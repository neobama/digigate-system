<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 12pt;
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
        h1 {
            text-align: center;
            font-size: 16pt;
            margin-top: 50px;
            margin-bottom: 30px;
            letter-spacing: 1px;
        }
        .info {
            width: 60%;
            margin-bottom: 25px;
        }
        .info table {
            width: 100%;
            border-collapse: collapse;
        }
        .info td {
            padding: 4px 0;
            vertical-align: top;
        }
        .info .label {
            width: 120px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.data th,
        table.data td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        table.data th {
            font-weight: bold;
        }
        .text-left {
            text-align: left;
        }
        .signature {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
        }
        .signature div {
            width: 40%;
            text-align: center;
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
        <h1>SURAT JALAN</h1>

        <div class="info">
            <table>
                <tr>
                    <td class="label">Nomor</td>
                    <td>:</td>
                    <td>{{ $suratJalanNumber }}</td>
                </tr>
                <tr>
                    <td class="label">Tanggal</td>
                    <td>:</td>
                    <td>{{ \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Penerima</td>
                    <td>:</td>
                    <td>{{ $invoice->client_name }}</td>
                </tr>
                <tr>
                    <td class="label">Invoice</td>
                    <td>:</td>
                    <td>{{ $invoice->invoice_number }}</td>
                </tr>
            </table>
        </div>

        <table class="data">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Barang</th>
                    <th>Quantity</th>
                    <th>SN</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rowNumber = 1;
                @endphp
                @foreach($assemblies as $assembly)
                    <tr>
                        <td>{{ $rowNumber++ }}</td>
                        <td class="text-left">DigiGate {{ $assembly->product_type }} - License</td>
                        <td>1</td>
                        <td>{{ $assembly->serial_number ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="signature">
            <div>
                <p>Pengirim</p>
                <br><br><br>
                <p>( .................................... )</p>
            </div>
            <div>
                <p>Penerima</p>
                <br><br><br>
                <p>( .................................... )</p>
            </div>
        </div>
    </div>

</body>
</html>
