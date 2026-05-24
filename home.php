<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: signin.php");
    exit;
}

// Ambil semua data
$senjata = json_decode(file_get_contents('data/senjata.json'), true) ?? [];
$logistik = json_decode(file_get_contents('data/logistik_umum.json'), true) ?? [];
$anggota = json_decode(file_get_contents('data/anggota.json'), true) ?? [];
$riwayat = json_decode(file_get_contents('data/riwayat.json'), true) ?? [];

// Hitung Statistik
$total_anggota = count($anggota);
$total_senjata = count($senjata);
$senjata_keluar = count(array_filter($senjata, function($s) { return $s['status_lokasi'] !== 'Di Gudang'; }));
$senjata_gudang = $total_senjata - $senjata_keluar;

// Cari logistik yang stoknya menipis (<= 5)
$logistik_kritis = array_filter($logistik, function($l) { return $l['stok_tersedia'] <= 5; });

// 5 Aktivitas Terbaru
$riwayat_terbaru = array_slice($riwayat, 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B600%3B700&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" rel="stylesheet" />
    <title>Beranda - Logistik Brimob</title>
    <style>body { font-family: 'Inter', 'Noto Sans', sans-serif; }</style>
</head>
<body class="bg-gray-50 flex flex-col md:flex-row h-screen overflow-hidden">
  
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-8 overflow-y-auto relative w-full">
    
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Logistik Kompi 3 Yon B Por</h1>
        <p class="text-gray-500 mt-1">Ringkasan inventaris dan pergerakan barang hari ini.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase">Total Personil</p>
                <h3 class="text-2xl font-black text-gray-800"><?= $total_anggota ?> <span class="text-sm font-medium text-gray-500">Anggota</span></h3>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600 flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase">Senjata di Gudang</p>
                <h3 class="text-2xl font-black text-gray-800"><?= $senjata_gudang ?> <span class="text-sm font-medium text-gray-500">Pucuk</span></h3>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600 flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase">Senjata Keluar</p>
                <h3 class="text-2xl font-black text-gray-800"><?= $senjata_keluar ?> <span class="text-sm font-medium text-gray-500">Dibawa</span></h3>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase">Katalog Logistik</p>
                <h3 class="text-2xl font-black text-gray-800"><?= count($logistik) ?> <span class="text-sm font-medium text-gray-500">Jenis</span></h3>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-lg shadow-sm flex flex-col">
            <div class="p-4 border-b border-gray-200 bg-gray-50 rounded-t-lg flex justify-between items-center">
                <h2 class="font-bold text-gray-800">Aktivitas Terbaru</h2>
                <a href="laporan.php" class="text-xs font-bold text-blue-600 hover:underline">Lihat Semua &rarr;</a>
            </div>
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left text-sm whitespace-nowrap min-w-max">
                    <thead class="bg-white border-b border-gray-100 text-gray-500 text-[11px] uppercase">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Waktu</th>
                            <th class="px-4 py-3 font-semibold">Personil</th>
                            <th class="px-4 py-3 font-semibold">Item</th>
                            <th class="px-4 py-3 font-semibold text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if(count($riwayat_terbaru) > 0): foreach($riwayat_terbaru as $log): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= date('d M, H:i', strtotime($log['waktu'])) ?></td>
                            <td class="px-4 py-3 font-bold text-gray-800 uppercase text-xs"><?= htmlspecialchars($log['nama_personil'] ?? 'Tidak Terdata') ?></td>
                            <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($log['item']) ?></td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-bold text-[10px] px-2 py-1 rounded <?= $log['jenis_transaksi'] == 'Keluar Gudang' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>">
                                    <?= strtoupper($log['jenis_transaksi']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Belum ada aktivitas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-red-200 rounded-lg shadow-sm flex flex-col">
            <div class="p-4 border-b border-red-200 bg-red-50 rounded-t-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <h2 class="font-bold text-red-800">Peringatan Stok Menipis</h2>
            </div>
            <div class="p-4 overflow-y-auto max-h-[300px]">
                <?php if(count($logistik_kritis) > 0): ?>
                    <ul class="space-y-3">
                        <?php foreach($logistik_kritis as $kritis): ?>
                        <li class="flex justify-between items-center bg-gray-50 p-3 rounded border border-gray-100">
                            <div>
                                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($kritis['nama_barang']) ?></p>
                                <p class="text-[10px] text-gray-500 font-mono"><?= $kritis['id_barang'] ?></p>
                            </div>
                            <div class="text-right">
                                <span class="bg-red-100 text-red-800 font-black px-2 py-1 rounded text-xs">Sisa: <?= $kritis['stok_tersedia'] ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <p class="text-sm font-bold text-gray-500">Semua stok logistik aman.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
  </main>
</body>
</html>