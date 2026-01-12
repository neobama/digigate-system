<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Proforma Invoice</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 14px;
            color: #000;
            margin: 0;
            padding: 40px;
            position: relative;
        }
        .kop-surat {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            margin: 0;
            padding: 0;
        }
        .kop-surat img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .content {
            position: relative;
            z-index: 1;
        }
        .header {
            text-align: center;
            margin-top: 50px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 10px 0;
            font-size: 22px;
            letter-spacing: 1px;
        }
        .info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .info-left {
            width: 48%;
            text-align: left;
        }
        .info-right {
            width: 48%;
            text-align: right;
        }
        .info-right p {
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 6px;
            text-align: center;
        }
        th {
            font-weight: bold;
        }
        .text-left {
            text-align: left;
        }
        .summary {
            width: 40%;
            float: right;
            margin-top: 10px;
        }
        .summary td {
            text-align: right;
        }
        .notes {
            margin-top: 40px;
        }
        .footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .footer-left {
            width: 48%;
        }
        .signature {
            width: 48%;
            text-align: right;
        }
        .signature p {
            text-align: right;
        }
    </style>
</head>
<body>

    @if($kopSurat ?? null)
    <div class="kop-surat">
        <img src="{{ $kopSurat }}" alt="Kop Surat DigiGate">
    </div>
    @endif

    <div class="content">
        <div class="header">
            <h1>PROFORMA INVOICE</h1>
        </div>

        <div class="info">
            <div class="info-left">
                <p><strong>Untuk:</strong> {{ $invoice->client_name }}</p>
                <p><strong>UP:</strong> -</p>
            </div>
            <div class="info-right">
                <p><strong>No. Invoice:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Tanggal Invoice:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->locale('id')->translatedFormat('d F Y') }}</p>
                @if($invoice->po_number)
                <p><strong>Nomor PO:</strong> {{ $invoice->po_number }}</p>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Keterangan</th>
                    <th>Qty</th>
                    <th>Harga (Rp)</th>
                    <th>Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                @endphp
                @foreach(($invoice->items ?? []) as $index => $item)
                    @php
                        $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                        $subtotal += $itemTotal;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="text-left">{{ $item['name'] ?? '' }}</td>
                        <td>{{ $item['quantity'] ?? 1 }}</td>
                        <td>{{ number_format($item['price'] ?? 0, 0, ',', '.') }}</td>
                        <td>{{ number_format($itemTotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td>Subtotal</td>
                <td>{{ number_format($subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Diskon</td>
                <td>{{ $invoice->discount && $invoice->discount > 0 ? number_format($invoice->discount, 0, ',', '.') : '-' }}</td>
            </tr>
            <tr>
                <td>Ongkir</td>
                <td>{{ $invoice->shipping_cost ? number_format($invoice->shipping_cost, 0, ',', '.') : '-' }}</td>
            </tr>
            <tr>
                <td><strong>Total</strong></td>
                <td><strong>{{ number_format($invoice->total_amount, 0, ',', '.') }}</strong></td>
            </tr>
        </table>

        <div style="clear: both;"></div>

        <div class="notes">
            <p><strong>Notes:</strong></p>
            <ul>
                <li>Garansi berlaku selama 12 bulan sejak barang diterima.</li>
            </ul>
        </div>

        <div class="footer">
            <div class="footer-left">
                <p><strong>Pembayaran dapat ditransfer melalui:</strong></p>
                <p>Bank BCA Cab. Matraman</p>
                <p>No. Rekening: 3420660391</p>
                <p>Atas Nama: PT. Gerbang Digital Indonesia</p>
            </div>
            <div class="signature">
                <p>Jakarta, {{ \Carbon\Carbon::parse($invoice->invoice_date)->locale('id')->translatedFormat('d F Y') }}</p>
                <p><strong>Neorafa A. Zulkarnaeen</strong></p>
            </div>
        </div>
    </div>

</body>
</html>
