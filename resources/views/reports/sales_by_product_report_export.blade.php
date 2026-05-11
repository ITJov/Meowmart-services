<!DOCTYPE html>
<html>
<head>
    <title>Laporan Penjualan by Produk</title>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th colspan="3">LAPORAN PENJUALAN BERDASARKAN PRODUK</th>
            </tr>
            <tr>
                <th colspan="3">Periode: {{ $filters['start_date'] }} s/d {{ $filters['end_date'] }}</th>
            </tr>
            <tr><th colspan="3"></th></tr>
            <tr>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">NAMA PRODUK</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">SATUAN</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">TOTAL STOK TERJUAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
            <tr>
                <td>{{ $product->name }}</td>
                <td>{{ $product->unit->name ?? 'N/A' }}</td>
                <td style="text-align: right;">{{ $product->total_quantity_sold }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>