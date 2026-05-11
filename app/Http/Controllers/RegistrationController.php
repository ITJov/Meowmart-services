<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Slot;
use App\Models\Kandang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule; 

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'registration_type' => 'nullable|string', 
        ]);

        $query = Registration::with(['customer', 'pet', 'slot', 'service'])
                             ->where('branches_id', $request->branches_id);

        if ($request->filled('registration_type')) {
            $query->where('registration_type', $request->registration_type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('exclude_statuses') && is_array($request->exclude_statuses)) {
            $query->whereNotIn('status', $request->exclude_statuses);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('booking_id', 'like', '%' . $search . '%')
                  ->orWhereHas('customer', function($c) use ($search) {
                      $c->where('name', 'like', '%' . $search . '%');
                  });
            });
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
            'kandang_id' => 'nullable|integer|exists:kandangs,id', 
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
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

    /**
 * Menampilkan detail satu data registrasi.
 */
    public function show(Registration $registration)
    {
        // Muat relasi lengkap agar frontend bisa menampilkan nama customer, hewan, dan jasa
        return response()->json([
            'success' => true,
            'data' => $registration->load(['customer', 'pet', 'service', 'kandang'])
        ]);
    }
    // ------------------------------------------------------------------

    public function Counts(Request $request)
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

    // ------------------------------------------------------------------

    public function updateStatus(Request $request, Registration $registration)
    {
        $validated = $request->validate([
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