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

        if ($request->has('status_group') && $request->status_group == 'active') {
        $query->whereNotIn('status', ['Check-Out', 'Selesai']);
    }

        $registrations = $query->latest('created_at')->paginate(10);

        return response()->json(['data' => $registrations]);
    }


    public function update(Request $request, $id)
    {
        // Temukan data registrasi pet hotel (mungkin dari model Registration?)
        $registration = Registration::find($id); // Sesuaikan dengan model Anda
        if (!$registration) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        // Validasi input
        $data = $request->validate([
            'status' => 'sometimes|string|in:Check-In,Check-Out,Selesai,Batal',
            'check_out_date' => 'sometimes|date'
        ]);

        // Update data
        $registration->update($data);

        return response()->json([
            'success' => true,
            'data' => $registration
        ]);
    }
}
