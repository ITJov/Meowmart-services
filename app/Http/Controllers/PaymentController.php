<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductDetail;
use App\Models\ProductBatches;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductItem; // Tambahkan Model Baru
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

        $query = Payment::with(['order.customer', 'order.user', 'paymentMode', 'order.items.product'])
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
     */
    public function store(Request $request)
    {
       $validated = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'payment_method' => 'required|string',
            'cart' => 'required|array',
            'subtotal' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0', 
            'paid_amount' => 'required|numeric|min:0', 
            'cart.*.name' => 'required|string',
            'cart.*.unique_item_id' => 'nullable|integer|exists:product_items,id', 
            'cart.*.id' => 'required', 
            'cart.*.quantity' => 'required|integer|min:1',
            'cart.*.price' => 'required|numeric|min:0',
        ]);
        
        if ($validated['paid_amount'] < $validated['total']) {
            return response()->json(['message' => 'Jumlah bayar kurang.'], 422);
        }

        DB::beginTransaction();
        try {
            $user = $request->user();

            // Pembuatan Order
            $order = Order::create([
                'branches_id' => $validated['branches_id'],
                'customer_id' => $validated['customer_id'],
                'user_id' => $user->id,
                'invoice_number' => 'SALE-' . date('Ymd') . '-' . strtoupper(uniqid()),
                'subtotal' => $validated['subtotal'],
                'total' => $validated['total'], 
                'payment_status' => 'paid',
                'order_status' => 'Selesai', 
                'order_date' => now(),
            ]);

            foreach ($validated['cart'] as $item) {
                // 1. JIKA ITEM ADALAH JASA
                if (!empty($item['registration_id'])) {
                    $order->items()->create([
                        'item_name'  => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'subtotal' => $item['quantity'] * $item['price'],
                    ]);
                } 
                // 2. JIKA ITEM ADALAH PRODUK FISIK
                else {
                    /** * BAGIAN PERUBAHAN UNTUK UNIT TRACKING (QR UNIT)
                     * Jika ada unique_item_id, ubah status unit tersebut menjadi 'Terjual'
                     */
                    if (!empty($item['unique_item_id'])) {
                        $unit = ProductItem::lockForUpdate()->find($item['unique_item_id']);
                        if ($unit) {
                            $unit->update(['status' => 'Terjual']);
                        }
                    }

                    // Logika Pengurangan Stok (FEFO) tetap berjalan untuk sinkronisasi batch
                    $this->reduceStockFEFO($validated['branches_id'], $item['id'], $item['quantity']);

                    $order->items()->create([
                        'product_id' => $item['id'],
                        'item_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'subtotal' => $item['quantity'] * $item['price'],
                    ]);
                }
            }
            
            // Catat Pembayaran
            $paymentMode = PaymentMode::where('name', $validated['payment_method'])->first();
            Payment::create([
                'order_id' => $order->id,
                'payment_mode_id' => $paymentMode->id ?? 1, 
                'amount' => $validated['paid_amount'], 
                'payment_date' => now(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Pembayaran berhasil diproses!', 'data' => $order], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show($id)
    {
        // Cari Payment berdasarkan ID dan muat semua relasi hingga ke level item produk
        $payment = Payment::with([
            'order.customer', 
            'order.user', 
            'paymentMode', 
            'order.items.product' 
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }

public function reduceStockFEFO($branchId, $productId, $qtyToSell)
    {
        $batches = ProductBatches::where('branches_id', $branchId)
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->orderBy('expiry_date', 'asc')
            ->lockForUpdate()
            ->get();

        $remainingToDeduct = $qtyToSell;
        foreach ($batches as $batch) {
            if ($remainingToDeduct <= 0) break;
            if ($batch->quantity >= $remainingToDeduct) {
                $batch->decrement('quantity', $remainingToDeduct);
                $remainingToDeduct = 0;
            } else {
                $remainingToDeduct -= $batch->quantity;
                $batch->update(['quantity' => 0]);
            }
        }

        $productDetail = ProductDetail::where('branches_id', $branchId)
            ->where('product_id', $productId)
            ->first();
        if ($productDetail) {
            $productDetail->decrement('current_stock', $qtyToSell);
        }
    }
}