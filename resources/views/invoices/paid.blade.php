<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 15mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            max-height: 60px;
            height: auto;
        }

        h2 {
            font-size: 18px;
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
        }

        .meta-table {
            width: 100%;
            margin-top: 15px;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 5px 10px;
            vertical-align: top;
        }

        .meta-table td:first-child {
            width: 25%;
            font-weight: bold;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table.items th,
        table.items td {
            border: 1px solid #000;
            padding: 8px;
        }

        table.items th {
            background: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }

        table.items td {
            text-align: left;
        }

        table.items td:first-child,
        table.items th:first-child {
            text-align: center;
            width: 5%;
        }

        table.items td:nth-child(3),
        table.items td:nth-child(4),
        table.items td:nth-child(5),
        table.items th:nth-child(3),
        table.items th:nth-child(4),
        table.items th:nth-child(5) {
            text-align: right;
        }

        .summary {
            margin-top: 20px;
            margin-left: auto;
            width: 40%;
        }

        .summary table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary td {
            padding: 5px 10px;
            border: none;
        }

        .summary td:first-child {
            text-align: left;
            font-weight: bold;
        }

        .summary td:last-child {
            text-align: right;
        }

        .summary .total-row td {
            font-weight: bold;
            font-size: 13px;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('images/digigate-logo.png') }}" alt="DigiGate Logo">
        <h2>INVOICE</h2>
    </div>

    <table class="meta-table">
        <tr>
            <td>Nomor</td>
            <td>: {{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
            <td>Nomor PO</td>
            <td>: {{ $invoice->po_number ?? '-' }}</td>
        </tr>
        <tr>
            <td>Client</td>
            <td>: {{ $invoice->client_name }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>: {{ strtoupper($invoice->status) }}</td>
        </tr>
    </table>

    <table class="items">
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
                <td>{{ $item['name'] ?? '' }}</td>
                <td>{{ $item['quantity'] ?? 1 }}</td>
                <td>Rp {{ number_format($item['price'] ?? 0, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($itemTotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <td>Subtotal</td>
                <td>Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Diskon :</td>
                <td>{{ $invoice->discount && $invoice->discount > 0 ? 'Rp ' . number_format($invoice->discount, 0, ',', '.') : '-' }}</td>
            </tr>
            <tr>
                <td>Ongkir :</td>
                <td>{{ $invoice->shipping_cost ? 'Rp ' . number_format($invoice->shipping_cost, 0, ',', '.') : '-' }}</td>
            </tr>
            <tr class="total-row">
                <td>Total :</td>
                <td>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>


