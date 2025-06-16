<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Penjualan #{{ $penjualan->invoice_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">

    <style>
        /* Desain dasar untuk tampilan layar */
        body {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 80mm;
            /* Lebar umum untuk thermal printer */
            margin: 0 auto;
            border: 1px dashed #ccc;
            /* Hapus ini untuk produksi */
            padding: 10px;
        }

        h1,
        h2,
        h3,
        h4 {
            margin: 0;
            padding: 0;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .mt-2 {
            margin-top: 8px;
        }

        .mb-2 {
            margin-bottom: 8px;
        }

        .py-1 {
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .border-top {
            border-top: 1px dashed #ccc;
        }

        .border-bottom {
            border-bottom: 1px dashed #ccc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table td,
        table th {
            padding: 4px 0;
            vertical-align: top;
        }

        .item-name {
            width: 60%;
        }

        .item-qty {
            width: 10%;
            text-align: center;
        }

        .item-price {
            width: 30%;
            text-align: right;
        }

        .total-row td {
            font-weight: bold;
            font-size: 14px;
        }

        /* Styling khusus untuk cetakan */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .container {
                border: none;
                /* Hapus border di hasil cetak */
                margin: 0;
                padding: 0;
            }

            /* Mungkin Anda ingin menyembunyikan elemen tertentu atau menyesuaikan ukuran font untuk printer termal */
            /* Misalnya: */
            /* button { display: none; } */
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>{{ $setting->site_name }}</h1>
        <p class="text-center">{{ $setting->address }}</p>
        <p class="text-center">Telp: {{ $setting->phone }}</p>
        <div class="border-top mt-2 mb-2"></div>

        <p>Invoice: <span class="font-bold">{{ $penjualan->invoice_number }}</span></p>
        <p>Tanggal: {{ \Carbon\Carbon::parse($penjualan->created_at)->translatedFormat('d M Y, H:i') }}</p>
        <p>Kasir: {{ $penjualan->user->name ?? 'N/A' }}</p>
        <p>Pelanggan: {{ $penjualan->customer->name ?? 'Guest' }}</p>
        @if ($penjualan->type === 'dine_in' && $penjualan->meja)
            <p>Meja: {{ $penjualan->meja->nama }}</p>
        @endif
        <p>Tipe: {{ ucwords(str_replace('_', ' ', $penjualan->type)) }}</p>
        <div class="border-bottom mt-2 mb-2"></div>

        <table>
            <thead>
                <tr>
                    <th class="item-name text-left">Produk</th>
                    <th class="item-qty">Qty</th>
                    <th class="item-price">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($penjualan->details as $detail)
                    <tr>
                        <td class="item-name">{{ $detail->produk->nama_produk ?? 'Produk Tidak Ditemukan' }}</td>
                        <td class="item-qty">{{ $detail->qty }}</td>
                        <td class="item-price">
                            {{ number_format($detail->produk->harga_jual * $detail->qty, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="border-top mt-2 mb-2"></div>

        <p>Sub Total: <span class="float-right">{{ number_format($penjualan->sub_total, 0, ',', '.') }}</span></p>
        <p>PPN ({{ $penjualan->ppn }}%): <span
                class="float-right">{{ number_format(($penjualan->sub_total * $penjualan->ppn) / 100, 0, ',', '.') }}</span>
        </p>
        <p>Biaya Layanan: <span class="float-right">{{ number_format($penjualan->biaya_layanan, 0, ',', '.') }}</span>
        </p>
        <p class="font-bold total-row">TOTAL: <span
                class="float-right">{{ number_format($penjualan->total, 0, ',', '.') }}</span></p>

        @if ($penjualan->payment_method === 'cash')
            <p>Dibayar: <span class="float-right">{{ number_format($penjualan->total, 0, ',', '.') }}</span></p>
            {{-- Asumsi ini uang dibayar, sesuaikan jika ada kolom 'amount_paid' terpisah --}}
            <p>Kembali: <span
                    class="float-right">{{ number_format($penjualan->total - $penjualan->total, 0, ',', '.') }}</span>
            </p> {{-- Sesuaikan dengan kembalian riil jika ada --}}
        @endif
        <p>Metode Pembayaran: <span class="float-right capitalize">{{ $penjualan->payment_method }}</span></p>
        <p>Status: <span class="float-right capitalize">{{ $penjualan->status }}</span></p>

        <div class="border-top mt-2 mb-2"></div>
        <p class="text-center mt-2">Terima kasih atas kunjungan Anda!</p>
        <p class="text-center">Silakan datang kembali.</p>
    </div>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>

</html>
