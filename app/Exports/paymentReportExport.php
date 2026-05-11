<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class PaymentReportExport implements FromView, ShouldAutoSize, WithTitle
{
    protected $payments;
    protected $filters;

    public function __construct($payments, $filters)
    {
        $this->payments = $payments;
        $this->filters = $filters;
    }

    public function view(): View
    {
        return view('reports.payment_report_export', [
            'payments' => $this->payments,
            'filters' => $this->filters,
        ]);
    }

    public function title(): string
    {
        return 'Laporan Pembayaran';
    }
}