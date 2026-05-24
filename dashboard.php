<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: signin.php");
    exit;
}

require_once 'class/LogistikDB.php';
$db = new LogistikDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $db->prosesTransaksiSenjata($_POST['nomor_seri'], $_POST['id_anggota'], $_POST['aksi'], $_POST['keterangan']);
    header("Location: dashboard.php?nrp=" . urlencode($_POST['nrp_search']) . "&sukses=1");
    exit;
}

// LOGIKA PENCARIAN PETUGAS
$search_nrp = isset($_GET['nrp']) ? trim($_GET['nrp']) : '';
$data_pencarian = ['anggota' => null, 'senjata' => null];

if ($search_nrp != '') {
    $anggota_data = json_decode(file_get_contents('data/anggota.json'), true) ?? [];
    $exact_nrp = null;

    foreach ($anggota_data as $a) {
        if ($a['nrp'] === $search_nrp) { $exact_nrp = $a['nrp']; break; }
        if (strtolower($a['nama']) === strtolower($search_nrp)) { $exact_nrp = $a['nrp']; break; }
    }
    if($exact_nrp === null){
        foreach ($anggota_data as $a) {
             if (strpos(strtolower($a['nama']), strtolower($search_nrp)) !== false) {
                 $exact_nrp = $a['nrp']; break;
             }
        }
    }

    if ($exact_nrp !== null) {
        $data_pencarian = $db->cariAnggotaDanSenjata($exact_nrp);
    }
}

$anggota = $data_pencarian['anggota'];
$senjata = $data_pencarian['senjata'];

// LOGIKA FILTER TANGGAL DAN PERSONIL DI LOG DASHBOARD
$semua_riwayat = json_decode(file_get_contents('data/riwayat.json'), true) ?? [];
$filter_start = isset($_GET['filter_start']) ? $_GET['filter_start'] : '';
$filter_end = isset($_GET['filter_end']) ? $_GET['filter_end'] : '';

$riwayat_filtered = $semua_riwayat;

// 1. Filter Eksklusif: Hanya tampilkan log orang yang sedang dicari
if ($anggota !== null) {
    $id_anggota_dicari = $anggota['id_anggota'];
    $riwayat_filtered = array_filter($riwayat_filtered, function($log) use ($id_anggota_dicari) {
        return (isset($log['id_anggota']) && $log['id_anggota'] == $id_anggota_dicari);
    });
}

// 2. Filter Berdasarkan Tanggal
if ($filter_start !== '' && $filter_end !== '') {
    $riwayat_filtered = array_filter($riwayat_filtered, function($log) use ($filter_start, $filter_end) {
        $waktu_log = date('Y-m-d', strtotime($log['waktu']));
        return ($waktu_log >= $filter_start && $waktu_log <= $filter_end);
    });
}

// Jika sedang melihat profil orang, tampilkan maksimal 50 riwayat dia. Jika tidak, tampilkan 15 terbaru umum.
if ($anggota !== null || ($filter_start !== '' && $filter_end !== '')) {
    $riwayat_tampil = array_slice($riwayat_filtered, 0, 50);
} else {
    $riwayat_tampil = array_slice($riwayat_filtered, 0, 15);
}

