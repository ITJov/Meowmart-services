<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pembayaran</title>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th colspan="6">LAPORAN PEMBAYARAN</th>
            </tr>
            <tr>
                <th colspan="6">Periode: {{ $filters['start_date'] }} s/d {{ $filters['end_date'] }}</th>
            </tr>
            <tr><th colspan="6"></th></tr>
            <tr>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">TANGGAL PEMBAYARAN</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">NO. REFERENSI</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">JUMLAH (Rp)</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">METODE PEMBAYARAN</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">NAMA PELANGGAN</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">NO. HP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payments as $payment)
            <tr>
                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y H:i') }}</td>
                <td>{{ $payment->order->invoice_number ?? 'N/A' }}</td>
                <td style="text-align: right;">{{ $payment->amount }}</td>
                <td>{{ $payment->paymentMode->name ?? 'N/A' }}</td>
                <td>{{ $payment->order->customer->name ?? 'N/A' }}</td>
                <td>{{ $payment->order->customer->phone ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>