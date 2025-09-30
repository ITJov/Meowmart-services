<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use Illuminate\Http\Request;

class PetHotelController extends Controller
{
    /**
     * Menampilkan daftar hewan yang sedang berada di Pet Hotel
     * untuk cabang yang aktif.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
        ]);

        $query = Registration::with(['customer', 'pet'])
            ->where('branches_id', $request->branches_id)
            ->where('registration_type', 'Pet Hotel')
            // Opsi: Anda bisa tambahkan filter status, misal hanya yang 'Check-in'
            // ->where('status', 'Check-in') 
            ;

        if ($request->filled('search')) {
            // Pencarian bisa berdasarkan nama hewan atau nama customer
            $query->where(function($q) use ($request) {
                $q->whereHas('pet', function ($subq) use ($request) {
                    $subq->where('name', 'like', '%' . $request->search . '%');
                })->orWhereHas('customer', function ($subq) use ($request) {
                    $subq->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        $registrations = $query->latest('created_at')->paginate(10);

        return response()->json(['data' => $registrations]);
    }
}
