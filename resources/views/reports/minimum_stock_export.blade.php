<!DOCTYPE html>
<html>
<head>
    <title>Laporan Stok Minimum</title>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th colspan="4">TABEL PENGINGAT STOK</th>
            </tr>
            <tr>
                <th colspan="4">Cabang: {{ $branchName }}</th>
            </tr>
            <tr><th colspan="4"></th></tr>
            <tr>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">NAMA PRODUK</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">STOK SAAT INI</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">PENGINGAT STOK</th>
                <th style="font-weight: bold; border: 1px solid #000; background-color: #f2f2f2;">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stockList as $item)
            {{-- Tentukan warna dan status di Blade, bukan di inline style --}}
            @php
                // Jika stock_alert NULL atau 0, anggap aman untuk menghindari error perbandingan
                $alertValue = $item->stock_alert ?? 0;
                $isAlert = $item->current_stock <= $alertValue;
                $color = $isAlert ? 'red' : 'black';
                $statusText = $isAlert ? 'PERLU RE-STOCK' : 'Stok Cukup';
                
                // Pastikan stok ditampilkan bahkan jika NULL dari database
                $currentStock = $item->current_stock ?? 0;
                
                // 🚀 BUAT STRING STYLE LENGKAP
                $style = "text-align: right; color: " . $color . ";";
            @endphp
            <tr>
                <td>{{ $item->product->name ?? 'N/A' }}</td>
                
                {{-- 🚀 PERBAIKAN: Masukkan variabel style string langsung ke atribut --}}
                <td>
                    {{ $currentStock }}
                </td>
                
                <td style="text-align: right;">{{ $alertValue }}</td>
                <td>
                    {{ $statusText }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>