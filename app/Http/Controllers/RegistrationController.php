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
        $query = Registration::with(['customer', 'pet', 'slot', 'service'])
                             ->where('branches_id', $request->branches_id);

        if ($request->filled('status')) {
            // Kita hanya memfilter berdasarkan status yang ada di tab: Terjadwal, Selesai, Batal
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('booking_id', 'like', '%' . $request->search . '%');
        }

        $registrations = $query->latest('id')->paginate(10);
        return response()->json(['data' => $registrations]);
    }

    // ------------------------------------------------------------------

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'pet_id' => 'required|exists:pets,id',
            'slot_id' => 'nullable|exists:slots,id,status,available',
            
            'service_id' => 'required|integer|exists:services,id', 
            'registration_type' => 'required|string|max:255', 
            
            'status' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        try {
            DB::beginTransaction();

            $validated['booking_id'] = 'BOOK-' . strtoupper(Str::random(8));
            
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

    // ------------------------------------------------------------------

    public function getCounts(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        // NOTE: Count harus menghitung semua status yang ada di database,
        // meskipun tab di frontend tidak menampilkannya (misal 'Menunggu Pembayaran').
        $counts = Registration::where('branches_id', $request->branches_id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json(['data' => $counts]);
    }

    // ------------------------------------------------------------------

    public function updateStatus(Request $request, Registration $registration)
    {
        $validated = $request->validate([
            // PERBAIKAN UTAMA: Hanya status yang visible atau final yang diizinkan dari UI ini.
            'status' => ['required', 'string', Rule::in(['Selesai', 'Batal'])]
        ]);

        $registration->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Status registrasi berhasil diperbarui.', 'data' => $registration]);
    }

    // ------------------------------------------------------------------

    public function getDetailsByIds(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
        ]);

        try {
            $registrationIds = json_decode($request->query('ids'));

            if (!is_array($registrationIds) || empty($registrationIds)) {
                return response()->json(['message' => 'Invalid or empty IDs provided.'], 400); 
            }

            $registrations = Registration::with('service')
                ->whereIn('id', $registrationIds)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $registrations,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching registration details.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}