$semua_anggota = json_decode(file_get_contents('data/anggota.json'), true) ?? [];
$json_anggota = json_encode($semua_anggota);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B600%3B700&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" rel="stylesheet" />
    <title>Pos Penjagaan - Logistik Brimob</title>
    <style>
        body { font-family: 'Inter', 'Noto Sans', sans-serif; }
        .search-scroll::-webkit-scrollbar { width: 6px; }
        .search-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .search-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col md:flex-row h-screen overflow-hidden">
  
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-8 overflow-y-auto w-full relative">
    
    <div class="mb-6 md:mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-2">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Pos Penjagaan Gudang</h1>
            <p class="text-sm md:text-base text-gray-500 mt-1">Selamat bertugas, <?= htmlspecialchars($_SESSION['nama_petugas']) ?>.</p>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white p-4 md:p-6 shadow-sm border border-gray-100 relative overflow-visible">
      <form id="form-pencarian" method="GET" action="" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 md:gap-4 w-full">
        <input type="hidden" name="filter_start" value="<?= $filter_start ?>">
        <input type="hidden" name="filter_end" value="<?= $filter_end ?>">
        
        <div class="w-full sm:flex-1 relative">
          <label class="mb-2 block text-sm font-bold text-gray-600 uppercase tracking-wider">Cari Data Petugas / Peminjam</label>
          <input type="text" name="nrp" id="input-cari-petugas" value="<?= htmlspecialchars($search_nrp) ?>" placeholder="Scan NRP atau Ketik Nama Lengkap..." class="block w-full rounded-md border border-gray-300 p-2.5 md:p-3 text-base md:text-lg font-bold focus:ring-blue-500 focus:border-blue-500 shadow-inner" autocomplete="off" autofocus onkeyup="cariPetugasLive(this.value)">
          
          <div id="dropdown-petugas" class="absolute z-50 w-full bg-white border border-gray-200 shadow-2xl max-h-56 overflow-y-auto hidden rounded-md mt-1 search-scroll divide-y divide-gray-100 left-0"></div>
        </div>
        <button type="submit" class="w-full sm:w-auto rounded-md bg-blue-600 px-8 py-2.5 md:py-3.5 text-sm font-bold text-white hover:bg-blue-700 transition-colors shadow-sm">Temukan Data</button>
      </form>
    </div>

    <?php if ($search_nrp != ''): ?>
      <div class="mb-8 rounded-lg bg-white p-4 md:p-6 shadow-sm border border-gray-100">
        <?php if ($anggota && $senjata): ?>
          <div class="flex flex-col lg:flex-row gap-6">
            <div class="flex-1">
              <h3 class="text-xl md:text-2xl font-bold text-gray-800 uppercase"><?= $anggota['nama'] ?></h3>
              <p class="text-sm font-medium text-gray-500 mt-1">NRP: <span class="text-gray-800"><?= $anggota['nrp'] ?></span> | <span class="text-gray-800"><?= $anggota['satuan'] ?></span></p>
              
              <div class="mt-5 rounded-md bg-blue-50/50 p-4 md:p-5 border border-blue-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Inventaris Organik</p>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-white rounded-lg border border-gray-200 shadow-sm hidden sm:block">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div>
                        <p class="text-lg md:text-xl font-bold text-blue-700"><?= $senjata['jenis_senjata'] ?></p>
                        <p class="text-sm text-gray-500 font-mono mt-0.5">SN: <?= $senjata['nomor_seri'] ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-blue-100/50">
                  <?php if ($senjata['status_lokasi'] == 'Di Gudang'): ?>
                    <span class="inline-flex items-center rounded bg-green-100 px-2.5 py-1 text-xs font-bold text-green-800 border border-green-200">● DI GUDANG</span>
                  <?php else: ?>
                    <span class="inline-flex items-center rounded bg-yellow-100 px-2.5 py-1 text-xs font-bold text-yellow-800 border border-yellow-200">● DIBAWA BERTUGAS</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="w-full lg:w-1/3 bg-gray-50 p-4 md:p-5 rounded-md border border-gray-200">
              <form id="form-transaksi" method="POST" action="">
                <input type="hidden" name="nomor_seri" value="<?= $senjata['nomor_seri'] ?>">
                <input type="hidden" name="id_anggota" value="<?= $anggota['id_anggota'] ?>">
                <input type="hidden" name="nrp_search" value="<?= $anggota['nrp'] ?>"> 
                <input type="hidden" name="aksi" id="input_aksi" value="">
                
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Keterangan Tugas:</label>
                <input type="text" name="keterangan" class="w-full rounded border border-gray-300 p-2.5 text-sm mb-4 focus:ring-blue-500 focus:border-blue-500" placeholder="Opsional...">

                <?php if ($senjata['status_lokasi'] == 'Di Gudang'): ?>
                  <button type="button" onclick="konfirmasiTransaksi('keluar')" class="w-full rounded-md bg-red-600 py-3 text-sm font-bold text-white hover:bg-red-700 transition-colors shadow-sm">KELUARKAN SENJATA</button>
                <?php else: ?>
                  <button type="button" onclick="konfirmasiTransaksi('masuk')" class="w-full rounded-md bg-green-600 py-3 text-sm font-bold text-white hover:bg-green-700 transition-colors shadow-sm">TERIMA (MASUK GUDANG)</button>
                <?php endif; ?>
              </form>
            </div>
          </div>
        <?php else: ?>
          <script>
            document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'error', title: 'Data Tidak Ditemukan', text: 'Pastikan Nama/NRP benar atau senjata belum didaftarkan.', confirmButtonColor: '#3085d6' }); });
          </script>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div>
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-end mb-3 md:mb-4 gap-3">
            <h2 class="text-base md:text-lg font-bold text-gray-800">
                <?php if ($anggota !== null): ?>
                    Log Riwayat: <span class="text-blue-700 uppercase"><?= htmlspecialchars($anggota['nama']) ?></span> <?= $filter_start ? "($filter_start s.d $filter_end)" : "" ?>
                <?php else: ?>
                    Log Pergerakan Keseluruhan <?= $filter_start ? "($filter_start s.d $filter_end)" : "Terbaru" ?>
                <?php endif; ?>
            </h2>
            
            <form method="GET" action="" class="flex items-center gap-2">
                <input type="hidden" name="nrp" value="<?= htmlspecialchars($search_nrp) ?>">
                <input type="date" name="filter_start" value="<?= $filter_start ?>" class="rounded-md border-gray-300 p-1.5 text-xs focus:ring-blue-500" required>
                <span class="text-gray-400 text-xs font-bold">-</span>
                <input type="date" name="filter_end" value="<?= $filter_end ?>" class="rounded-md border-gray-300 p-1.5 text-xs focus:ring-blue-500" required>
                <button type="submit" class="bg-gray-800 hover:bg-black text-white px-3 py-1.5 rounded font-bold text-xs">Filter</button>
                <?php if($filter_start): ?>
                    <a href="dashboard.php<?= $anggota ? '?nrp='.urlencode($anggota['nrp']) : '' ?>" class="bg-red-100 text-red-600 px-2 py-1.5 rounded font-bold text-xs hover:bg-red-200">X</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm w-full overflow-x-auto max-h-[500px]">
            <table class="w-full text-left text-sm whitespace-nowrap min-w-max relative">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider sticky top-0 shadow-sm z-10">
                    <tr>
                        <th class="px-4 md:px-6 py-3 font-semibold">Waktu</th>
                        <?php if ($anggota === null): ?>
                            <th class="px-4 md:px-6 py-3 font-semibold text-blue-800">Personil</th>
                        <?php endif; ?>
                        <th class="px-4 md:px-6 py-3 font-semibold">Transaksi</th>
                        <th class="px-4 md:px-6 py-3 font-semibold">Item</th>
                        <th class="px-4 md:px-6 py-3 font-semibold">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(count($riwayat_tampil) > 0): foreach($riwayat_tampil as $log): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 md:px-6 py-3 md:py-4 text-gray-500 font-mono text-xs md:text-sm"><?= date('d M Y, H:i', strtotime($log['waktu'])) ?></td>
                        
                        <?php if ($anggota === null): ?>
                            <td class="px-4 md:px-6 py-3 md:py-4 font-bold text-gray-800 uppercase text-xs md:text-sm"><?= htmlspecialchars($log['nama_personil'] ?? 'TIDAK DIKENAL') ?></td>
                        <?php endif; ?>

                        <td class="px-4 md:px-6 py-3 md:py-4">
                            <span class="font-bold text-xs px-2 py-1 rounded <?= $log['jenis_transaksi'] == 'Keluar Gudang' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>">
                                <?= $log['jenis_transaksi'] == 'Keluar Gudang' ? 'KELUAR' : 'MASUK' ?>
                            </span>
                        </td>
                        <td class="px-4 md:px-6 py-3 md:py-4 text-gray-800 font-medium"><?= $log['item'] ?></td>
                        <td class="px-4 md:px-6 py-3 md:py-4 text-gray-500 text-xs md:text-sm truncate max-w-xs"><?= $log['keterangan'] ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada riwayat pergerakan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </main>

  <script>
    const dbAnggota = <?= $json_anggota ?>;

    function cariPetugasLive(keyword) {
        let box = document.getElementById('dropdown-petugas');
        keyword = keyword.toLowerCase().trim();
        
        if(keyword.length < 2) { box.classList.add('hidden'); return; }

        let html = '';
        let filterData = dbAnggota.filter(a => a.nama.toLowerCase().includes(keyword) || a.nrp.includes(keyword));

        filterData.forEach(a => {
            html += `
            <div class="p-3 cursor-pointer hover:bg-blue-50 transition-colors flex items-center justify-between" onclick="pilihPetugas('${a.nrp}')">
                <div>
                    <p class="text-sm font-bold text-gray-800 uppercase">${a.nama}</p>
                    <p class="text-[10px] font-medium text-gray-500 mt-0.5">NRP: <span class="font-mono">${a.nrp}</span> | ${a.satuan}</p>
                </div>
            </div>`;
        });

        if(html === '') { html = '<div class="p-4 text-sm text-center text-red-500 font-medium">Petugas tidak terdaftar di sistem...</div>'; }
        box.innerHTML = html;
        box.classList.remove('hidden');
    }

    function pilihPetugas(nrp) {
        document.getElementById('input-cari-petugas').value = nrp;
        document.getElementById('dropdown-petugas').classList.add('hidden');
        document.getElementById('form-pencarian').submit();
    }

    document.addEventListener('click', function(e) {
        let box = document.getElementById('dropdown-petugas');
        let input = document.getElementById('input-cari-petugas');
        if (!box.contains(e.target) && e.target !== input) { box.classList.add('hidden'); }
    });

    <?php if(isset($_GET['sukses'])): ?>
    document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Status senjata telah diperbarui.', timer: 2500, showConfirmButton: false }); });
    <?php endif; ?>

    function konfirmasiTransaksi(aksi) {
        let pesan = aksi === 'keluar' ? 'Keluarkan senjata ini untuk bertugas?' : 'Terima kembali senjata ini ke gudang?';
        let warnaTombol = aksi === 'keluar' ? '#dc2626' : '#16a34a';
        let teksTombol = aksi === 'keluar' ? 'Ya, Keluarkan!' : 'Ya, Terima!';

        Swal.fire({
            title: 'Konfirmasi Pergerakan', text: pesan, icon: 'question', showCancelButton: true, confirmButtonColor: warnaTombol, cancelButtonColor: '#6b7280', confirmButtonText: teksTombol, cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('input_aksi').value = aksi;
                document.getElementById('form-transaksi').submit();
            }
        });
    }

    document.getElementById('form-pencarian').addEventListener('submit', function(e) {
        let input = document.getElementById('input-cari-petugas').value.trim();
        if(input === '') {
             e.preventDefault();
             Swal.fire('Peringatan', 'Silakan ketik atau scan NRP / Nama Petugas terlebih dahulu.', 'warning');
        }
    });
  </script>
</body>
</html>