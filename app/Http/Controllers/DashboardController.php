<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentsOut;
use App\Models\Purchase;
use App\Models\Registration;    
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Mengambil semua statistik yang dibutuhkan untuk halaman dasbor.
     */
    public function getStats(Request $request)
    {
        $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        $branchId = $request->branches_id;
        $now = Carbon::now();

        // 1. KPI Cards (Data Bulan Ini)
        $totalSales = Order::where('branches_id', $branchId)
            ->whereYear('order_date', $now->year)
            ->whereMonth('order_date', $now->month)
            ->sum('total');

        $totalPurchases = Purchase::where('branches_id', $branchId)
            ->whereYear('purchase_date', $now->year)
            ->whereMonth('purchase_date', $now->month)
            ->sum('total_amount');

        $paymentsIn = Payment::where('branches_id', $branchId)
            ->whereYear('payment_date', $now->year)
            ->whereMonth('payment_date', $now->month)
            ->sum('amount');

        $paymentsOut = PaymentsOut::where('branches_id', $branchId)
            ->whereYear('payment_date', $now->year)
            ->whereMonth('payment_date', $now->month)
            ->sum('amount');

        // 2. Grafik Penjualan Layanan Terbaik (Bulan Ini)
        $topServices = Registration::where('branches_id', $branchId)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->select('registration_type', DB::raw('count(*) as total'))
            ->groupBy('registration_type')
            ->orderBy('total', 'desc')
            ->get();

        // 3. Daftar Penjualan Produk Terbaik (Bulan Ini)
        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.branches_id', $branchId)
            ->whereYear('orders.order_date', $now->year)
            ->whereMonth('orders.order_date', $now->month)
            ->select('products.name', DB::raw('SUM(order_items.subtotal) as total_sales'))
            ->groupBy('products.name')
            ->orderBy('total_sales', 'desc')
            ->limit(5)
            ->get();
            
        // 4. Data untuk Grafik Bar Penjualan vs Pembelian (6 Bulan Terakhir)
        $monthlyReport = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('F'); // e.g., "September"

            $sales = Order::where('branches_id', $branchId)
                ->whereYear('order_date', $date->year)
                ->whereMonth('order_date', $date->month)
                ->sum('total');

            $purchases = Purchase::where('branches_id', $branchId)
                ->whereYear('purchase_date', $date->year)
                ->whereMonth('purchase_date', $date->month)
                ->sum('total_amount');

            $monthlyReport[] = [
                'month' => $monthName,
                'sales' => $sales,
                'purchases' => $purchases,
            ];
        }

        return response()->json([
            'data' => [
                'kpi' => [
                    'total_sales' => $totalSales,
                    'total_purchases' => $totalPurchases,
                    'payments_in' => $paymentsIn,
                    'payments_out' => $paymentsOut,
                ],
                'top_services' => $topServices,
                'top_products' => $topProducts,
                'monthly_report' => $monthlyReport,
            ]
        ]);
    }
}

