<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductDetail;
use App\Models\ProductBatches;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * Menampilkan daftar produk berdasarkan cabang yang aktif dari request.
     * Termasuk eager loading untuk details HANYA pada cabang yang diminta.
     */
    public function index(Request $request)
    {
        // === 1. LOGIKA UNTUK DROPDOWN / POS (Ambil Semua Produk Aktif) ===
        if ($request->boolean('all')) { 
            $allProductsQuery = Product::with(['unit:id,short_name']) 
                                    ->orderBy('name');
            
            // Jika dipanggil dari POS, pastikan hanya mengambil yang aktif
            if ($request->boolean('only_active')) {
                $allProductsQuery->where('is_active', true);
            }

            $allProducts = $allProductsQuery->get(['id', 'name', 'unit_id', 'is_active']);
            
            return response()->json($allProducts);
        }
        
        // === 2. VALIDASI INPUT UNTUK TABEL MANAJEMEN ===
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id', 
            'search'      => 'nullable|string',
            'category_id' => 'nullable',
            'brand_id'    => 'nullable|integer|exists:brands,id', 
            'per_page'    => 'nullable|integer|min:1|max:100',
            'only_active' => 'nullable' // Tambahan filter status
        ]);
        
        $branchesId = $request->branches_id;
        $perPage    = $request->input('per_page', 50); 
        
        // === 3. QUERY UTAMA DENGAN EAGER LOADING ===
        $query = Product::with([
            'category', 
            'brand', 
            'unit',
            'details' => function($q) use ($branchesId) {
                $q->where('branches_id', $branchesId)
                ->select('product_id', 'sales_price', 'purchase_price', 'current_stock');
            },
            'batches' => function($q) use ($branchesId) {
                $q->where('branches_id', $branchesId)
                ->where('quantity', '>', 0)
                ->orderBy('expiry_date', 'asc') 
                ->limit(1); 
            }
        ])
        // Filter agar hanya tampilkan produk yang terdaftar di cabang ini
        ->whereHas('details', function($q) use ($branchesId) {
            $q->where('branches_id', $branchesId);
        });

        // === 4. PENERAPAN FILTER ===

        // Filter Status Aktif/Inactive
        if ($request->has('only_active')) {
            $isActive = $request->boolean('only_active');
            $query->where('is_active', $isActive);
        }

        // Filter Pencarian
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        // Filter Kategori
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }
        
        // Filter Merek
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // === 5. EKSEKUSI PAGINASI ===
        $products = $query->latest()->paginate($perPage);

        return response()->json(['data' => $products]);
    }

    /**
     * Menyimpan produk baru beserta detailnya untuk cabang yang ditentukan.
     */
    public function store(Request $request)
{
    // 1. Validasi Input
    $validatedData = $request->validate([
        'branches_id'    => 'required|integer|exists:branches,id',
        'name'           => 'required|string|max:255',
        'category_id'    => 'required|integer|exists:categories,id',
        'unit_id'        => 'required|integer|exists:units,id',
        'brand_id'       => 'nullable|integer|exists:brands,id',
        'description'    => 'nullable|string',
        'photo'          => 'nullable|image|max:2048',
        'item_code'      => 'nullable|string',
        
        // Data Keuangan & Stok
        'purchase_price' => 'required|numeric|min:0',
        'sales_price'    => 'required|numeric|min:0',
        'current_stock'  => 'required|integer|min:0',
        'stock_alert'    => 'required|integer|min:0',
        
        // PERBAIKAN: Wajib ada expiry_date untuk stok awal
        'expiry_date'    => 'required|date', 
    ]);

    DB::beginTransaction();
    try {
        // Upload Gambar
        $imagePath = null;
        if ($request->hasFile('photo')) {
            $imagePath = $request->file('photo')->store('products', 'public');
        }

        // 2. Simpan Produk Master
        $product = Product::create([
            'name'        => $validatedData['name'],
            'item_code'   => $validatedData['item_code'],
            'unit_id'     => $validatedData['unit_id'],
            'category_id' => $validatedData['category_id'],
            'brand_id'    => $validatedData['brand_id'] ?? null,
            'description' => $validatedData['description'] ?? null,
            'photo'       => $imagePath,
        ]);

        // 3. Simpan Product Batch (Untuk FEFO/Expired) - KHUSUS PRODUK BARU
        if ($validatedData['current_stock'] > 0) {
            ProductBatches::create([
                'branches_id' => $validatedData['branches_id'],
                'product_id'  => $product->id,
                'expiry_date' => $validatedData['expiry_date'], 
                'quantity'    => $validatedData['current_stock'],
                'batch_code'  => 'INIT-' . time(),
            ]);
        }

        // 4. Simpan Product Detail (Agregat Stok & Harga)
        ProductDetail::create([
            'product_id'     => $product->id,
            'branches_id'    => $validatedData['branches_id'], 
            'purchase_price' => $validatedData['purchase_price'],
            'sales_price'    => $validatedData['sales_price'],
            'current_stock'  => $validatedData['current_stock'],
            'stock_alert'    => $validatedData['stock_alert'],
            'status'         => $validatedData['current_stock'] > 0 ? 'in_stock' : 'out_of_stock',
        ]);
        
        DB::commit();
        return response()->json(['success' => true, 'message' => 'Produk berhasil dibuat', 'data' => $product], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        // Hapus gambar jika gagal
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

    /**
     * Menampilkan detail satu produk untuk cabang tertentu.
     */
    public function show(Request $request, Product $product)
    {
        $request->validate(['branches_id' => 'required|integer|exists:branches,id']);
        $branchesId = $request->branches_id;
        
        // 1. Muat data produk global
        $product->load(['category', 'brand', 'unit']);
        
        // 2. Muat detail stok/harga HANYA untuk cabang yang aktif
        $product->load(['details' => function($q) use ($branchesId) {
            $q->where('branches_id', $branchesId);
        }]);

        // 3. TAMBAHAN: Muat data Batches (Informasi Kadaluwarsa) per cabang
        // Diurutkan dari yang paling dekat tanggal kadaluwarsanya (ASC)
        $product->load(['batches' => function($q) use ($branchesId) {
            $q->where('branches_id', $branchesId)
            ->where('quantity', '>', 0) // Hanya tampilkan batch yang masih ada stoknya
            ->orderBy('expiry_date', 'asc'); 
        }]);

        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * Memperbarui produk global dan detailnya untuk cabang tertentu.
     */
    public function update(Request $request, Product $product)
    {
        $validatedData = $request->validate([
            // Validasi data global (tabel products)
            'name' => 'required|string|max:255',
            'item_code' => ['nullable', 'string', Rule::unique('products')->ignore($product->id)],
            'unit_id' => 'required|integer|exists:units,id',
            'category_id' => 'required|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'description' => 'nullable|string',
            'photo' => 'nullable|sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',

            // Validasi data spesifik cabang (tabel product_details)
            'branches_id' => 'required|integer|exists:branches,id',
            'purchase_price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'current_stock' => 'required|integer|min:0', // Note: Stok tidak boleh diubah di sini jika ada transaksi
        ]);

        try {
            DB::beginTransaction();

            $imagePath = $product->photo;
            // Cek jika ada file foto baru yang diupload
            if ($request->hasFile('photo')) {
                // Hapus foto lama jika ada
                if ($product->photo) {
                    Storage::disk('public')->delete($product->photo);
                }
                // Simpan foto baru
                $imagePath = $request->file('photo')->store('products', 'public');
            }

            // 1. Update data produk global
            $product->update([
                'name' => $validatedData['name'],
                'item_code' => $validatedData['item_code'] ?? null,
                'unit_id' => $validatedData['unit_id'],
                'category_id' => $validatedData['category_id'],
                'brand_id' => $validatedData['brand_id'] ?? null,
                'description' => $validatedData['description'] ?? null,
                'photo' => $imagePath,
            ]);

            // 2. Update atau buat detail produk untuk cabang ini
            ProductDetail::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'branches_id' => $validatedData['branches_id']
                ],
                [
                    'purchase_price' => $validatedData['purchase_price'],
                    'sales_price' => $validatedData['sales_price'],
                    'current_stock' => $validatedData['current_stock'],
                    'status' => $validatedData['current_stock'] > 0 ? 'in_stock' : 'out_of_stock',
                ]
            );
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui produk.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Produk berhasil diperbarui', 'data' => $product]);
    }

    // ProductController.php
    public function updateStock(Request $request, Product $product) 
        {
            $request->validate([
                'branches_id' => 'required|exists:branches,id',
                'quantity' => 'required|integer|min:1',
                'expiry_date' => 'nullable|date'
            ]);

            // 1. Update total stok di ProductDetail
            $detail = $product->details()->where('branches_id', $request->branches_id)->first();
            $detail->increment('current_stock', $request->quantity);

            // 2. Jika menggunakan sistem Batch/FEFO, buat record batch baru
            ProductBatches::create([
                'branches_id' => $request->branches_id,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'expiry_date' => $request->expiry_date ?? now()->addYear(),
                'batch_code' => 'RESTOCK-' . time(),
            ]);

            return response()->json(['message' => 'Stok berhasil ditambah']);
        }

    /**
     * Menghapus produk secara global dari semua cabang.
     */
    public function destroy(Product $product)
    {
        // Note: This deletes the product and its details across ALL branches.
        // Pastikan Anda menggunakan onDelete('cascade') di migrasi Anda.
        try {
            DB::beginTransaction();
            if ($product->photo) {
                Storage::disk('public')->delete($product->photo);
            }
            
            $product->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus produk.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Produk berhasil dihapus']);
    }

    public function toggleActive(Product $product)
    {
        // Balikkan nilai boolean
        $product->is_active = !$product->is_active;
        
        // Simpan secara permanen ke database
        $product->save(); 

        $statusTeks = $product->is_active ? 'diaktifkan' : 'dinonaktifkan';
        
        return response()->json([
            'success' => true,
            'message' => "Produk '{$product->name}' berhasil {$statusTeks}",
            'data' => [
                'is_active' => $product->is_active
            ]
        ]);
    }

    public function findByCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'branches_id' => 'required|integer'
        ]);

        // 1. Cari Unit Spesifik berdasarkan QR Code unik di tabel product_items
        $item = \App\Models\ProductItem::where('unique_qr_code', $request->code)
            ->where('branches_id', $request->branches_id)
            ->first();

        // Jika tidak ditemukan di tabel Unit Tracking
        if (!$item) {
            return response()->json([
                'success' => false, 
                'message' => 'QR Code Unit tidak terdaftar di sistem.'
            ], 404);
        }

        // 2. Cek apakah barang sudah terjual atau rusak
        if ($item->status !== 'Tersedia') {
            return response()->json([
                'success' => false,
                'message' => 'Barang gagal diproses. Status unit ini: ' . $item->status
            ], 422);
        }

        // 3. Ambil data produk induk + detail harganya
        $product = Product::with(['details' => function($query) use ($request) {
                $query->where('branches_id', $request->branches_id);
            }])
            ->find($item->product_id);

        if (!$product || !$product->is_active) {
            return response()->json([
                'success' => false, 
                'message' => 'Data produk induk tidak aktif atau tidak ditemukan.'
            ], 404);
        }

        // 4. Return data produk + ID unik item tersebut
        return response()->json([
            'success' => true,
            'data' => $product,
            // unique_item_id ini dikirim agar saat simpan, status unit ini bisa diubah jadi 'Terjual'
            'unique_item_id' => $item->id 
        ]);
    }

    public function findUnits(Request $request)
    {
        $units = Unit::all(['id', 'name']);
        return response()->json($units);
    }
    
    public function findCategories(Request $request)
    {
        $categories = Category::all(['id', 'name']);
        return response()->json($categories);
    }
    
    public function findBrands(Request $request)
    {
        $brands = Brand::orderBy('name')->get(['id', 'name']);
        return response()->json(['data' => $brands]);
    }
}