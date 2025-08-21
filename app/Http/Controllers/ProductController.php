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

class ProductController extends Controller
{
    /**
     * Menampilkan daftar produk milik perusahaan user yang login.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
        ]);

        $user = $request->user();

        // Query hanya mengambil produk milik perusahaan user yang login
        $query = Product::with(['category', 'brand', 'unit', 'details' => function($q) use ($user) {
            // Sertakan detail HANYA dari warehouse user saat ini
            $q->where('warehouse_id', $user->warehouse_id);
        }])->where('company_id', $user->company_id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->latest()->paginate(15);

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * Menyimpan produk baru.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'item_code' => ['required', 'string', Rule::unique('products')->where('company_id', $request->user()->company_id)],
            'unit_id' => 'required|integer|exists:units,id',
            'category_id' => 'required|integer|exists:categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'purchase_price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'current_stock' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();
            $user = $request->user();

            $product = Product::create([
                'name' => $validatedData['name'],
                'item_code' => $validatedData['item_code'],
                'unit_id' => $validatedData['unit_id'],
                'category_id' => $validatedData['category_id'],
                'brand_id' => $validatedData['brand_id'] ?? null,
                'company_id' => $user->company_id, // <-- Diaktifkan
            ]);

            ProductDetail::create([
                'product_id' => $product->id,
                'warehouse_id' => $user->warehouse_id, // <-- Diaktifkan
                'purchase_price' => $validatedData['purchase_price'],
                'sales_price' => $validatedData['sales_price'],
                'current_stock' => $validatedData['current_stock'],
                'status' => $validatedData['current_stock'] > 0 ? 'in_stock' : 'out_of_stock',
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create product.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Product created', 'data' => $product], 201);
    }

    /**
     * Menampilkan detail satu produk.
     */
    public function show(Product $product, Request $request)
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $product->load(['category', 'brand', 'unit', 'details']);
        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * Memperbarui data produk.
     */
    public function update(Request $request, Product $product)
    {
        // Keamanan: Pastikan user hanya bisa mengedit produk di perusahaannya
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'unit_id' => 'required|integer|exists:units,id',
            'category_id' => 'required|integer|exists:categories,id',
            
            'purchase_price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
        ]);
        
        try {
            DB::beginTransaction();

            $product->update([
                'name' => $validatedData['name'],
                'unit_id' => $validatedData['unit_id'],
                'category_id' => $validatedData['category_id'],
            ]);

            $productDetail = $product->details()->where('warehouse_id', $request->user()->warehouse_id)->first();
            if ($productDetail) {
                $productDetail->update([
                    'purchase_price' => $validatedData['purchase_price'],
                    'sales_price' => $validatedData['sales_price'],
                ]);
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to update product.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Product updated successfully', 'data' => $product]);
    }

    /**
     * Menghapus produk.
     */
    public function destroy(Product $product)
    {
        if ($product->photo) {
            Storage::disk('public')->delete($product->photo);
        }
        
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Product deleted successfully']);
    }


    public function findUnits(Request $request)
    {
        $units = Unit::where('name', 'like', '%' . $request->query('term') . '%')->get(['id', 'name']);
        return response()->json($units);
    }
    
    public function findCategories(Request $request)
    {
        $categories = Category::where('name', 'like', '%' . $request->query('term') . '%')->get(['id', 'name']);
        return response()->json($categories);
    }
    
    public function findBrands(Request $request)
    {
        $brands = Brand::where('name', 'like', '%' . $request->query('term') . '%')->get(['id', 'name']);
        return response()->json($brands);
    }
}