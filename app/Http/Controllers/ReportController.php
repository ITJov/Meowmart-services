<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;  
use App\Models\Purchase;  
use App\Models\Payment;  
use App\Models\PaymentsOut;  
use App\Models\ProductDetail; 
use App\Models\Product;  
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel; 
use App\Exports\ProfitLossExport;

class ReportController extends Controller
{
    /**
     * Mengambil data untuk laporan Untung & Rugi.
     */
    public function getProfitLoss(Request $request)
    {
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $branchId = $data['branches_id']; 

        $totalRevenue = Order::where('payment_status', 'paid') 
                               ->where('branches_id', $branchId) 
                               ->whereBetween('order_date', [$startDate, $endDate])
                               ->sum('total'); 
                               
        $totalCOGS = Purchase::where('purchase_status', 'Received') 
                             ->where('branches_id', $branchId) // <-- PERBAIKAN 3
                             ->whereBetween('purchase_date', [$startDate, $endDate])
                             ->sum('total_amount');

        $totalExpenses = PaymentsOut::whereNull('purchase_id') 
                                   ->where('branches_id', $branchId) // <-- PERBAIKAN 4
                                   ->whereBetween('payment_date', [$startDate, $endDate])
                                   ->sum('amount');
        
        $totalCashIn = Payment::whereHas('order', function ($query) use ($branchId) {
                                    $query->where('branches_id', $branchId); // <-- PERBAIKAN 5
                                })
                              ->whereBetween('payment_date', [$startDate, $endDate])
                              ->sum('amount');
                              
        $totalCashOut = PaymentsOut::where('branches_id', $branchId) 
                                  ->whereBetween('payment_date', [$startDate, $endDate])
                                  ->sum('amount');

        $grossProfit = $totalRevenue - $totalCOGS;
        $netProfit = $grossProfit - $totalExpenses;
        $netCashFlow = $totalCashIn - $totalCashOut;

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue'   => $totalRevenue,
                'total_cogs'      => $totalCOGS,
                'gross_profit'    => $grossProfit,
                'total_expenses'  => $totalExpenses,
                'net_profit'      => $netProfit,
                'total_cash_in'   => $totalCashIn,
                'total_cash_out'  => $totalCashOut,
                'net_cash_flow'   => $netCashFlow,
                'start_date'      => $startDate->toFormattedDateString(),
                'end_date'        => $endDate->toFormattedDateString(),
            ]
        ]);
    }

    public function getPaymentReport(Request $request)
    {
        // 1. Validasi input
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $branchId = $data['branches_id'];
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $perPage = $request->input('per_page', 10);

        // 2. Query data pembayaran
        $query = Payment::query()
            // Ambil relasi yang kita butuhkan
            ->with([
                'paymentMode:id,name', // (Metode Pembayaran)
                'order:id,invoice_number,customer_id,branches_id', // (No. Ref & Customer ID)
                'order.customer:id,name,phone' // (Nama Pelanggan & No. HP)
            ])
            // Filter utama: berdasarkan tanggal pembayaran
            ->whereBetween('payment_date', [$startDate, $endDate])
            // Filter utama: berdasarkan cabang (melalui relasi 'order')
            ->whereHas('order', function ($q) use ($branchId) {
                $q->where('branches_id', $branchId);
            });

        // 3. Terapkan filter pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Cari di invoice number
                $q->whereHas('order', function($sq) use ($search) {
                    $sq->where('invoice_number', 'like', "%{$search}%");
                })
                // Atau cari di nama pelanggan
                ->orWhereHas('order.customer', function($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        // 4. Ambil hasil dengan paginasi
        $payments = $query->latest('payment_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    public function getMinimumStockReport(Request $request)
    {
        // 1. Validasi: HANYA butuh branches_id, search, per_page
        $data = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $branchId = $data['branches_id'];
        $perPage = $request->input('per_page', 10);

        // 2. Query (TIDAK PERLU 'whereBetween' TANGGAL)
        $query = ProductDetail::query()
            ->with(['product:id,name']) // Ambil relasi nama produk
            ->where('branches_id', $branchId)
            // INI LOGIKA UTAMANYA: Stok saat ini <= batas pengingat
            ->whereRaw('current_stock <= stock_alert')
            // Dan pastikan stock_alert tidak 0 atau null
            ->where('stock_alert', '>', 0);

        // 3. Filter Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // 4. Paginasi
        $stockAlerts = $query->latest('updated_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $stockAlerts
        ]);
    }

    public function getSalesReport(Request $request)
    {
        // 1. Validasi input
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $branchId = $data['branches_id'];
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $perPage = $request->input('per_page', 10);

        // 2. Query data order
        $query = Order::query()
            // Ambil relasi yang kita butuhkan
            ->with(['customer:id,name']) // (Nama Pelanggan)
            // Filter utama: berdasarkan cabang
            ->where('branches_id', $branchId)
            // Filter utama: berdasarkan tanggal order
            ->whereBetween('order_date', [$startDate, $endDate]);

        // 3. Terapkan filter pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Cari di invoice number
                $q->where('invoice_number', 'like', "%{$search}%")
                // Atau cari di nama pelanggan
                ->orWhereHas('customer', function($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        // 4. Ambil hasil dengan paginasi
        $sales = $query->latest('order_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    public function getSalesByProductReport(Request $request)
    {
        // 1. Validasi input
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $branchId = $data['branches_id'];
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $perPage = $request->input('per_page', 10);

        // 2. Query data produk
        $query = Product::query()
            // Ambil relasi unit (untuk kolom 'Satuan')
            ->with('unit:id,name,short_name')
            // Hitung total kuantitas terjual menggunakan 'withSum'
            ->withSum([
                // Nama relasi di model Product: 'orderItems'
                // Alias untuk kolom hasil: 'total_quantity_sold'
                'orderItems as total_quantity_sold' => function ($query) use ($branchId, $startDate, $endDate) {
                    // Filter sum HANYA berdasarkan order yang sesuai
                    $query->whereHas('order', function ($q) use ($branchId, $startDate, $endDate) {
                        $q->where('branches_id', $branchId)
                          ->whereBetween('order_date', [$startDate, $endDate]);
                    });
                }
            ], 'quantity') // Kolom yang ingin di-SUM
            
            // 3. Filter: Hanya tampilkan produk yang pernah terjual
            ->having('total_quantity_sold', '>', 0);

        // 4. Terapkan filter pencarian (berdasarkan nama produk)
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // 5. Urutkan berdasarkan yang terlaris & paginasi
        $products = $query->orderBy('total_quantity_sold', 'desc')
                          ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function getStockRecapReport(Request $request)
    {
        // 1. Validasi input
        $data = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $branchId = $data['branches_id'];
        $perPage = $request->input('per_page', 10);

        // 2. Query data dari ProductDetail (karena stok ada di sana)
        $query = ProductDetail::query()
            // Ambil relasi yang kita butuhkan
            ->with([
                // product:id,name,SKU,category_id,brand_id
                'product:id,name,item_code,category_id,brand_id,unit_id', 
                'product.category:id,name', // Kategori
                'product.brand:id,name',    // Merek
                'product.unit:id,name'      // Satuan
            ])
            // Filter utama: berdasarkan cabang
            ->where('branches_id', $branchId);

        // 3. Terapkan filter pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                // Cari di nama produk, SKU, kategori, atau brand
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhereHas('category', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('brand', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        // 4. Ambil hasil dengan paginasi
        $stockRecap = $query->latest('updated_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $stockRecap
        ]);
    }

    public function downloadProfitLoss(Request $request)
    {
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id',
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $branchId = $data['branches_id'];

        // --- Logika Perhitungan (SAMA seperti di getProfitLoss) ---
        $totalRevenue = Order::where('payment_status', 'paid')
                               ->where('branches_id', $branchId)
                               ->whereBetween('order_date', [$startDate, $endDate])
                               ->sum('total');

        $totalCOGS = Purchase::where('purchase_status', 'Received')
                             ->where('branches_id', $branchId)
                             ->whereBetween('purchase_date', [$startDate, $endDate])
                             ->sum('total_amount');

        $totalExpenses = PaymentsOut::whereNull('purchase_id')
                                   ->where('branches_id', $branchId)
                                   ->whereBetween('payment_date', [$startDate, $endDate])
                                   ->sum('amount');

        $grossProfit = $totalRevenue - $totalCOGS;
        $netProfit = $grossProfit - $totalExpenses;
        // Kita hanya perlu data P&L untuk laporan ini, Cash Flow bisa diabaikan atau ditambahkan jika perlu.

        $reportData = [
            'total_revenue'  => $totalRevenue,
            'total_cogs'     => $totalCOGS,
            'gross_profit'   => $grossProfit,
            'total_expenses' => $totalExpenses,
            'net_profit'     => $netProfit,
            'start_date'     => $startDate->format('Y-m-d'),
            'end_date'       => $endDate->format('Y-m-d'),
            // Tambahkan data Cash Flow jika ingin ditampilkan di Excel
        ];
        // --- Akhir Logika Perhitungan ---

        $filename = 'Laporan_Untung_Rugi_' . $reportData['start_date'] . '_sd_' . $reportData['end_date'] . '.xlsx';

        // Kembalikan file Excel menggunakan Maatwebsite/Excel
        return Excel::download(new ProfitLossExport($reportData), $filename);
    }
}