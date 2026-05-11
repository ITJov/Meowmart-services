<!DOCTYPE html>
<html>
<head>
    <title>Laporan Rekap Penjualan</title>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th colspan="5">LAPORAN REKAP PENJUALAN</th>
            </tr>
            <tr>
                <th colspan="5">Periode: {{ $filters['start_date'] }} s/d {{ $filters['end_date'] }}</th>
            </tr>
            <tr><th colspan="5"></th></tr>
            <tr>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">TANGGAL TRANSAKSI</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">NO. INVOICE</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">NAMA PELANGGAN</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">TOTAL TRANSAKSI (Rp)</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">STATUS PEMBAYARAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sales as $order)
            <tr>
                <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d-M-Y H:i') }}</td>
                <td>{{ $order->invoice_number }}</td>
                <td>{{ $order->customer->name ?? 'N/A' }}</td>
                <td style="text-align: right;">{{ $order->total }}</td>
                <td>{{ $order->payment_status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>