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

// =========================
// HITUNG STATISTIK DASHBOARD
// =========================

// 1. Total Personil (unik berdasarkan id_anggota)
$unique_anggota = [];
foreach ($anggota as $a) {
    $id = trim($a['id_anggota'] ?? '');
    if ($id !== '') {
        $unique_anggota[$id] = true;
    }
}
$total_anggota = count($unique_anggota);

// 2. Total Senjata
$total_senjata = count($senjata);

// 3. Senjata di Gudang (status spesifik)
$senjata_gudang = count(array_filter($senjata, function($s) {
    return trim($s['status_lokasi'] ?? '') === 'Di Gudang';
}));

// 4. Senjata Keluar / Dibawa Bertugas (status spesifik)
$senjata_keluar = count(array_filter($senjata, function($s) {
    return trim($s['status_lokasi'] ?? '') === 'Dibawa Bertugas';
}));

// 5. Katalog Logistik = jumlah jenis/baris logistik
$total_logistik = count($logistik);

// Cari logistik yang stoknya menipis (<= 5)
$logistik_kritis = array_filter($logistik, function($l) {
    return (int)($l['stok_tersedia'] ?? 0) <= 5;
});

// 5 Aktivitas Terbaru
$riwayat_terbaru = array_slice($riwayat, 0, 5);


