<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - {{ $employee->name }}</title>
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

        .info-table {
            width: 100%;
            margin-top: 15px;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 5px 10px;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 25%;
            font-weight: bold;
        }

        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table.details th,
        table.details td {
            border: 1px solid #000;
            padding: 8px;
        }

        table.details th {
            background: #f2f2f2;
            text-align: left;
            font-weight: bold;
        }

        .text-right {
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
        <h2>SLIP GAJI</h2>
    </div>

    <table class="info-table">
        <tr>
            <td>Nama</td>
            <td>: {{ $employee->name }}</td>
        </tr>
        <tr>
            <td>NIK</td>
            <td>: {{ $employee->nik }}</td>
        </tr>
        <tr>
            <td>Posisi</td>
            <td>: {{ $employee->position }}</td>
        </tr>
        <tr>
            <td>Periode</td>
            <td>: {{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }}</td>
        </tr>
    </table>

    @if($cashbons->count() > 0)
    <table class="details">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Keterangan Cashbon</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
        @foreach($cashbons as $cashbon)
            <tr>
                <td>{{ \Carbon\Carbon::parse($cashbon->request_date)->format('d/m/Y') }}</td>
                <td>{{ $cashbon->reason }}</td>
                <td class="text-right">Rp {{ number_format($cashbon->amount, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    <div class="summary">
        <table>
            <tr>
                <td>Gaji Pokok</td>
                <td>Rp {{ number_format($base_salary, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Potongan Cashbon</td>
                <td>Rp {{ number_format($total_cashbon, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Potongan BPJS</td>
                <td>Rp {{ number_format($bpjs_allowance, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td>Gaji Bersih</td>
                <td>Rp {{ number_format($gaji_bersih, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>

