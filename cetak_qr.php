<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

// Tangkap data dari URL (GET)
$id_barang = isset($_GET['id']) ? $_GET['id'] : 'TIDAK_ADA_ID';
$nama_barang = isset($_GET['nama']) ? $_GET['nama'] : 'Nama Barang';
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'logistik';

// Format teks label
$label_tipe = ($tipe == 'senjata') ? 'INVENTARIS ORGANIK' : 'LOGISTIK SATBRIMOB';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cetak Label QR - <?= $id_barang ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        /* Styling khusus agar pas di-print */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0; /* Warna abu-abu saat dilihat di layar */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        /* Ini adalah bentuk stiker label fisiknya */
        .label-container {
            background-color: white;
            width: 50mm; /* Lebar standar stiker label (5cm) */
            height: 50mm; /* Tinggi standar stiker label (5cm) */
            border: 1px dashed #ccc; /* Garis potong panduan */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2mm;
            box-sizing: border-box;
            text-align: center;
        }

        .label-header {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2mm;
            border-bottom: 1px solid black;
            padding-bottom: 1px;
            width: 100%;
        }

        #qrcode {
            margin-bottom: 2mm;
        }
        
        #qrcode img {
            display: block;
            margin: 0 auto;
            width: 25mm; /* Ukuran QR Code 2.5cm */
            height: 25mm;
        }

        .label-id {
            font-family: monospace;
            font-size: 9pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .label-nama {
            font-size: 7pt;
            margin: 1mm 0 0 0;
            text-transform: uppercase;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .no-print-area {
            position: fixed;
            top: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }

        .btn-print {
            background-color: #0d7ff2;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }

        .btn-print:hover { background-color: #0269d3; }

        /* Aturan sakti saat Ctrl+P (Print) ditekan */
        @media print {
            body { 
                background-color: white; 
                display: block; /* Matikan flexbox agar bisa print banyak */
            }
            .no-print-area { 
                display: none; /* Sembunyikan tombol print */
            }
            .label-container {
                border: none; /* Hilangkan garis putus-putus */
                page-break-inside: avoid; /* Jangan memotong label di tengah halaman */
                margin: 0;
                /* Reset ukuran ke 100% jika menggunakan printer thermal label roll */
                width: 100%; 
                height: 100%;
            }
            @page {
                /* Set ukuran kertas khusus label di sini (Contoh 50x50mm) */
                /* size: 50mm 50mm; */
                margin: 0; /* Hilangkan margin kertas bawaan browser */
            }
        }
    </style>
</head>
<body>

    <div class="no-print-area">
        <p style="margin:0 0 10px 0; font-size:14px; color:#555;">Review Stiker Label (Ukuran 5x5 cm)</p>
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Stiker Sekarang</button>
        <br>
        <a href="javascript:window.close()" style="font-size: 12px; color: #888; text-decoration: none; display: inline-block; margin-top: 15px;">Tutup Tab Ini</a>
    </div>

    <div class="label-container">
        <div class="label-header"><?= $label_tipe ?></div>
        
        <div id="qrcode"></div>
        
        <p class="label-id"><?= htmlspecialchars($id_barang) ?></p>
        <p class="label-nama"><?= htmlspecialchars($nama_barang) ?></p>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Ambil ID / Nomor Seri dari PHP
            var kodeBarang = "<?= htmlspecialchars($id_barang) ?>";
            
            // Generate QR Code ke dalam div #qrcode
            var qrcode = new QRCode(document.getElementById("qrcode"), {
                text: kodeBarang,
                width: 128, // Resolusi pixel internal
                height: 128,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M // Level toleransi error medium (bisa discan meski agak rusak)
            });
            
            // Fitur opsional: Otomatis memanggil dialog print setelah QR Code selesai digambar
            // setTimeout(() => { window.print(); }, 500); 
        });
    </script>
</body>
</html>