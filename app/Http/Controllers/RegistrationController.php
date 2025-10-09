<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule; 

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        // DIUBAH: Tambahkan validasi untuk branches_id
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        // DIUBAH: Filter query berdasarkan branches_id
        $query = Registration::with(['customer', 'pet', 'slot'])
                             ->where('branches_id', $request->branches_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('booking_id', 'like', '%' . $request->search . '%');
        }

        $registrations = $query->latest('id')->paginate(10);
        return response()->json(['data' => $registrations]);
    }

    public function store(Request $request)
    {
        // DIUBAH: Tambahkan validasi untuk branches_id
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'pet_id' => 'required|exists:pets,id',
            'slot_id' => 'nullable|exists:slots,id,status,available',
            'registration_type' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        try {
            DB::beginTransaction();

            $validated['booking_id'] = 'BOOK-' . strtoupper(Str::random(8));
            
            // Data registrasi akan otomatis tersimpan dengan branches_id yang tervalidasi
            $registration = Registration::create($validated);

            if (!empty($validated['slot_id'])) {
                Slot::where('id', $validated['slot_id'])->update(['status' => 'booked']);
            }

            DB::commit();

            return response()->json(['message' => 'Registrasi berhasil dibuat', 'data' => $registration], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCounts(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        $counts = Registration::where('branches_id', $request->branches_id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json(['data' => $counts]);
    }

    public function updateStatus(Request $request, Registration $registration)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['Menunggu Antrian', 'Dalam Tindakan', 'Pembayaran', 'Selesai', 'Batal'])]
        ]);

        $registration->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Status registrasi berhasil diperbarui.', 'data' => $registration]);
    }

    public function getDetailsByIds(Request $request)
    {
        // 1. Validasi request untuk memastikan parameter 'ids' ada
        $request->validate([
            'ids' => 'required|string',
        ]);

        try {
            // 2. Decode string JSON dari parameter 'ids' menjadi array
            $registrationIds = json_decode($request->query('ids'));

            // 3. Pastikan hasil decode adalah array dan tidak kosong
            if (!is_array($registrationIds) || empty($registrationIds)) {
                return response()->json(['message' => 'Invalid or empty IDs provided.'], 400); // Bad Request
            }

            // 4. Ambil data registrasi menggunakan 'whereIn' dan eager load relasi 'service'
            $registrations = Registration::with('service') // Eager load untuk efisiensi
                ->whereIn('id', $registrationIds)
                ->get();

            // 5. Kembalikan data dalam format JSON yang standar
            return response()->json([
                'success' => true,
                'data' => $registrations,
            ]);

        } catch (\Exception $e) {
            // 6. Tangani jika terjadi error (misal: JSON tidak valid)
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching registration details.',
                'error' => $e->getMessage()
            ], 500); // Internal Server Error
        }
    }

}