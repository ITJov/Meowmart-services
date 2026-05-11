<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class SalesReportExport implements FromView, ShouldAutoSize, WithTitle
{
    protected $sales;
    protected $filters;

    public function __construct($sales, $filters)
    {
        $this->sales = $sales;
        $this->filters = $filters;
    }

    public function view(): View
    {
        // Asumsi View Export Anda berada di reports/sales_report_export.blade.php
        return view('reports.sales_report_export', [
            'sales' => $this->sales,
            'filters' => $this->filters,
        ]);
    }

    public function title(): string
    {
        return 'Rekap Penjualan';
    }
}