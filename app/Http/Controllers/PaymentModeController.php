<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMode;

class PaymentModeController extends Controller
{
    public function index()
    {
        // Ambil semua data PaymentMode
        $paymentModes = PaymentMode::all(['id', 'name']);

        // Mengembalikan dalam format standar yang diharapkan frontend (data: [...])
        return response()->json(['success' => true, 'data' => $paymentModes]);
    }
}
