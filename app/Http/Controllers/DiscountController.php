<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{

    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Discount::query()->where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        $discounts = $query->latest()->paginate($request->per_page ?? 10);
        return response()->json(['success' => true, 'data' => $discounts]);
    }

    /**
     * Menyimpan diskon baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:discounts,code',
            'description' => 'nullable|string',
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            
            // Logika validasi nilai diskon
            'discount_value' => 'required|numeric|min:0.01',
            'min_payment_amount' => 'nullable|numeric|min:0',
            
            // Logika validasi tanggal
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            
            'is_active' => 'boolean',
            'usage_limit' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);
        
        // Konversi persentase jika tipe-nya percentage (misal: 15 menjadi 0.15)
        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 1) {
             $validated['discount_value'] = $validated['discount_value'] / 100;
        }

        $discount = Discount::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Diskon berhasil dibuat.',
            'data' => $discount
        ], 201);
    }

    /**
     * Menampilkan detail satu diskon.
     */
    public function show(Discount $discount)
    {
        // Keamanan: Pastikan hanya user dari branch yang sama yang bisa melihat
        if ($discount->branches_id !== auth()->user()->branches_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        return response()->json(['success' => true, 'data' => $discount]);
    }

    /**
     * Memperbarui diskon.
     */
    public function update(Request $request, Discount $discount)
    {
        // Keamanan
        if ($discount->branches_id !== auth()->user()->branches_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Unique code, abaikan ID diskon yang sedang di-edit
            'code' => ['nullable', 'string', 'max:50', Rule::unique('discounts', 'code')->ignore($discount->id)],
            'description' => 'nullable|string',
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => 'required|numeric|min:0.01',
            'min_payment_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'usage_limit' => 'nullable|integer|min:1',
            'user_limit' => 'nullable|integer|min:1',
            // branches_id tidak boleh diubah
        ]);
        
        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 1) {
             $validated['discount_value'] = $validated['discount_value'] / 100;
        }

        $discount->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Diskon berhasil diperbarui.',
            'data' => $discount
        ]);
    }

    /**
     * Menghapus diskon.
     */
    public function destroy(Discount $discount)
    {
        if ($discount->branches_id !== auth()->user()->branches_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        // HATI-HATI: Tambahkan pengecekan apakah diskon ini sudah pernah dipakai di Order.
        // Jika sudah pernah dipakai, jangan di-delete, hanya di-nonaktifkan.
        // Asumsi: Tidak perlu dicek relasi Order jika tujuannya hanya master data.

        $discount->delete();

        return response()->json(['success' => true, 'message' => 'Diskon berhasil dihapus.']);
    }

    // File: app/Http/Controllers/DiscountController.php

    // Method penting untuk memvalidasi dan mendapatkan diskon di POS
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'branches_id' => 'required|integer',
            'subtotal' => 'required|numeric',
        ]);

        $currentDate = now()->toDateString(); // Ambil tanggal hari ini (YYYY-MM-DD)

        $discount = Discount::where('code', $request->code)
                            ->where('branches_id', $request->branches_id) // Filter per cabang
                            ->where('is_active', true) // Hanya yang aktif
                            
                            // PERBAIKAN UTAMA 1: Diskon hanya berlaku JIKA start_date <= hari ini
                            ->whereDate('start_date', '<=', $currentDate) 
                            
                            // PERBAIKAN UTAMA 2: Diskon hanya berlaku JIKA end_date >= hari ini (atau NULL)
                            ->where(function ($query) use ($currentDate) {
                                $query->whereNull('end_date') // Berlaku selamanya
                                      ->orWhereDate('end_date', '>=', $currentDate); 
                            })
                        
                            ->first();

        if (!$discount) {
            return response()->json(['message' => 'Kode kupon tidak valid atau belum/sudah kedaluwarsa.'], 404);
        }

        // Cek minimal pembayaran (Sudah ada di logic Anda sebelumnya)
        if ($request->subtotal < $discount->min_payment_amount) {
             return response()->json(['message' => 'Minimal pembayaran belum terpenuhi.'], 400);
        }
        
        // Catatan: Anda mungkin juga perlu mengecek batas penggunaan per user di sini (user_limit)

        return response()->json(['data' => $discount]);
    }
}
