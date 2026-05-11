<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\ProductDetail;
use App\Models\ProductBatches;
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
        'purchase_status' => 'required|string', 
        'payment_status' => 'required|string', 
        'notes' => 'nullable|string',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|integer|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.unit_price' => 'required|numeric|min:0',
        'items.*.expiry_date' => 'required|date', // WAJIB ADA untuk FEFO
    ]);

    DB::beginTransaction();
    try {
        $totalAmount = collect($validatedData['items'])->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'];
        });

        // 1. Simpan Header Pembelian
        $purchase = Purchase::create([
            'branches_id' => $validatedData['branches_id'],
            'supplier' => $validatedData['supplier'],
            'invoice_number' => 'PUR-' . strtoupper(Str::random(10)),
            'purchase_date' => $validatedData['purchase_date'],
            'purchase_status' => $validatedData['purchase_status'],
            'total_amount' => $totalAmount,
            'payment_status' => $validatedData['payment_status'],
            'notes' => $validatedData['notes'] ?? null,
        ]);

        foreach ($validatedData['items'] as $itemData) {
            // 2. Simpan Rincian Item Pembelian (History Transaksi)
            $purchase->items()->create([
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'subtotal' => $itemData['quantity'] * $itemData['unit_price'],
            ]);

            // HANYA TAMBAH STOK JIKA STATUS 'SELESAI' / 'DITERIMA'
            if (in_array($validatedData['purchase_status'], ['Selesai', 'Diterima'])) {
                
                // 3. Simpan ke Tabel Batch (Pecahan Stok + Expired Date)
                ProductBatches::create([
                    'branches_id' => $validatedData['branches_id'],
                    'product_id'  => $itemData['product_id'],
                    'expiry_date' => $itemData['expiry_date'],
                    'quantity'    => $itemData['quantity'],
                    'batch_code'  => 'BATCH-' . time() . '-' . rand(100,999),
                ]);

                // 4. Update Total Stok (Agregat) di ProductDetail
                $productDetail = ProductDetail::firstOrCreate(
                    [
                        'product_id' => $itemData['product_id'],
                        'branches_id' => $validatedData['branches_id']
                    ],
                    [
                        'purchase_price' => $itemData['unit_price'],
                        'sales_price' => $itemData['unit_price'] * 1.25, // Default margin
                        'current_stock' => 0,
                        'status' => 'in_stock'
                    ]
                );
                
                // Update harga beli terbaru (opsional, tergantung kebijakan akuntansi)
                $productDetail->purchase_price = $itemData['unit_price'];
                
                // Tambah total stok
                $productDetail->increment('current_stock', $itemData['quantity']);
                $productDetail->save();
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

    public function show(Purchase $purchase)
    {
        // Memuat semua item terkait untuk ditampilkan di form edit
        $purchase->load('items'); 
        return response()->json(['data' => $purchase]);
    }
    
    /**
     * Memperbarui data pengadaan produk dan menyesuaikan stok.
     */
    public function update(Request $request, Purchase $purchase)
    {
        $validatedData = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'supplier' => 'required|string|max:255',
            'purchase_date' => 'required|date',
            'purchase_status' => 'required|string', 
            'payment_status' => 'required|string', 
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0', // Harga beli
        ]);

        DB::beginTransaction();
        try {
            // 1. Hitung ulang total pembelian
            $newTotalAmount = collect($validatedData['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            // 2. Logika Penyesuaian Stok LAMA: Hanya kurangi stok jika status LAMA sudah "Selesai" atau "Diterima"
            if (in_array($purchase->purchase_status, ['Selesai', 'Diterima'])) {
                foreach ($purchase->items as $oldItem) {
                    $productDetail = ProductDetail::where('product_id', $oldItem->product_id)
                                                ->where('branches_id', $purchase->branches_id)
                                                ->lockForUpdate()
                                                ->first();
                    if ($productDetail) {
                        // KURANGI stok lama (membatalkan transaksi lama)
                        $productDetail->decrement('current_stock', $oldItem->quantity);
                    }
                }
            }

            // 3. Hapus Item lama dan update Purchase
            $purchase->items()->delete();
            
            $purchase->update([
                'supplier' => $validatedData['supplier'],
                'purchase_date' => $validatedData['purchase_date'],
                'purchase_status' => $validatedData['purchase_status'],
                'total_amount' => $newTotalAmount,
                'payment_status' => $validatedData['payment_status'],
                'notes' => $validatedData['notes'] ?? null,
            ]);

            // 4. Simpan Item BARU dan terapkan stok BARU
            if (in_array($validatedData['purchase_status'], ['Selesai', 'Diterima'])) {
                 foreach ($validatedData['items'] as $itemData) {
                    // Tambah Item Baru
                    $purchase->items()->create([
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemData['quantity'] * $itemData['unit_price'],
                    ]);

                    // Tambah stok BARU
                    $productDetail = ProductDetail::firstOrCreate(
                        [
                            'product_id' => $itemData['product_id'],
                            'branches_id' => $validatedData['branches_id']
                        ],
                        ['current_stock' => 0] // Tambahkan field default yang diperlukan
                    );
                    $productDetail->increment('current_stock', $itemData['quantity']);
                }
            } else {
                // Jika status baru BUKAN 'Selesai'/'Diterima', tetap simpan item baru tanpa menambah stok
                // PurchaseController.php

                foreach ($validatedData['items'] as $itemData) {
                    // 1. Simpan Rincian Item Pembelian (History Transaksi)
                    $purchase->items()->create([
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemData['quantity'] * $itemData['unit_price'],
                    ]);

                    // HANYA TAMBAH STOK & UNIT JIKA STATUS 'SELESAI' / 'DITERIMA'
                    if (in_array($validatedData['purchase_status'], ['Selesai', 'Diterima'])) {
                        
                        // 2. Simpan ke Tabel Batch (Pecahan Stok + Expired Date)
                        $batch = \App\Models\ProductBatches::create([
                            'branches_id' => $validatedData['branches_id'],
                            'product_id'  => $itemData['product_id'],
                            'expiry_date' => $itemData['expiry_date'],
                            'quantity'    => $itemData['quantity'],
                            'batch_code'  => 'BATCH-' . time() . '-' . rand(100,999),
                        ]);

                        // 3. GENERATE UNIT UNIK (KUNCI AGAR TABEL PRODUCT_ITEMS TERISI)
                        // Kita ambil nama produk untuk kode QR agar mudah dibaca
                        $product = \App\Models\Product::find($itemData['product_id']);
                        $productName = $product ? Str::slug($product->name) : 'PROD';

                        for ($i = 0; $i < $itemData['quantity']; $i++) {
                            \App\Models\ProductItem::create([
                                'product_id' => $itemData['product_id'],
                                'branches_id' => $validatedData['branches_id'],
                                'product_batch_id' => $batch->id,
                                // Format: NAMA-TGL-RANDOM (Contoh: MEO-20260130-ABC12)
                                'unique_qr_code' => strtoupper($productName . '-' . date('Ymd') . '-' . Str::random(5)),
                                'status' => 'Tersedia',
                            ]);
                        }

                        // 4. Update Total Stok di ProductDetail
                        $productDetail = \App\Models\ProductDetail::firstOrCreate(
                            [
                                'product_id' => $itemData['product_id'],
                                'branches_id' => $validatedData['branches_id']
                            ],
                            [
                                'purchase_price' => $itemData['unit_price'],
                                'sales_price' => $itemData['unit_price'] * 1.25,
                                'current_stock' => 0,
                                'status' => 'in_stock'
                            ]
                        );
                        
                        $productDetail->increment('current_stock', $itemData['quantity']);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => 'Pengadaan produk berhasil diperbarui.', 
                'data' => $purchase
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui pengadaan produk.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

