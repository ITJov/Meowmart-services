<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductDetail;
use App\Models\PaymentMode;
use App\Models\Discount;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Menampilkan daftar pembayaran berdasarkan cabang dengan data relasi.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
        ]);

        $query = Payment::with(['order.customer', 'order.user', 'paymentMode', 'order.items'])
                        // Filter berdasarkan 'branches_id' yang ada di 'orders'
                        ->whereHas('order', function ($q) use ($request) {
                            $q->where('branches_id', $request->branches_id);
                        });
        // ======================================================

        if ($request->filled('search')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('invoice_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', '%' . $request->search . '%'));
            });
        }
        
        // Sorting tetap berdasarkan tanggal pembayaran terbaru
        $payments = $query->latest('payment_date')->paginate($request->per_page ?? 10);

        return response()->json(['data' => $payments]);
    }

    /**
     * Memproses dan menyimpan checkout dari POS.
     * Diperbarui untuk menangani PRODUK dan LAYANAN, serta mencegah STOK MINUS.
     */
    public function store(Request $request)
    {
        // Validasi dasar
        $validated = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'payment_method' => 'required|string',
            'cart' => 'required|array',
            
            // Validasi Rincian Pembayaran
            'subtotal' => 'required|numeric|min:0',
            
            // Pajak & Diskon FK dan Nilai (nullable)
            'tax_id' => 'nullable|integer|exists:taxes,id', 
            'total_tax' => 'nullable|numeric|min:0', 

            'discount_id' => 'nullable|integer|exists:discounts,id',
            'total_discount' => 'nullable|numeric|min:0',
            
            'total' => 'required|numeric|min:0', 
            
            // === PERBAIKAN: TAMBAHKAN PAID AMOUNT ===
            'paid_amount' => 'required|numeric|min:0', 
            // =======================================

            // Validasi Item Cart (tetap sama)
            'cart.*.id' => 'required|string', 
            'cart.*.quantity' => 'required|integer|min:1',
            'cart.*.price' => 'required|numeric|min:0',
            'cart.*.name' => 'required|string',
            'cart.*.registration_id' => 'nullable|integer', 
        ]);
        
        // === PERBAIKAN: VALIDASI PENTING - PAID AMOUNT HARUS LEBIH BESAR DARI TOTAL ===
        if ($validated['paid_amount'] < $validated['total']) {
             return response()->json(['message' => 'Jumlah yang dibayarkan (' . $validated['paid_amount'] . ') tidak boleh kurang dari total transaksi (' . $validated['total'] . ').'], 422);
        }
        // =============================================================================

        // === LANGKAH BARU 1: VALIDASI MINIMAL PEMBAYARAN DISKON DI BACKEND ===
        $discount = null;
        if ($validated['discount_id']) {
            $discount = Discount::find($validated['discount_id']);

            if (!$discount) {
                 return response()->json(['message' => 'Diskon yang dipilih tidak ditemukan.'], 404);
            }
            
            // Cek Syarat Minimum Pembayaran
            if ($validated['subtotal'] < $discount->min_payment_amount) {
                // Hapus diskon dari order dan set total_discount = 0
                $validated['discount_id'] = null;
                $validated['total_discount'] = 0;
            }
            
            // PERINGATAN TAMBAHAN: Cek konsistensi nilai.
            if ($validated['total_discount'] > 0 && $validated['discount_id'] === null) {
                $validated['total_discount'] = 0;
            }
        }
        // === AKHIR LANGKAH BARU 1 ===


        DB::beginTransaction();
        try {
            $user = $request->user();
            
            // 1. Buat Order baru
            $order = Order::create([
                'branches_id' => $validated['branches_id'],
                'customer_id' => $validated['customer_id'],
                'user_id' => $user->id,
                'invoice_number' => 'SALE-' . date('Ymd') . '-' . strtoupper(uniqid()),
                
                // Kolom Diskon dan Pajak yang sudah divalidasi
                'discount_id' => $validated['discount_id'] ?? null,
                'tax_id' => $validated['tax_id'] ?? null,
                'total_discount' => $validated['total_discount'] ?? 0,
                'total_tax' => $validated['total_tax'] ?? 0,
                
                // Kolom Total
                'subtotal' => $validated['subtotal'],
                'total' => $validated['total'], 
                
                'payment_status' => 'paid',
                'order_status' => 'Selesai', // FIX: Set status penjualan agar tidak kosong
                'order_date' => now(),
            ]);

            // 2. Loop keranjang & proses item
            foreach ($validated['cart'] as $item) {
                
                $isService = ($item['registration_id'] !== null); 

                if ($isService) {
                    // --- INI ADALAH LAYANAN (JASA) ---
                    $registrationId = $item['registration_id'];
                    $registration = Registration::find($registrationId);
                    
                    if ($registration) {
                        
                        // Buat Order Item
                        $order->items()->create([
                            'product_id' => null, 
                            'item_name'  => $item['name'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['price'],
                            'subtotal' => $item['quantity'] * $item['price'],
                        ]);

                        $registration->update(['status' => 'Selesai']); 
                    }
                } else {
                    // --- INI ADALAH PRODUK ---
                    
                    // 3. Validasi stok DENGAN LOCK (Mencegah Stok Minus)
                    $productDetail = ProductDetail::where('product_id', $item['id'])
                                                ->where('branches_id', $validated['branches_id'])
                                                ->lockForUpdate()
                                                ->first();
                    
                    if (!$productDetail || $productDetail->current_stock < $item['quantity']) {
                        throw new \Exception('Stok untuk produk "' . $item['name'] . '" tidak mencukupi.');
                    }

                    // 4. Buat Order Item
                    $order->items()->create([
                        'product_id' => $item['id'],
                        'item_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'subtotal' => $item['quantity'] * $item['price'],
                    ]);

                    // 5. Kurangi stok produk
                    $productDetail->decrement('current_stock', $item['quantity']);
                }
            }
            
            // 6. Buat Payment baru
            $paymentMode = PaymentMode::where('name', $validated['payment_method'])->first();

            Payment::create([
                'order_id' => $order->id,
                'payment_mode_id' => $paymentMode->id ?? 1, 
                // === PERBAIKAN: GUNAKAN PAID AMOUNT DI SINI ===
                'amount' => $validated['paid_amount'], 
                // =============================================
                'payment_date' => now(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Pembayaran berhasil diproses!', 'data' => $order], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

