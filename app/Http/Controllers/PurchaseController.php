<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    /**
     * Menampilkan daftar pengadaan produk untuk cabang yang aktif.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
        ]);

        // DIUBAH: Menghapus relasi 'supplier' yang tidak ada
        $query = Purchase::where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }

        $purchases = $query->latest('purchase_date')->paginate(10);

        return response()->json(['data' => $purchases]);
    }

    /**
     * Menyimpan data pengadaan produk baru dan memperbarui stok.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'supplier' => 'required|string|max:255',
            'purchase_date' => 'required|date',
            'purchase_status' => 'required|string', // e.g., "Selesai", "Dipesan"
            'payment_status' => 'required|string', // e.g., "Paid", "Unpaid"
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0', // Harga beli
        ]);

        DB::beginTransaction();
        try {
            // Hitung total pembelian di backend untuk keamanan
            $totalAmount = collect($validatedData['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            // 1. Buat data Pembelian (Purchase) utama
            $purchase = Purchase::create([
                'branches_id' => $validatedData['branches_id'],
                'supplier' => $validatedData['supplier'],
                'invoice_number' => 'PUR-' . strtoupper(Str::random(10)), // Generate nomor invoice otomatis
                'purchase_date' => $validatedData['purchase_date'],
                'purchase_status' => $validatedData['purchase_status'],
                'total_amount' => $totalAmount,
                'payment_status' => $validatedData['payment_status'],
                'notes' => $validatedData['notes'] ?? null,
            ]);

            // 2. Simpan setiap item pembelian dan perbarui stok
            foreach ($validatedData['items'] as $itemData) {
                // Buat rincian item pembelian
                $purchase->items()->create([
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['quantity'] * $itemData['unit_price'],
                ]);

                // 3. Tambah stok HANYA jika status pembelian "Selesai" atau "Diterima"
                if (in_array($validatedData['purchase_status'], ['Selesai', 'Diterima'])) {
                    // Cari detail produk di cabang ini
                    $productDetail = ProductDetail::firstOrCreate(
                        [
                            'product_id' => $itemData['product_id'],
                            'branches_id' => $validatedData['branches_id']
                        ],
                        [
                            'purchase_price' => $itemData['unit_price'],
                            'sales_price' => $itemData['unit_price'] * 1.25, // Atur margin default 25%
                            'current_stock' => 0,
                            'status' => 'in_stock'
                        ]
                    );
                    
                    // Tambah stok yang ada
                    $productDetail->increment('current_stock', $itemData['quantity']);
                }
            }
            
            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => 'Pengadaan produk berhasil disimpan.', 
                'data' => $purchase
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menyimpan pengadaan produk.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

