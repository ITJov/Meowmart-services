<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductDetail;
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

        $query = Payment::with(['order.customer', 'order.user', 'paymentMode'])
                        ->where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('invoice_number', 'like', '%' . $request->search . '%');
            });
        }

        $payments = $query->latest('payment_date')->paginate(10);

        return response()->json(['data' => $payments]);
    }

    /**
     * Memproses dan menyimpan checkout dari POS (menggunakan tabel Orders yang benar).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'payment_method' => 'required|string',
            'cart' => 'required|array',
            'cart.*.id' => 'required|integer|exists:products,id',
            'cart.*.quantity' => 'required|integer|min:1',
            'cart.*.price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user = $request->user();
            $subtotal = collect($validated['cart'])->sum(function ($item) {
                return $item['quantity'] * $item['price'];
            });

            // 1. Buat Order baru
            $order = Order::create([
                'branches_id' => $validated['branches_id'],
                'customer_id' => $validated['customer_id'],
                'user_id' => $user->id,
                'invoice_number' => 'SALE-' . strtoupper(Str::random(10)),
                'subtotal' => $subtotal,
                'total' => $subtotal, // Asumsi tidak ada diskon/pajak
                'payment_status' => 'paid',
                'order_date' => now(),
            ]);

            // 2. Buat Order Items
            foreach ($validated['cart'] as $item) {
                $order->items()->create([
                    'product_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'subtotal' => $item['quantity'] * $item['price'],
                ]);

                // 3. Kurangi stok produk
                $productDetail = ProductDetail::where('product_id', $item['id'])
                                              ->where('branches_id', $validated['branches_id'])
                                              ->first();
                if ($productDetail && $productDetail->current_stock >= $item['quantity']) {
                    $productDetail->decrement('current_stock', $item['quantity']);
                } else {
                    throw new \Exception('Stok untuk produk ID ' . $item['id'] . ' tidak mencukupi.');
                }
            }
            
            // 4. Buat Payment baru
            // Cari payment_mode_id berdasarkan nama
            $paymentMode = \App\Models\PaymentMode::where('name', $validated['payment_method'])->first();

            Payment::create([
                'branches_id' => $validated['branches_id'],
                'order_id' => $order->id,
                'payment_mode_id' => $paymentMode->id ?? 1, // Default ke 1 (Cash) jika tidak ketemu
                'amount' => $order->total,
                'payment_date' => now(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Pembayaran berhasil diproses!', 'data' => $order], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan.', 'error' => $e->getMessage()], 500);
        }
    }
}

