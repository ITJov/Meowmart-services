<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;  
use App\Models\Purchase;  
use App\Models\Payment;  
use App\Models\PaymentsOut;  
use App\Models\ProductDetail;
use App\Models\Branch;
use App\Models\Product;  
use App\Models\ProductBatches;  
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProfitLossExport;
use App\Exports\PaymentReportExport;
use App\Exports\MinimumStockExport;
use App\Exports\SalesReportExport;
use App\Exports\SalesByProductReportExport;

class ReportController extends Controller
{
    /**
     * Mengambil data untuk laporan Untung & Rugi. (Mendukung Semua Cabang)
     */
    public function getProfitLoss(Request $request)
    {
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'nullable|integer', 
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $branchId = (int) $data['branches_id'];
        $filterByBranch = $branchId > 0;

        // =======================================================
        // 1. PERHITUNGAN TOTAL GLOBAL (Untuk Ringkasan Utama)
        // =======================================================

        // 1A. Total Pendapatan (Revenue)
        $totalRevenue = Order::where('payment_status', 'paid')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->when($filterByBranch, fn ($query) => $query->where('branches_id', $branchId))
            ->sum('total');
                                    
        // 1B. Harga Pokok Penjualan (COGS)
        $totalCOGS = Purchase::where('purchase_status', 'Received')
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->when($filterByBranch, fn ($query) => $query->where('branches_id', $branchId))
            ->sum('total_amount');

        // 1C. Beban Operasional Lainnya (Expenses) - Non-Purchase
        $totalExpenses = PaymentsOut::whereNull('purchase_id')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->when($filterByBranch, fn ($query) => $query->where('branches_id', $branchId))
            ->sum('amount');
        
        // 1D. Total Uang Masuk (Cash In)
        $totalCashIn = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('order', function ($query) use ($filterByBranch, $branchId) {
                if ($filterByBranch) {
                    $query->where('branches_id', $branchId);
                }
            })
            ->sum('amount');
                                    
        // 1E. Total Uang Keluar (Cash Out)
        $totalCashOut = PaymentsOut::whereBetween('payment_date', [$startDate, $endDate])
            ->when($filterByBranch, fn ($query) => $query->where('branches_id', $branchId))
            ->sum('amount');

        // 1F. Hitung Profit/Flow
        $grossProfit = $totalRevenue - $totalCOGS;
        $netProfit = $grossProfit - $totalExpenses; // NILAI INI YANG HARUS KONSISTEN!
        $netCashFlow = $totalCashIn - $totalCashOut;
        
        // =======================================================
        // 2. PERHITUNGAN DETAIL PER CABANG (Hanya jika Semua Cabang)
        // =======================================================
        
        $branchDetails = [];

        if (!$filterByBranch) {
            // Ambil semua Cabang (Branch IDs) yang terlibat dalam transaksi selama periode ini
            $involvedBranchIds = collect(DB::select("
                SELECT branches_id FROM orders WHERE payment_status = 'paid' AND order_date BETWEEN ? AND ?
                UNION
                SELECT branches_id FROM purchases WHERE purchase_status = 'Received' AND purchase_date BETWEEN ? AND ?
                UNION
                SELECT branches_id FROM payments_out WHERE payment_date BETWEEN ? AND ?
            ", [
                $startDate, $endDate, 
                $startDate, $endDate, 
                $startDate, $endDate
            ]))->pluck('branches_id')->unique()->filter();

            $allBranches = Branch::whereIn('id', $involvedBranchIds)->get(['id', 'name'])->keyBy('id');
            
            // Query Agregasi Per Cabang
            // Revenue
            $revenueByBranch = Order::where('payment_status', 'paid')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->whereIn('branches_id', $involvedBranchIds)
                ->groupBy('branches_id')
                ->selectRaw('branches_id, SUM(total) as revenue')
                ->pluck('revenue', 'branches_id');
                
            // COGS
            $cogsByBranch = Purchase::where('purchase_status', 'Received')
                ->whereBetween('purchase_date', [$startDate, $endDate])
                ->whereIn('branches_id', $involvedBranchIds)
                ->groupBy('branches_id')
                ->selectRaw('branches_id, SUM(total_amount) as cogs')
                ->pluck('cogs', 'branches_id');

            // Expenses (Non-Purchase)
            $expensesByBranch = PaymentsOut::whereNull('purchase_id')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->whereIn('branches_id', $involvedBranchIds)
                ->groupBy('branches_id')
                ->selectRaw('branches_id, SUM(amount) as expenses')
                ->pluck('expenses', 'branches_id');
                
            // Gabungkan dan Hitung Profit per Cabang
            foreach ($allBranches as $id => $branch) {
                $rev = $revenueByBranch[$id] ?? 0;
                $cogs = $cogsByBranch[$id] ?? 0;
                $exp = $expensesByBranch[$id] ?? 0;
                
                $grossProfitBranch = $rev - $cogs;
                $netProfitBranch = $grossProfitBranch - $exp;
                
                $branchDetails[] = [
                    'id'         => $id,
                    'name'       => $branch->name,
                    'revenue'    => (float) $rev,
                    'cogs'       => (float) $cogs,
                    'expenses'   => (float) $exp,
                    'net_profit' => (float) $netProfitBranch,
                ];
            }
        }
        
