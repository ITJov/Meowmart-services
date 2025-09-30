<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Branch; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Menampilkan daftar pelanggan berdasarkan cabang yang aktif.
     * Mengasumsikan Global Scope sudah diimplementasikan untuk filter otomatis.
     */
    public function index(Request $request)
{
    $request->validate([
        'branches_id' => 'required|integer|exists:branches,id',
        'search' => 'nullable|string',
    ]);
    
    $query = Customer::query()->where('branches_id', $request->branches_id);

    if ($request->filled('search')) {
        $query->where(function($q) use ($request) {
            $q->where('name', 'like', '%' . $request->search . '%')
              ->orWhere('email', 'like', '%' . $request->search . '%');
        });
    }

    $customers = $query->latest()->paginate(15);
    
    return response()->json(['data' => $customers]);
}

    /**
     * Menyimpan data pelanggan baru untuk cabang yang aktif.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email'],
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'branches_id' => 'required|integer|exists:branches,id', 
        ]);
        
        // Pastikan email unik per cabang
        $request->validate([
            // DIUBAH: Gunakan 'branches_id' agar konsisten dengan array $validatedData
            'email' => [Rule::unique('customers')->where('branches_id', $validatedData['branches_id'])],
        ]);

        $customer = Customer::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
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
        // Asumsi: Global scope atau policy akan membatasi akses ke customer di cabang yang salah
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
            'email' => ['required', 'email', Rule::unique('customers')->where('branches_id', $customer->branches_id)->ignore($customer->id)],
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