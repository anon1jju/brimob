<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: signin.php");
    exit;
}

require_once 'class/LogistikDB.php';
$db = new LogistikDB();

// 1. DATA LOGISTIK
$logistik_data = $db->getLogistikUmum() ?? [];
$calc_total_fisik = 0; $calc_tersedia = 0; $calc_rusak = 0; $calc_dipinjam = 0;

foreach($logistik_data as $l) {
    $stok_rusak = isset($l['stok_rusak']) ? (int)$l['stok_rusak'] : 0;
    $total = (int)$l['total_stok'];
    $tersedia = (int)$l['stok_tersedia'];
    $dipinjam = $total - $tersedia - $stok_rusak;
    $calc_total_fisik += $total; $calc_tersedia += $tersedia; $calc_rusak += $stok_rusak; $calc_dipinjam += $dipinjam;
}

// 2. DATA SENJATA
$senjata_data = $db->getAllSenjata() ?? [];
$senjata_gudang = 0; $senjata_keluar = 0; $senjata_rusak = 0;
foreach($senjata_data as $s) {
    if ($s['status_lokasi'] == 'Di Gudang') $senjata_gudang++;
    elseif ($s['status_lokasi'] == 'Rusak/Perbaikan') $senjata_rusak++;
    else $senjata_keluar++;
}
$senjata_total = count($senjata_data);

// 3. RIWAYAT & FILTER
$semua_riwayat = json_decode(file_get_contents('data/riwayat.json'), true) ?? [];
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$riwayat_tampil = $semua_riwayat; 

if ($start_date !== '' && $end_date !== '') {
    $riwayat_tampil = array_filter($semua_riwayat, function($log) use ($start_date, $end_date) {
        $waktu_log = date('Y-m-d', strtotime($log['waktu']));
        return ($waktu_log >= $start_date && $waktu_log <= $end_date);
    });
}

