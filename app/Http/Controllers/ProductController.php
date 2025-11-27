<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductDetail;
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
        // === BAGIAN UNTUK MENGAMBIL SEMUA PRODUK (DROPDOWN/POS) ===
        // Endpoint ini harusnya tidak memuat detail stok/harga, hanya ID dan Nama
        if ($request->boolean('all')) { 
            $allProducts = Product::with(['unit:id,short_name']) 
                                  ->orderBy('name')
                                  ->get(['id', 'name', 'unit_id']);
            
            return response()->json($allProducts);

        }
        
        // --- LOGIKA UTAMA TABEL PRODUK ---
        
        // Validasi HANYA dijalankan jika BUKAN ?all=true
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id', 
            'search' => 'nullable|string',
            'category_id' => 'nullable',
            'brand_id' => 'nullable|integer|exists:brands,id', 
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        
        $branchesId = $request->branches_id;
        $perPage = $request->input('per_page', 50); 
        
        // Query produk, eager load relasi DAN detail stok untuk cabang ini
        $query = Product::with([
            'category', 
            'brand', 
            'unit',
            // Wajib: Eager load detail stok HANYA dari cabang yang diminta
            'details' => function($q) use ($branchesId) {
                $q->where('branches_id', $branchesId);
            }
        ])
        // Wajib: Filter agar hanya tampilkan produk yang memang sudah terdaftar (memiliki detail) di cabang ini
        ->whereHas('details', function($q) use ($branchesId) {
            $q->where('branches_id', $branchesId);
        });

        // Terapkan filter pencarian
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        // Terapkan filter kategori
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }
        
        // Terapkan filter merek
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Ambil hasil dengan paginasi
        $products = $query->latest()->paginate($perPage);

        // Kembalikan dalam format paginasi standar Laravel
        return response()->json(['data' => $products]);
    }

    /**
     * Menyimpan produk baru beserta detailnya untuk cabang yang ditentukan.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'branches_id' => 'required|integer|exists:branches,id',
            'item_code' => 'nullable|string', 
            'category_id' => 'required|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'purchase_price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'current_stock' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $imagePath = null;
            if ($request->hasFile('photo')) {
                $imagePath = $request->file('photo')->store('products', 'public');
            }

            // Data produk bersifat global
            $product = Product::create([
                'name' => $validatedData['name'],
                'item_code' => $validatedData['item_code'] ?? null,
                'unit_id' => $validatedData['unit_id'],
                'category_id' => $validatedData['category_id'],
                'brand_id' => $validatedData['brand_id'] ?? null,
                'description' => $validatedData['description'] ?? null,
                'photo' => $imagePath,
            ]);

            // Data detail produk (stok, harga) spesifik untuk cabang
            ProductDetail::create([
                'product_id' => $product->id,
                'branches_id' => $validatedData['branches_id'], 
                'purchase_price' => $validatedData['purchase_price'],
                'sales_price' => $validatedData['sales_price'],
                'current_stock' => $validatedData['current_stock'],
                'status' => $validatedData['current_stock'] > 0 ? 'in_stock' : 'out_of_stock',
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Hapus gambar jika transaksi gagal
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json(['success' => false, 'message' => 'Gagal membuat produk.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Produk berhasil dibuat', 'data' => $product], 201);
    }

    /**
     * Menampilkan detail satu produk untuk cabang tertentu.
     */
    public function show(Request $request, Product $product)
    {
        $request->validate(['branches_id' => 'required|integer|exists:branches,id']);
        $branchesId = $request->branches_id;
        
        // Muat data produk global
        $product->load(['category', 'brand', 'unit']);
        
        // Muat detail stok/harga HANYA untuk cabang yang aktif
        $product->load(['details' => function($q) use ($branchesId) {
            $q->where('branches_id', $branchesId);
        }]);
        
        // Jika detail tidak ditemukan, 'details' akan berupa koleksi kosong.
        // Frontend harus menangani ini dengan aman (sudah dilakukan di TabelProduk.vue).

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

    // Helper functions for dropdowns (sudah benar)
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