// Ambil nama kegiatan dari format: [Nama Kegiatan] ...
function extractNamaKegiatan($text) {
    if (preg_match('/\[(.*?)\]/', $text, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

// Hitung kegiatan yang sedang aktif
function getKegiatanAktif($riwayat) {
    $status_terakhir = [];

    foreach ($riwayat as $item) {
        $id_anggota = trim($item['id_anggota'] ?? '');
        $keterangan = $item['keterangan'] ?? '';
        $jenis_transaksi = trim($item['jenis_transaksi'] ?? '');
        $waktu = $item['waktu'] ?? '';
        $nama_kegiatan = extractNamaKegiatan($keterangan);

        if ($id_anggota === '' || $nama_kegiatan === null || $waktu === '') {
            continue;
        }

        // unik per orang + kegiatan
        $key = $id_anggota . '||' . $nama_kegiatan;

        // simpan transaksi paling terbaru
        if (
            !isset($status_terakhir[$key]) ||
            strtotime($waktu) > strtotime($status_terakhir[$key]['waktu'])
        ) {
            $status_terakhir[$key] = [
                'id_anggota' => $id_anggota,
                'nama_kegiatan' => $nama_kegiatan,
                'jenis_transaksi' => $jenis_transaksi,
                'waktu' => $waktu
            ];
        }
    }

    $hasil = [];

    foreach ($status_terakhir as $data) {
        if ($data['jenis_transaksi'] === 'Keluar Gudang') {
            $nama_kegiatan = $data['nama_kegiatan'];

            if (!isset($hasil[$nama_kegiatan])) {
                $hasil[$nama_kegiatan] = 0;
            }

            // tambah 1 orang, bukan tambah item
            $hasil[$nama_kegiatan]++;
        }
    }

    arsort($hasil);
    return $hasil;
}

// Ambil data ukuran berdasarkan keyword nama barang
function ambilUkuran($logistik, $keyword) {
    $hasil = [];

    foreach ($logistik as $item) {
        $nama = strtoupper($item['nama_barang'] ?? '');
        if (strpos($nama, strtoupper($keyword)) !== false) {
            $hasil[] = [
                'nama_barang' => $item['nama_barang'] ?? '-',
                'kategori' => $item['kategori'] ?? '-',
                'total_stok' => $item['total_stok'] ?? 0,
                'stok_tersedia' => $item['stok_tersedia'] ?? 0
            ];
        }
    }

    return $hasil;
}

$kegiatan_aktif = getKegiatanAktif($riwayat);

$data_baju   = ambilUkuran($logistik, 'BAJU');
$data_celana = ambilUkuran($logistik, 'CELANA');
$data_sepatu = ambilUkuran($logistik, 'SEPATU');
$data_topi   = ambilUkuran($logistik, 'TOPI');

function totalStokKelompok($data) {
    $total = 0;
    foreach ($data as $item) {
        $total += (int)($item['stok_tersedia'] ?? 0);
    }
    return $total;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&family=Inter:wght@400;500;600;700;800&family=Noto+Sans:wght@400;500;700;900" rel="stylesheet" />
    <title>Beranda - Logistik Brimob</title>
    <style>
        body { font-family: 'Inter', 'Noto Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 flex flex-col md:flex-row h-screen overflow-hidden">

<?php include 'sidebar.php'; ?>

<main class="flex-1 overflow-y-auto">
    <div class="p-4 md:p-8">
        
        <!-- Header -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-blue-900 to-slate-800 text-white shadow-xl mb-8">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white rounded-full"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-cyan-300 rounded-full"></div>
            </div>
            <div class="relative p-6 md:p-8">
                <p class="text-blue-200 text-sm font-medium uppercase tracking-wider">Dashboard Logistik</p>
                <h1 class="text-2xl md:text-4xl font-extrabold mt-2">Logistik Kompi 3 Yon B Por</h1>
                <p class="text-blue-100 mt-3 max-w-2xl text-sm md:text-base">
                    Pantau kondisi inventaris, kegiatan yang sedang berjalan, dan ketersediaan perlengkapan personil dalam satu tampilan.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <div class="bg-white/10 backdrop-blur px-4 py-2 rounded-xl border border-white/10 text-sm">
                        Total Personil: <span class="font-bold"><?= $total_anggota ?></span>
                    </div>
                    <div class="bg-white/10 backdrop-blur px-4 py-2 rounded-xl border border-white/10 text-sm">
                        Senjata di Gudang: <span class="font-bold"><?= $senjata_gudang ?></span>
                    </div>
                    <div class="bg-white/10 backdrop-blur px-4 py-2 rounded-xl border border-white/10 text-sm">
                        Kegiatan Aktif: <span class="font-bold"><?= count($kegiatan_aktif) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-bold tracking-widest uppercase text-slate-400">Total Personil</p>
                        <h3 class="text-3xl font-extrabold text-slate-800 mt-3"><?= $total_anggota ?></h3>
                        <p class="text-sm text-slate-500 mt-1">Anggota terdaftar</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.653-.084-1.287-.24-1.891M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.653.084-1.287.24-1.891m0 0a5.002 5.002 0 019.52 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM5 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-bold tracking-widest uppercase text-slate-400">Senjata Gudang</p>
                        <h3 class="text-3xl font-extrabold text-slate-800 mt-3"><?= $senjata_gudang ?></h3>
                        <p class="text-sm text-slate-500 mt-1">Siap digunakan</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center text-emerald-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622C17.176 19.29 21 14.591 21 9c0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-bold tracking-widest uppercase text-slate-400">Senjata Keluar</p>
                        <h3 class="text-3xl font-extrabold text-slate-800 mt-3"><?= $senjata_keluar ?></h3>
                        <p class="text-sm text-slate-500 mt-1">Sedang dipakai</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-100 flex items-center justify-center text-amber-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:shadow-md transition">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-bold tracking-widest uppercase text-slate-400">Katalog Logistik</p>
                        <h3 class="text-3xl font-extrabold text-slate-800 mt-3"><?= $total_logistik ?></h3>
                        <p class="text-sm text-slate-500 mt-1">Jenis barang</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-purple-100 flex items-center justify-center text-purple-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kegiatan aktif + Ringkasan perlengkapan -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
            <!-- Kegiatan aktif -->
            <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-slate-800 text-lg">Kegiatan Yang Sedang Berjalan</h2>
                        <p class="text-sm text-slate-500">Hanya kegiatan yang belum selesai ditampilkan di sini.</p>
                    </div>
                    <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full">
                        <?= count($kegiatan_aktif) ?> aktif
                    </span>
                </div>

                <div class="p-6">
                    <?php if (!empty($kegiatan_aktif)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($kegiatan_aktif as $nama => $jumlah): ?>
                                <div class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-sky-50 p-5 hover:shadow-sm transition">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-slate-500">Kegiatan Aktif</p>
                                            <h3 class="text-lg font-bold text-slate-800 mt-1"><?= htmlspecialchars($nama) ?></h3>
                                        </div>
                                        <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-extrabold text-lg">
                                            <?= $jumlah ?>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-700 bg-emerald-100 px-3 py-1 rounded-full">
                                            <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                                            Sedang berjalan
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <h3 class="font-bold text-slate-700 text-lg">Tidak ada kegiatan aktif</h3>
                            <p class="text-sm text-slate-500 mt-1">Semua kegiatan sudah selesai atau belum ada transaksi keluar terbaru.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ringkasan perlengkapan -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="font-bold text-slate-800 text-lg">Ringkasan Ukuran</h2>
                    <p class="text-sm text-slate-500">Ketersediaan perlengkapan personil.</p>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Baju</p>
                            <h3 class="font-bold text-slate-800">Ukuran Baju</h3>
                        </div>
                        <span class="text-lg font-extrabold text-blue-700"><?= totalStokKelompok($data_baju) ?></span>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Celana</p>
                            <h3 class="font-bold text-slate-800">Ukuran Celana</h3>
                        </div>
                        <span class="text-lg font-extrabold text-emerald-700"><?= totalStokKelompok($data_celana) ?></span>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Sepatu</p>
                            <h3 class="font-bold text-slate-800">Ukuran Sepatu</h3>
                        </div>
                        <span class="text-lg font-extrabold text-amber-700"><?= totalStokKelompok($data_sepatu) ?></span>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Topi</p>
                            <h3 class="font-bold text-slate-800">Ukuran Topi</h3>
                        </div>
                        <span class="text-lg font-extrabold text-purple-700"><?= totalStokKelompok($data_topi) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail ukuran -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            $sections = [
                ['title' => 'Ukuran Baju', 'data' => $data_baju, 'color' => 'blue'],
                ['title' => 'Ukuran Celana', 'data' => $data_celana, 'color' => 'emerald'],
                ['title' => 'Ukuran Sepatu', 'data' => $data_sepatu, 'color' => 'amber'],
                ['title' => 'Ukuran Topi', 'data' => $data_topi, 'color' => 'purple'],
            ];
            ?>

            <?php foreach ($sections as $section): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                        <h2 class="font-bold text-slate-800 text-lg"><?= $section['title'] ?></h2>
                    </div>

                    <div class="p-5">
                        <?php if (count($section['data']) > 0): ?>
                            <div class="space-y-3">
                                <?php foreach ($section['data'] as $item): ?>
                                    <div class="rounded-xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 class="font-bold text-slate-800"><?= htmlspecialchars($item['nama_barang']) ?></h3>
                                                <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($item['kategori']) ?></p>
                                            </div>
                                            <div class="text-right min-w-[110px]">
                                                <p class="text-xs text-slate-500">Total</p>
                                                <p class="font-bold text-slate-800"><?= $item['total_stok'] ?></p>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                                                <span>Stok tersedia</span>
                                                <span><?= $item['stok_tersedia'] ?> / <?= $item['total_stok'] ?></span>
                                            </div>
                                            <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                                                <div
                                                    class="h-2.5 rounded-full
                                                        <?= $section['color'] === 'blue' ? 'bg-blue-600' : '' ?>
                                                        <?= $section['color'] === 'emerald' ? 'bg-emerald-600' : '' ?>
                                                        <?= $section['color'] === 'amber' ? 'bg-amber-500' : '' ?>
                                                        <?= $section['color'] === 'purple' ? 'bg-purple-600' : '' ?>"
                                                    style="width: <?= ($item['total_stok'] > 0) ? max(0, min(100, ($item['stok_tersedia'] / $item['total_stok']) * 100)) : 0 ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10">
                                <div class="w-14 h-14 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-3">
                                    <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V7a2 2 0 00-2-2h-3V3H9v2H6a2 2 0 00-2 2v6m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"></path>
                                    </svg>
                                </div>
                                <p class="font-semibold text-slate-600">Data belum tersedia</p>
                                <p class="text-sm text-slate-500 mt-1"><?= strtolower($section['title']) ?> belum ditemukan di logistik.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</main>
</body>
</html>