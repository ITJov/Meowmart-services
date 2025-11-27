<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProfitLossExport implements FromView, ShouldAutoSize, WithTitle
{
    protected $reportData;

    public function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    public function view(): View
    {
        // Ganti dengan path view yang sesuai
        return view('reports.profit_loss_export', [
            'report' => $this->reportData,
        ]);
    }

    public function title(): string
    {
        return 'Ringkasan Laba Rugi';
    }
}