<?php

namespace App\Http\Controllers;

use App\Models\TransferStocks;
use App\Models\ProductDetail;
use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransferStockController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|nullable',
        ]);

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $query = TransferStocks::query()
            ->with([
                'fromWarehouse',
                'toWarehouse',
                'user',
                'items.product', // Eager load produk
                'items.product.unit' // Eager load satuan
            ])
            ->latest();

        if ($search) {
            $query->where('reference_number', 'like', '%' . $search . '%');
        }

        $transfers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validasi Input
        $data = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'transfer_date' => 'required|date',
            'status' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // 2. Validasi Logika Bisnis: Cek Cabang Asal vs Tujuan
        $fromWarehouse = Warehouse::findOrFail($data['from_warehouse_id']);
        $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);

        if ($fromWarehouse->branches_id === $toWarehouse->branches_id) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer stok harus dilakukan antar cabang yang berbeda. Stok dicatat per cabang, bukan per gudang.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 3. Buat Header Transfer
            $transfer = TransferStocks::create([
                'reference_number' => $this->generateReferenceNumber(),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'transfer_date' => $data['transfer_date'],
                'status' => 'Completed', // Asumsi langsung selesai
                'user_id' => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                // A. AMBIL DATA STOK DARI CABANG ASAL (PENTING UNTUK HARGA)
                // Kita perlu tahu harga beli/jual dari cabang asal untuk dipindahkan ke cabang tujuan
                $sourceStock = ProductDetail::where('product_id', $item['product_id'])
                                            ->where('branches_id', $fromWarehouse->branches_id)
                                            ->lockForUpdate() // Kunci agar tidak berubah saat proses
                                            ->first();

                // Validasi ketersediaan stok di Backend (Safety Net)
                if (!$sourceStock || $sourceStock->current_stock < $item['quantity']) {
                    throw new \Exception("Stok tidak mencukupi di gudang asal untuk produk ID: " . $item['product_id']);
                }

                // Simpan harga referensi untuk dipakai di cabang tujuan
                $priceReference = [
                    'purchase_price' => $sourceStock->purchase_price,
                    'sales_price' => $sourceStock->sales_price
                ];

                // B. Catat Item Transfer
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);

                // C. Kurangi Stok GUDANG ASAL
                // Parameter terakhir null karena pengurangan tidak butuh referensi harga baru
                $this->updateStock(
                    $item['product_id'],
                    $fromWarehouse->branches_id,
                    - (int)$item['quantity']
                );

                // D. Tambah Stok GUDANG TUJUAN
                // Kita kirim $priceReference agar jika stok baru dibuat, harganya tidak 0
                $this->updateStock(
                    $item['product_id'],
                    $toWarehouse->branches_id,
                    (int)$item['quantity'],
                    $priceReference
                );
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Transfer stok berhasil disimpan'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper untuk update stok.
     * Menerima branches_id langsung agar lebih efisien.
     */
    private function updateStock($productId, $branchId, $quantityChange, $priceData = [])
    {
        // Cari stok di cabang tersebut
        $stock = ProductDetail::where('product_id', $productId)
                              ->where('branches_id', $branchId)
                              ->first();

        // KASUS 1: PENGURANGAN STOK
        if ($quantityChange < 0) {
            // Validasi sudah dilakukan di loop utama, tapi double check tidak masalah
            if ($stock) {
                $stock->decrement('current_stock', abs($quantityChange));
            }
        }
        // KASUS 2: PENAMBAHAN STOK
        else if ($quantityChange > 0) {
            if ($stock) {
                // Jika stok sudah ada, cukup tambah qty
                $stock->increment('current_stock', $quantityChange);
            } else {
                // JIKA STOK BELUM ADA DI CABANG TUJUAN -> BUAT BARU
                // Gunakan harga dari parameter $priceData (dari cabang asal)
                ProductDetail::create([
                    'product_id' => $productId,
                    'branches_id' => $branchId,
                    'current_stock' => $quantityChange,
                    'sales_price' => $priceData['sales_price'] ?? 0,
                    'purchase_price' => $priceData['purchase_price'] ?? 0,
                    'stock_alert' => 5, // Default alert
                    'status' => 'in_stock',
                ]);
            }
        }
    }

    private function generateReferenceNumber()
    {
        $prefix = 'TF/' . date('ym') . '/';
        $lastTransfer = TransferStocks::where('reference_number', 'like', $prefix . '%')
                                    ->orderBy('id', 'desc')
                                    ->first();

        $nextNumber = 1;
        if ($lastTransfer) {
            $lastNumber = (int)substr($lastTransfer->reference_number, -4);
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}