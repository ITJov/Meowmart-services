<?php

namespace App\Http\Controllers; 

use App\Models\Kandang;
use App\Models\User;
use App\Models\Registration;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule; 

class KandangController extends Controller
{
    /**
     * Menampilkan daftar kandang dengan paginasi, search, dan filter.
     */
    public function index(Request $request)
    {
            $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:Aktif,Tidak Aktif,Perbaikan',
        ]);

        $query = Kandang::query()
                    ->where('branches_id', $request->branches_id)
                    ->where('flag_deleted', false);

        if ($request->filled('search')) {
            $query->where('kode_room', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $kandangs = $query->latest()->paginate(15);

        return response()->json(['data' => $kandangs]);
    }

    /**
     * Menyimpan kandang baru ke cabang yang ditentukan.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'kode_room' => ['required', 'string', Rule::unique('kandangs')->where('branches_id', $request->branches_id)],
            'quota' => 'required|integer|min:1',
            'status' => 'required|string',
        ]);
        
        // Data kandang akan dibuat dengan branches_id yang sudah divalidasi
        $kandang = Kandang::create($validatedData);

        return response()->json(['success' => true, 'message' => 'Kandang created', 'data' => $kandang], 201);
    }

    /**
     * Memperbarui data kandang yang ada.
     */
    public function update(Request $request, Kandang $kandang)
    {
        $validatedData = $request->validate([
            'kode_room' => [
                'required',
                'string',
                Rule::unique('kandangs')->where('branches_id', $kandang->branches_id)->ignore($kandang->id),
            ],
            'quota' => 'required|integer|min:1',
            'status' => 'required|string|in:Aktif,Tidak Aktif,Perbaikan',
        ]);

        $kandang->update($validatedData);

        return response()->json(['success' => true, 'message' => 'Kandang updated successfully', 'data' => $kandang]);
    }

    /**
     * Menghapus kandang (soft delete).
     */
    public function destroy(Kandang $kandang)
    {
        $kandang->update([
            'status' => 'Tidak Aktif',
            'flag_deleted' => true
        ]);

        return response()->json(['success' => true, 'message' => 'Kandang soft deleted successfully']);
    }

    /**
     * Fungsi untuk autocomplete/pencarian dropdown.
     */
    public function find(Request $request)
    {
        $request->validate(['searchTerm' => 'nullable|string']);
        $term = $request->searchTerm;

        $query = Kandang::query()
                    ->where('flag_deleted', false)
                    ->where('status', 'Aktif');

        if ($term) {
            $query->where('kode_room', 'like', '%' . $term . '%');
        }

        $kandangs = $query->limit(10)->get();

        $formatted_kandangs = $kandangs->map(function ($kandang) {
            return ['id' => $kandang->id, 'text' => $kandang->kode_room . ' (Kuota: ' . $kandang->quota . ')'];
        });

        return response()->json($formatted_kandangs);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);
        
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $totalQuota = Kandang::where('branches_id', $request->branches_id)
                             ->where('flag_deleted', false)
                             ->where('status', 'Aktif')
                             ->sum('quota');

        if ($totalQuota === 0) {
            return response()->json(['available' => 0, 'message' => 'Tidak ada kandang aktif yang tersedia.'], 200);
        }

        $bookedCount = Registration::where('branches_id', $request->branches_id)
            ->where('status', '!=', 'Batal') // Jangan hitung yang batal
            ->where('status', '!=', 'Selesai') // Jangan hitung yang sudah selesai/check-out
            
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereDate('start_date', '<=', $endDate)
                  ->whereDate('end_date', '>=', $startDate);
            })
            ->count();
            
        $availableQuota = $totalQuota - $bookedCount;

        return response()->json([
            'available' => max(0, $availableQuota),
            'message' => 'Kuota kandang tersedia.',
            'total_quota' => $totalQuota,
            'booked_count' => $bookedCount
        ], status: 200);
    }

        public function getActiveList(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
        ]);
        
        $kandangs = Kandang::where('branches_id', $request->branches_id)
            ->where('flag_deleted', false)
            ->where('status', 'Aktif')
            ->select('id', 'kode_room as name', 'quota')
            ->get();

        return response()->json(['success' => true, 'data' => $kandangs]);
    }
}