$settings = json_decode(file_get_contents('data/settings.json'), true);
$nama_komandan = $settings['nama_komandan'] ?? 'Nama Komandan';
$pangkat_komandan = $settings['pangkat_komandan'] ?? 'Pangkat Komandan'
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <title>Laporan Logistik - PDF Ready</title>
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* ATURAN KHUSUS SAAT PRINT */
        @media print {
            /* Sembunyikan Sidebar, Tombol, dan Form Filter */
            aside, .no-print, #sidebar-overlay, .md\:hidden { display: none !important; }
            
            /* Paksa Konten Jadi Full Width */
            main { padding: 0 !important; margin: 0 !important; width: 100% !important; overflow: visible !important; }
            .bg-gray-50 { background-color: white !important; }
            
            /* Tabel Agar Tidak Terpotong di Antara Halaman */
            table { page-break-inside: auto; width: 100% !important; border-collapse: collapse; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            
            /* Tampilkan Warna Latar (Untuk Chrome/Edge) */
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            
            /* Hilangkan bayangan box agar bersih */
            .shadow-sm, .shadow-md { shadow: none !important; border: 1px solid #e5e7eb !important; }

            /* Atur ukuran font agar pas di kertas */
            th, td { font-size: 10pt !important; padding: 8px 4px !important; }
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col md:flex-row h-screen overflow-hidden">
  
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-8 overflow-y-auto w-full relative block space-y-8 custom-scrollbar">
    
    <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-gray-800 tracking-tight">Laporan Mutasi Senpi Rutin</h1>
            <p class="text-gray-500 mt-1 text-sm font-medium no-print">Data diupdate pada: <?= date('d/m/Y H:i') ?></p>
        </div>
        
        <div class="flex gap-2 no-print">
            <button onclick="window.print()" class="bg-gray-900 hover:bg-black text-white px-5 py-2.5 rounded-md font-bold text-sm shadow-md transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak PDF / Print
            </button>
        </div>
    </div>

    <!--<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
            <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Total Fisik</p>
            <h3 class="text-2xl font-black text-gray-900"><?= $calc_total_fisik + $senjata_total ?></h3>
        </div>
        <div class="bg-white rounded-xl p-4 border border-green-200 shadow-sm">
            <p class="text-[10px] font-bold text-green-600 uppercase mb-1">Siap Pakai</p>
            <h3 class="text-2xl font-black text-green-600"><?= $calc_tersedia + $senjata_gudang ?></h3>
        </div>
        <div class="bg-white rounded-xl p-4 border border-blue-200 shadow-sm">
            <p class="text-[10px] font-bold text-blue-600 uppercase mb-1">Dipinjam</p>
            <h3 class="text-2xl font-black text-blue-600"><?= $calc_dipinjam + $senjata_keluar ?></h3>
        </div>
        <div class="bg-white rounded-xl p-4 border border-red-200 shadow-sm">
            <p class="text-[10px] font-bold text-red-600 uppercase mb-1">Rusak</p>
            <h3 class="text-2xl font-black text-red-600"><?= $calc_rusak + $senjata_rusak ?></h3>
        </div>
    </div>-->

    <!--<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="p-4 bg-gray-50 border-b border-gray-200 font-bold text-gray-700">RINCIAN STOK GUDANG</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-gray-100 border-b border-gray-200 text-[11px] uppercase text-gray-500 font-bold">
                    <tr>
                        <th class="px-4 py-3">Barang</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3 text-center">Total</th>
                        <th class="px-4 py-3 text-center">Ready</th>
                        <th class="px-4 py-3 text-center">Keluar</th>
                        <th class="px-4 py-3 text-center">Rusak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($logistik_data as $l): 
                        $rsk = $l['stok_rusak'] ?? 0;
                        $pjm = $l['total_stok'] - $l['stok_tersedia'] - $rsk;
                    ?>
                    <tr>
                        <td class="px-4 py-3 font-bold text-gray-800 uppercase text-xs"><?= htmlspecialchars($l['nama_barang']) ?></td>
                        <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($l['kategori']) ?></td>
                        <td class="px-4 py-3 text-center font-bold"><?= $l['total_stok'] ?></td>
                        <td class="px-4 py-3 text-center text-green-600 font-bold"><?= $l['stok_tersedia'] ?></td>
                        <td class="px-4 py-3 text-center text-blue-600 font-bold"><?= $pjm ?></td>
                        <td class="px-4 py-3 text-center text-red-600 font-bold"><?= $rsk ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>-->

    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm no-print">
        <p class="text-xs font-bold text-gray-400 mb-3 uppercase tracking-widest">Filter Riwayat Transaksi</p>
        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?= $start_date ?>" class="rounded-md border-gray-300 text-sm p-2">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?= $end_date ?>" class="rounded-md border-gray-300 text-sm p-2">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md font-bold text-sm shadow-sm">Filter</button>
            <?php if($start_date): ?><a href="laporan.php" class="text-red-500 text-sm font-bold underline px-2">Reset</a><?php endif; ?>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="p-4 bg-gray-50 border-b border-gray-200 font-bold text-gray-700 uppercase text-xs">Riwayat Pergerakan <?= $start_date ? "($start_date s/d $end_date)" : "(Semua)" ?></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-gray-100 border-b border-gray-200 text-[10px] uppercase text-gray-500 font-bold">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Personil</th>
                        <th class="px-4 py-3">Aksi</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(count($riwayat_tampil) > 0): foreach($riwayat_tampil as $log): ?>
                    <tr class="text-[11px]">
                        <td class="px-4 py-2 font-mono text-gray-500"><?= date('d/m/y H:i', strtotime($log['waktu'])) ?></td>
                        <td class="px-4 py-2 font-bold text-gray-800 uppercase"><?= htmlspecialchars($log['nama_personil'] ?? '-') ?></td>
                        <td class="px-4 py-2">
                            <span class="font-bold <?= $log['jenis_transaksi'] == 'Keluar Gudang' ? 'text-red-600' : 'text-green-600' ?>">
                                <?= strtoupper($log['jenis_transaksi']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($log['item']) ?></td>
                        <td class="px-4 py-2 text-gray-500 italic"><?= htmlspecialchars($log['keterangan']) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Data riwayat tidak ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="hidden print:grid grid-cols-2 mt-20 text-center">
        <div>
            <p class="text-sm mb-20 font-bold">Mengetahui,<br>KOMANDAN KOMPI 3 YON B PELOPOR</p>
            <p class="text-sm border-b border-black w-48 mx-auto font-bold uppercase"><?php echo $nama_komandan; ?></p>
            <p class="text-xs"><?php echo $pangkat_komandan; ?></p>
        </div>
        <div>
            <p class="text-sm mb-20 font-bold">Aceh Utara, <?= date('d F Y') ?><br>BA LOG KOMPI 3 YON B PELOPOR</p>
            <p class="text-sm border-b border-black w-48 mx-auto font-bold uppercase"><?= htmlspecialchars($_SESSION['nama_petugas'] ?? 'NAMA PETUGAS') ?></p>
            <p class="text-xs"><?= htmlspecialchars($_SESSION['pangkat']. ' NRP '.$_SESSION['nrp'] ?? 'NRP PETUGAS') ?></p>
        </div>
    </div>

  </main>
</body>
</html>