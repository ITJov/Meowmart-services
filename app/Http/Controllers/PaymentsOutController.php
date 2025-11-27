<?php

namespace App\Http\Controllers;

// DIUBAH: Menggunakan model PaymentOut (singular)
use App\Models\PaymentsOut;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentsOutController extends Controller
{
    /**
     * Menampilkan daftar pembayaran keluar untuk cabang yang aktif.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search' => 'nullable|string',
        ]);

        $query = PaymentsOut::with('user', 'purchase') // Muat relasi user & purchase
                           ->where('branches_id', $request->branches_id);

        if ($request->filled('search')) {
            $query->where('transaction_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('purchase', function($q) use ($request) {
                      $q->where('invoice_number', 'like', '%' . $request->search . '%');
                  });
        }

        $payments = $query->latest('payment_date')->paginate(10);

        return response()->json(['data' => $payments]);
    }

    /**
     * Menyimpan data pembayaran keluar baru.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'purchase_id' => 'nullable|integer|exists:purchases,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Tambahkan data yang di-generate otomatis oleh server
        $validatedData['user_id'] = $request->user()->id;
        $validatedData['transaction_number'] = 'PAY-OUT-' . strtoupper(Str::random(12));

        $paymentOut = PaymentsOut::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran keluar berhasil disimpan.',
            'data' => $paymentOut
        ], 201);
    }

    /**
     * Menampilkan detail satu pembayaran keluar.
     */
    public function show(PaymentsOut $paymentOut)
    {
        // Memuat relasi untuk ditampilkan di halaman detail
        $paymentOut->load(['user', 'purchase']);
        return response()->json(['data' => $paymentOut]);
    }

    /**
     * Memperbarui data pembayaran keluar.
     */
    public function update(Request $request, PaymentsOut $paymentOut)
    {
        $validatedData = $request->validate([
            'purchase_id' => 'nullable|integer|exists:purchases,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $paymentOut->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran keluar berhasil diperbarui.',
            'data' => $paymentOut
        ]);
    }

    /**
     * Menghapus data pembayaran keluar.
     */
    public function destroy(PaymentsOut $paymentOut)
    {
        // Opsi keamanan: Pastikan user hanya bisa menghapus data di cabangnya
        // if ($paymentOut->branches_id !== auth()->user()->branches_id) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $paymentOut->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran keluar berhasil dihapus.'
        ]);
    }
}