        // =======================================================
        // 3. RETURN DATA
        // =======================================================

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue'   => $totalRevenue,
                'total_cogs'      => $totalCOGS,
                'gross_profit'    => $grossProfit,
                'total_expenses'  => $totalExpenses,
                'net_profit'      => $netProfit, // Konsisten dengan perhitungan 1F
                'total_cash_in'   => $totalCashIn,
                'total_cash_out'  => $totalCashOut,
                'net_cash_flow'   => $netCashFlow,
                'start_date'      => $startDate->toFormattedDateString(),
                'end_date'        => $endDate->toFormattedDateString(),
                'report_for_all_branches' => !$filterByBranch,
                'branch_details'  => $branchDetails, // Data baru untuk tabel detail
            ]
        ]);
    }

    public function getPaymentReport(Request $request)
    {
        // 1. Validasi input
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id', // MASIH WAJIB CABANG TERTENTU
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
            // Filter utama: berdasarkan cabang
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
        // 1. Modifikasi Validasi: branches_id diubah menjadi nullable/opsional
        $data = $request->validate([
            'branches_id' => 'nullable|integer', // Diubah menjadi nullable
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $branchId = (int) $data['branches_id'];
        $perPage = $request->input('per_page', 10);
        $filterByBranch = $branchId > 0;

        // 2. Query dari ProductDetail
        $query = ProductDetail::query()
            ->with(['product:id,name'])
            // Muat relasi cabang HANYA jika mode semua cabang
            ->when(!$filterByBranch, function($q) {
                $q->with('branch:id,name'); // Asumsi ada relasi 'branch' di ProductDetail
            })
            
            // Terapkan filter cabang HANYA jika $branchId > 0
            ->when($filterByBranch, function($q) use ($branchId) {
                $q->where('branches_id', $branchId);
            })
            
            ->where(function($q) {
                $q->where('current_stock', 0)
                    ->orWhere(function($subQ) {
                        $subQ->whereNotNull('stock_alert') 
                            ->whereRaw('current_stock <= stock_alert');
                    });
            });

        // 3. Filter Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // 4. Paginasi
        $stockAlerts = $query->orderBy('current_stock', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $stockAlerts
        ]);
    }

    public function getSalesReport(Request $request)
    {
        // 1. Validasi input: branches_id diubah menjadi nullable
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'nullable|integer', // Ubah dari required ke nullable
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $branchId = $data['branches_id']; 
        $perPage = $request->input('per_page', 10);

        // 2. Query data order
        $query = Order::query()
            // Muat relasi customer dan relasi branch (untuk menampilkan nama cabang di tabel global)
            ->with(['customer:id,name', 'branch:id,name']) 
            ->whereBetween('order_date', [$startDate, $endDate])
            // Gunakan when: filter branches_id HANYA jika nilainya > 0
            ->when($branchId > 0, function ($q) use ($branchId) {
                return $q->where('branches_id', $branchId);
            });

        // 3. Terapkan filter pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
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
        // Gunakan 'nullable' agar validasi tidak error jika branches_id tidak dikirim atau null
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'nullable', // Pastikan ini nullable
            'search'      => 'nullable|string',
            'per_page'    => 'nullable|integer',
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $perPage = $request->input('per_page', 10);
        
        // Gunakan null coalescing (??) untuk menghindari error "Undefined array key"
        $branchId = $data['branches_id'] ?? null;

        $query = Product::query()
            ->with('unit:id,name,short_name')
            ->withSum([
                'orderItems as total_quantity_sold' => function ($query) use ($branchId, $startDate, $endDate) {
                    $query->whereHas('order', function ($q) use ($branchId, $startDate, $endDate) {
                        // Hanya filter cabang jika ID cabang ada dan lebih besar dari 0
                        if ($branchId && $branchId > 0) {
                            $q->where('branches_id', $branchId);
                        }
                        $q->whereBetween('order_date', [$startDate, $endDate]);
                    });
                }
            ], 'quantity')
            ->having('total_quantity_sold', '>', 0);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('total_quantity_sold', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function getStockRecapReport(Request $request)
{
    // 1. Validasi input dari frontend
    $data = $request->validate([
        'branches_id'    => 'nullable|integer',
        'search'         => 'nullable|string',
        'per_page'       => 'nullable|integer',
        'low_stock_only' => 'nullable|boolean',
        'expiring_soon'  => 'nullable|boolean', // Parameter filter expired
    ]);

    $branchId = (int) ($data['branches_id'] ?? 0);
    $perPage = $request->input('per_page', 10);
    $lowStockOnly = $request->boolean('low_stock_only');
    $expiringSoon = $request->boolean('expiring_soon');

    // 2. Query dasar dari ProductDetail
    $query = ProductDetail::query()
        ->with([
            'product:id,name,item_code,category_id,brand_id,unit_id', 
            'product.category:id,name', 
            'product.brand:id,name',    
            'product.unit:id,name',
            'product.batches' => function($q) use ($branchId) {
                // Hanya ambil batch yang masih ada stoknya
                $q->where('quantity', '>', 0)
                  ->when($branchId > 0, function($bq) use ($branchId) {
                      $bq->where('branches_id', $branchId);
                  })
                  ->orderBy('expiry_date', 'asc');
            },
            'branch:id,name' 
        ])
        ->when($branchId > 0, function($q) use ($branchId) {
            $q->where('branches_id', $branchId);
        });

    // 3. Filter Khusus: Stok Minim
    $query->when($lowStockOnly, function($q) {
        $q->where(function($sub) {
            $sub->where('current_stock', 0)
                ->orWhereRaw('current_stock <= stock_alert');
        });
    });

    // 4. Filter Khusus: Hampir Kadaluwarsa (30 Hari)
    // Menggunakan relasi ke model ProductBatches
    $query->when($expiringSoon, function($q) use ($branchId) {
        $q->whereHas('product.batches', function($batchQuery) use ($branchId) {
            $batchQuery->where('quantity', '>', 0)
                ->whereBetween('expiry_date', [
                    now()->startOfDay(), 
                    now()->addDays(30)->endOfDay()
                ])
                ->when($branchId > 0, function($bq) use ($branchId) {
                    $bq->where('branches_id', $branchId);
                });
        });
    });

    // 5. Fitur Pencarian
    if ($request->filled('search')) {
        $search = $request->search;
        $query->whereHas('product', function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('item_code', 'like', "%{$search}%");
        });
    }

    // 6. Eksekusi Pagination
    $stockRecap = $query->latest('updated_at')->paginate($perPage);

    // 7. Transformasi Data (Opsional: menyederhanakan expiry_date terdekat untuk table)
    $stockRecap->getCollection()->transform(function ($item) {
        $item->nearest_expiry = $item->product->batches->first()->expiry_date ?? null;
        return $item;
    });

    return response()->json([
        'success' => true,
        'data' => $stockRecap
    ]);
}

    /**
     * Mengunduh data laporan Untung & Rugi. (Mendukung Semua Cabang)
     */
    public function downloadProfitLoss(Request $request)
    {
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            // Diubah menjadi nullable/opsional untuk menerima 0
            'branches_id' => 'nullable|integer',
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $branchId = (int) $data['branches_id'];
        $filterByBranch = $branchId > 0;

        // 1. Total Pendapatan (Revenue)
        $totalRevenue = Order::where('payment_status', 'paid')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->when($filterByBranch, function ($query) use ($branchId) {
                return $query->where('branches_id', $branchId);
            })
            ->sum('total');

        // 2. Harga Pokok Penjualan (COGS)
        $totalCOGS = Purchase::where('purchase_status', 'Received')
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->when($filterByBranch, function ($query) use ($branchId) {
                return $query->where('branches_id', $branchId);
            })
            ->sum('total_amount');

        // 3. Beban Operasional Lainnya (Expenses)
        $totalExpenses = PaymentsOut::whereNull('purchase_id')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->when($filterByBranch, function ($query) use ($branchId) {
                return $query->where('branches_id', $branchId);
            })
            ->sum('amount');

        $grossProfit = $totalRevenue - $totalCOGS;
        $netProfit = $grossProfit - $totalExpenses;
        
        // 4. Ambil nama cabang untuk nama file
        $branchName = 'Semua Cabang';
        if ($filterByBranch) {
            $branch = Branch::find($branchId);
            $branchName = $branch->name ?? 'Cabang Tidak Dikenal';
        }

        $reportData = [
            'total_revenue'  => $totalRevenue,
            'total_cogs'     => $totalCOGS,
            'gross_profit'   => $grossProfit,
            'total_expenses' => $totalExpenses,
            'net_profit'     => $netProfit,
            'start_date'     => $startDate->format('Y-m-d'),
            'end_date'       => $endDate->format('Y-m-d'),
            'branch_name'    => $branchName, // Digunakan di ProfitLossExport
        ];

        $filename = 'Laporan_Untung_Rugi_' . Str::slug($branchName) . '_' . $reportData['start_date'] . '_sd_' . $reportData['end_date'] . '.xlsx';

        return Excel::download(new ProfitLossExport($reportData), $filename);
    }

    public function downloadPaymentReport(Request $request)
    {
        // 1. Validasi input (MASIH WAJIB CABANG TERTENTU)
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
        ]);

        $branchId = $data['branches_id'];
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();

        // 2. Query data pembayaran
        $query = Payment::query()
            ->with([
                'paymentMode:id,name',  
                'order:id,invoice_number,customer_id,branches_id', 
                'order.customer:id,name,phone' 
            ])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('order', function ($q) use ($branchId) {
                $q->where('branches_id', $branchId);
            });

        // 3. Terapkan filter pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('order', function($sq) use ($search) {
                    $sq->where('invoice_number', 'like', "%{$search}%");
                })
                ->orWhereHas('order.customer', function($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        // 4. Ambil SEMUA hasil (tanpa paginasi)
        $payments = $query->latest('payment_date')->get();

        $filename = 'Laporan_Pembayaran_' . $startDate->format('Y-m-d') . '_sd_' . $endDate->format('Y-m-d') . '.xlsx';

        $filters = [
            'start_date' => $startDate->toFormattedDateString(),
            'end_date' => $endDate->toFormattedDateString(),
        ];
        
        // Kembalikan file Excel menggunakan Maatwebsite/Excel
        return Excel::download(new PaymentReportExport($payments, $filters), $filename);
    }
    
    /**
     * Mengunduh data laporan Stok Minimum.
     */
    public function downloadMinimumStockReport(Request $request)
    {
        // 1. Validasi
        $data = $request->validate([
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
        ]);

        $branchId = $data['branches_id'];
        
        // 2. Query data (SAMA seperti getMinimumStockReport, tetapi tanpa paginasi)
        $query = ProductDetail::query()
            ->with(['product:id,name']) 
            ->where('branches_id', $branchId)
            
            ->where(function($q) {
                $q->where('current_stock', 0)
                    ->orWhere(function($subQ) {
                        $subQ->whereNotNull('stock_alert') 
                             ->whereRaw('current_stock <= stock_alert');
                    });
            });

        // 3. Filter Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // 4. Ambil SEMUA hasil
        $stockAlerts = $query->orderBy('current_stock', 'asc')->get();
        
        // 5. Ambil nama cabang untuk judul file/laporan
        $branch = Branch::find($branchId);
        $branchName = $branch->name ?? 'Cabang Tidak Dikenal';

        $filename = 'Laporan_Stok_Minimum_' . Str::slug($branchName) . '_' . date('Ymd') . '.xlsx';

        // Kembalikan file Excel
        return Excel::download(new MinimumStockExport($stockAlerts, $branchName), $filename);
    }

    public function downloadSalesReport(Request $request)
    {
        // 1. Validasi input (MASIH WAJIB CABANG TERTENTU)
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'required|integer|exists:branches,id',
            'search'      => 'nullable|string',
        ]);

        $branchId = $data['branches_id'];
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();

        // 2. Query data order (TANPA PAGINASI)
        $query = Order::query()
            ->with(['customer:id,name']) 
            ->where('branches_id', $branchId)
            ->whereBetween('order_date', [$startDate, $endDate]);

        // 3. Terapkan filter pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('customer', function($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        // 4. Ambil SEMUA hasil
        $sales = $query->latest('order_date')->get();

        $filename = 'Laporan_Rekap_Penjualan_' . $startDate->format('Y-m-d') . '_sd_' . $endDate->format('Y-m-d') . '.xlsx';

        $filters = [
            'start_date' => $startDate->toFormattedDateString(),
            'end_date' => $endDate->toFormattedDateString(),
        ];
        
        // Kembalikan file Excel
        return Excel::download(new SalesReportExport($sales, $filters), $filename);
    }

    public function downloadSalesByProductReport(Request $request)
    {
        // 1. Validasi input (MASIH WAJIB CABANG TERTENTU)
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'nullable',
            'search'      => 'nullable|string',
        ]);

        $branchId = $request->has('branches_id') ? (int)$request->branches_id : 0;
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();

        // 2. Query data produk (TANPA PAGINASI)
        $query = Product::query()
        ->with('unit:id,name,short_name')
        ->withSum([
            'orderItems as total_quantity_sold' => function ($query) use ($branchId, $startDate, $endDate) {
                $query->whereHas('order', function ($q) use ($branchId, $startDate, $endDate) {
                    // Logika penanganan "Semua Cabang"
                    if ($branchId > 0) {
                        $q->where('branches_id', $branchId);
                    }
                    $q->whereBetween('order_date', [$startDate, $endDate]);
                });
            }
        ], 'quantity')
        ->having('total_quantity_sold', '>', 0);

        // 3. Terapkan filter pencarian
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // 4. Ambil SEMUA hasil
        $products = $query->orderBy('total_quantity_sold', 'desc')->get();
        
        $filters = [
            'start_date' => $startDate->toFormattedDateString(),
            'end_date' => $endDate->toFormattedDateString(),
        ];
        
        $filename = 'Laporan_Penjualan_Produk_' . $startDate->format('Y-m-d') . '_sd_' . $endDate->format('Y-m-d') . '.xlsx';

        // Kembalikan file Excel
        return Excel::download(new SalesByProductReportExport($products, $filters), $filename);
    }

    public function getExpiringItems(Request $request)
    {
        $branchId = $request->branches_id;
        // Ambil barang yang expired dalam 30 hari ke depan
        $thresholdDate = now()->addDays(30); 

        $batches = ProductBatches::with('product')
            ->where('branches_id', $branchId)
            ->where('quantity', '>', 0) // Hanya yang masih ada stok
            ->whereDate('expiry_date', '<=', $thresholdDate)
            ->orderBy('expiry_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $batches
        ]);
    }

    public function getTopServices(Request $request)
    {
        $data = $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'branches_id' => 'nullable|integer',
            'per_page'    => 'nullable|integer',
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $branchId = (int) ($data['branches_id'] ?? 0);
        $perPage = $request->input('per_page', 10);

        $query = DB::table('registrations')
            ->leftJoin('services', 'registrations.service_id', '=', 'services.id')
            ->join('orders', 'registrations.id', '=', 'orders.registration_id') 
            ->select(
                DB::raw('COALESCE(services.name, registrations.registration_type) as service_name'),
                DB::raw('count(registrations.id) as total_usage'),
                DB::raw('sum(orders.total) as total_revenue')
            )
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.order_date', [$startDate, $endDate]);

        if ($branchId > 0) {
            $query->where('registrations.branches_id', $branchId);
        }

        // Menggunakan paginate() agar format JSON mengandung metadata (total, per_page, dll)
        $topServices = $query->groupBy('service_name', 'services.id')
            ->orderBy('total_usage', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $topServices
        ]);
    }
}