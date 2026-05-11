<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class SalesByProductReportExport implements FromView, ShouldAutoSize, WithTitle
{
    protected $products;
    protected $filters;

    public function __construct($products, $filters)
    {
        $this->products = $products;
        $this->filters = $filters;
    }

    public function view(): View
    {
        return view('reports.sales_by_product_report_export', [
            'products' => $this->products,
            'filters' => $this->filters,
        ]);
    }

    public function title(): string
    {
        return 'Laporan penjuakan produk';
    }
}