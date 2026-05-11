<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class MinimumStockExport implements FromView, ShouldAutoSize, WithTitle
{
    protected $stockAlerts;
    protected $branchName;

    public function __construct($stockAlerts, $branchName)
    {
        $this->stockAlerts = $stockAlerts;
        $this->branchName = $branchName;
    }

    public function view(): View
    {
        return view('reports.minimum_stock_export', [
            'stockList' => $this->stockAlerts,
            'branchName' => $this->branchName,
        ]);
    }

    public function title(): string
    {
        return 'Pengingat Stok Minimum';
    }
}