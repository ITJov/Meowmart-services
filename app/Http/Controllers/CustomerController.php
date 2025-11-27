<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Branch; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB; // Tambahkan untuk kemudahan debugging

class CustomerController extends Controller
{
    /**
     * Menampilkan daftar pelanggan berdasarkan cabang yang aktif.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
        ]);
        
        $query = Customer::query()->where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm)
                  ->orWhere('phone', 'like', $searchTerm); // Tambah pencarian via HP
            });
        }

        $customers = $query->latest()->paginate(15);
        
        return response()->json(['data' => $customers]);
    }

    /**
     * Menyimpan data pelanggan baru ke cabang yang aktif.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email'],
            
            // Perbaikan Validasi Email: Cek email unik per cabang, abaikan data ini sendiri
            'email' => [
                 'required', 'email', 
                 Rule::unique('customers')->where('branches_id', $request->branches_id)
            ],
            
            // Password sekarang opsional dari frontend
            'password' => 'nullable|string|min:8', 
            
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'branches_id' => 'required|integer|exists:branches,id', 
        ]);
        
        // --- LOGIKA PERBAIKAN PASSWORD ---
        // Jika password tidak dikirim (atau dikirim tapi kosong dari frontend), 
        // berikan password default (misal: nomor telepon pelanggan)
        $passwordToHash = $validatedData['password'] ?? $validatedData['phone'] ?? 'password123';

        $customer = Customer::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            // Hash password default jika tidak ada
            'password' => Hash::make($passwordToHash), 
            
            'phone' => $validatedData['phone'] ?? null,
            'address' => $validatedData['address'] ?? null,
            'branches_id' => $validatedData['branches_id'], 
        ]);

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil dibuat', 'data' => $customer], 201);
    }
    
    /**
     * Menampilkan detail satu pelanggan.
     */
    public function show(Customer $customer)
    {
        $customer->load('pets');
        return response()->json(['success' => true, 'data' => $customer]);
    }

    /**
     * Memperbarui data pelanggan.
     */
    public function update(Request $request, Customer $customer) // <-- Gunakan model Customer
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            
            // Perbaikan Validasi Email Update: Abaikan ID pelanggan yang sedang di-edit
            'email' => ['required', 'email', Rule::unique('customers')->where('branches_id', $customer->branches_id)->ignore($customer->id)],
            
            // Password tidak perlu divalidasi di sini karena frontend tidak mengirimnya kecuali ada perubahan
            // Jika Anda ingin mengubah password, Anda harus mengirimkan field 'new_password' terpisah.
            
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $customer->update($validatedData);

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil diperbarui', 'data' => $customer]);
    }

    /**
     * Menghapus data pelanggan.
     */
    public function destroy(Customer $customer) // <-- Gunakan model Customer
    {
        $customer->delete();

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil dihapus']);
    }
    
    /**
     * Mengambil data hewan peliharaan milik seorang pelanggan.
     */
     public function getPetsByCustomer(Customer $customer)
    {
        return response()->json(['data' => $customer->pets]);
    }
}