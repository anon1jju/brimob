<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: signin.php");
    exit;
}

require_once 'class/LogistikDB.php';
$db = new LogistikDB();

$logistik_file = 'data/logistik_umum.json';
$anggota_file = 'data/anggota.json';
$riwayat_file = 'data/riwayat.json';

function redirectDenganPesan($pesan) {
    header("Location: personil.php?pesan=" . urlencode($pesan));
    exit;
}

function extractNamaKegiatan($text) {
    if (preg_match('/\[(.*?)\]/', (string)$text, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function getKegiatanAktifPerPersonil($riwayat) {
    $statusTerakhir = [];
    $hasil = [];

    foreach ($riwayat as $log) {
        $idAnggota = trim((string)($log['id_anggota'] ?? ''));
        $jenis = trim((string)($log['jenis_transaksi'] ?? ''));
        $waktu = trim((string)($log['waktu'] ?? ''));
        $keterangan = trim((string)($log['keterangan'] ?? ''));
        $namaKegiatan = extractNamaKegiatan($keterangan);

        if ($idAnggota === '' || $waktu === '' || $namaKegiatan === null || $namaKegiatan === '') {
            continue;
        }

        $key = $idAnggota . '||' . $namaKegiatan;

        if (
            !isset($statusTerakhir[$key]) ||
            strtotime($waktu) > strtotime($statusTerakhir[$key]['waktu'])
        ) {
            $statusTerakhir[$key] = [
                'id_anggota' => $idAnggota,
                'nama_kegiatan' => $namaKegiatan,
                'jenis_transaksi' => $jenis,
                'waktu' => $waktu
            ];
        }
    }

    foreach ($statusTerakhir as $item) {
        if ($item['jenis_transaksi'] === 'Keluar Gudang') {
            $hasil[$item['id_anggota']] = $item['nama_kegiatan'];
        }
    }

    return $hasil;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_personil'])) {
    $logistik_data = json_decode(file_get_contents($logistik_file), true) ?? [];
    $anggota_data = json_decode(file_get_contents($anggota_file), true) ?? [];
    $riwayat_data = json_decode(file_get_contents($riwayat_file), true) ?? [];

    $nama_input = trim($_POST['nama'] ?? '');
    $nrp_input = trim($_POST['nrp'] ?? '');
    $satuan_input = trim($_POST['satuan'] ?? '');

    $pt_ids = $_POST['pt_id'] ?? [];
    $pt_qtys = $_POST['pt_qty'] ?? [];

    // Validasi stok pinjaman tetap SEBELUM simpan anggota
    if (is_array($pt_ids) && is_array($pt_qtys)) {
        for ($i = 0; $i < count($pt_ids); $i++) {
            $pt_id = trim($pt_ids[$i] ?? '');
            $pt_qty = (int)($pt_qtys[$i] ?? 0);

            if ($pt_id === '' || $pt_qty <= 0) {
                continue;
            }

            $barang_ditemukan = false;
            foreach ($logistik_data as $l) {
                if (($l['id_barang'] ?? '') == $pt_id) {
                    $barang_ditemukan = true;
                    $stok_tersedia = (int)($l['stok_tersedia'] ?? 0);

                    if ($stok_tersedia < $pt_qty) {
                        redirectDenganPesan('stok_tidak_cukup');
                    }
                    break;
                }
            }

            if (!$barang_ditemukan) {
                redirectDenganPesan('barang_tidak_ditemukan');
            }
        }
    }

    $id_baru = $db->tambahAnggota($nama_input, $nrp_input, $satuan_input);

    if (!empty($_POST['nomor_seri']) && !empty($_POST['jenis_senjata'])) {
        $db->tambahSenjata($_POST['nomor_seri'], $_POST['jenis_senjata'], $id_baru);
    }

    $waktu = date('Y-m-d H:i:s');
    $tx_id = "TRX-PT-" . time();
    $new_pt = [];

    if (is_array($pt_ids) && is_array($pt_qtys)) {
        for ($i = 0; $i < count($pt_ids); $i++) {
            $pt_id = trim($pt_ids[$i] ?? '');
            $pt_qty = (int)($pt_qtys[$i] ?? 0);

            if ($pt_id === '' || $pt_qty <= 0) {
                continue;
            }

            foreach ($logistik_data as &$l) {
                if (($l['id_barang'] ?? '') == $pt_id) {
                    $l['stok_tersedia'] -= $pt_qty;

                    $new_pt[] = [
                        'id_barang' => $pt_id,
                        'nama_barang' => $l['nama_barang'],
                        'qty' => $pt_qty
                    ];

                    array_unshift($riwayat_data, [
                        "id_transaksi" => $tx_id,
                        "waktu" => $waktu,
                        "id_anggota" => $id_baru,
                        "nama_personil" => $nama_input,
                        "jenis_transaksi" => "Keluar Gudang",
                        "item" => $pt_qty . "x " . $l['nama_barang'],
                        "keterangan" => "[Pinjaman Tetap]"
                    ]);
                    break;
                }
            }
            unset($l);
        }
    }

    foreach ($anggota_data as &$a) {
        if (($a['id_anggota'] ?? '') == $id_baru) {
            $a['pangkat'] = $_POST['pangkat'] ?? '';
            $a['size_baju'] = $_POST['size_baju'] ?? '';
            $a['size_celana'] = $_POST['size_celana'] ?? '';
            $a['size_tutup_kepala'] = $_POST['size_tutup_kepala'] ?? '';
            $a['size_sepatu'] = $_POST['size_sepatu'] ?? '';
            $a['no_hp'] = $_POST['no_hp'] ?? '';
            $a['pinjaman_tetap'] = $new_pt;
            break;
        }
    }
    unset($a);

    file_put_contents($logistik_file, json_encode($logistik_data, JSON_PRETTY_PRINT));
    file_put_contents($anggota_file, json_encode($anggota_data, JSON_PRETTY_PRINT));
    file_put_contents($riwayat_file, json_encode($riwayat_data, JSON_PRETTY_PRINT));

    header("Location: personil.php?pesan=tambah_sukses");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_personil'])) {
    $logistik_data = json_decode(file_get_contents($logistik_file), true) ?? [];
    $anggota_data = json_decode(file_get_contents($anggota_file), true) ?? [];
    $riwayat_data = json_decode(file_get_contents($riwayat_file), true) ?? [];

    $id_anggota = $_POST['id_anggota'] ?? '';

    $db->updateAnggotaDanSenjata(
        $id_anggota,
        $_POST['nama'],
        $_POST['nrp'],
        $_POST['satuan'],
        $_POST['jenis_senjata'],
        $_POST['nomor_seri']
    );

    $old_pt = [];
    foreach ($anggota_data as $a) {
        if (($a['id_anggota'] ?? '') == $id_anggota) {
            $old_pt = (isset($a['pinjaman_tetap']) && is_array($a['pinjaman_tetap'])) ? $a['pinjaman_tetap'] : [];
            break;
        }
    }

    // 1. Simulasikan pengembalian stok lama dulu
    foreach ($old_pt as $opt) {
        foreach ($logistik_data as &$l) {
            if (($l['id_barang'] ?? '') == ($opt['id_barang'] ?? '')) {
                $l['stok_tersedia'] += (int)($opt['qty'] ?? 0);
                if ($l['stok_tersedia'] > (int)($l['total_stok'] ?? 0)) {
                    $l['stok_tersedia'] = (int)($l['total_stok'] ?? 0);
                }
                break;
            }
        }
        unset($l);
    }

    $pt_ids = $_POST['pt_id'] ?? [];
    $pt_qtys = $_POST['pt_qty'] ?? [];

    // 2. Validasi stok baru setelah simulasi pengembalian
    if (is_array($pt_ids) && is_array($pt_qtys)) {
        for ($i = 0; $i < count($pt_ids); $i++) {
            $pt_id = trim($pt_ids[$i] ?? '');
            $pt_qty = (int)($pt_qtys[$i] ?? 0);

            if ($pt_id === '' || $pt_qty <= 0) {
                continue;
            }

            $barang_ditemukan = false;
            foreach ($logistik_data as $l) {
                if (($l['id_barang'] ?? '') == $pt_id) {
                    $barang_ditemukan = true;
                    $stok_tersedia = (int)($l['stok_tersedia'] ?? 0);

                    if ($stok_tersedia < $pt_qty) {
                        redirectDenganPesan('stok_tidak_cukup');
                    }
                    break;
                }
            }

            if (!$barang_ditemukan) {
                redirectDenganPesan('barang_tidak_ditemukan');
            }
        }
    }

    // 3. Potong stok baru
    $new_pt = [];
    foreach ($logistik_data as &$l) {
        foreach ($pt_ids as $idx => $pt_id_raw) {
            $pt_id = trim($pt_id_raw ?? '');
            $pt_qty = (int)($pt_qtys[$idx] ?? 0);

            if ($pt_id !== '' && $pt_qty > 0 && ($l['id_barang'] ?? '') == $pt_id) {
                $l['stok_tersedia'] -= $pt_qty;
                $new_pt[] = [
                    'id_barang' => $pt_id,
                    'nama_barang' => $l['nama_barang'],
                    'qty' => $pt_qty
                ];
            }
        }
    }
    unset($l);

    foreach ($anggota_data as &$a) {
        if (($a['id_anggota'] ?? '') == $id_anggota) {
            $a['pangkat'] = $_POST['pangkat'] ?? '';
            $a['size_baju'] = $_POST['size_baju'] ?? '';
            $a['size_celana'] = $_POST['size_celana'] ?? '';
            $a['size_tutup_kepala'] = $_POST['size_tutup_kepala'] ?? '';
            $a['size_sepatu'] = $_POST['size_sepatu'] ?? '';
            $a['no_hp'] = $_POST['no_hp'] ?? '';
            $a['pinjaman_tetap'] = $new_pt;
            break;
        }
    }
    unset($a);

    file_put_contents($logistik_file, json_encode($logistik_data, JSON_PRETTY_PRINT));
    file_put_contents($anggota_file, json_encode($anggota_data, JSON_PRETTY_PRINT));

    header("Location: personil.php?pesan=edit_sukses");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_personil'])) {
    // Kembalikan stok pinjaman tetap jika personil dihapus
    $anggota_data = json_decode(file_get_contents($anggota_file), true) ?? [];
    $logistik_data = json_decode(file_get_contents($logistik_file), true) ?? [];
    $id_hapus = $_POST['id_anggota'] ?? '';

    foreach ($anggota_data as $a) {
        if (($a['id_anggota'] ?? '') == $id_hapus) {
            if (isset($a['pinjaman_tetap']) && is_array($a['pinjaman_tetap'])) {
                foreach ($a['pinjaman_tetap'] as $opt) {
                    $id_barang = $opt['id_barang'] ?? '';
                    $qty_kembali = (int)($opt['qty'] ?? 0);

                    if ($id_barang === '' || $qty_kembali <= 0) {
                        continue;
                    }

                    foreach ($logistik_data as &$l) {
                        if (($l['id_barang'] ?? '') == $id_barang) {
                            $stok_tersedia = (int)($l['stok_tersedia'] ?? 0);
                            $total_stok = (int)($l['total_stok'] ?? 0);

                            $stok_baru = $stok_tersedia + $qty_kembali;
                            $l['stok_tersedia'] = ($stok_baru > $total_stok) ? $total_stok : $stok_baru;
                            break;
                        }
                    }
                    unset($l);
                }
            }
            break;
        }
    }

    file_put_contents($logistik_file, json_encode($logistik_data, JSON_PRETTY_PRINT));

    $db->hapusAnggota($id_hapus);
    header("Location: personil.php?pesan=hapus_sukses");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_rusak'])) {
    $db->setStatusSenjata($_POST['nomor_seri'], $_POST['status_baru']);
    header("Location: personil.php?pesan=status_sukses");
    exit;
}

$semua_personil = $db->getAllPersonil();
$semua_riwayat = json_decode(file_get_contents('data/riwayat.json'), true) ?? [];
$json_riwayat = json_encode($semua_riwayat);

$data_logistik = json_decode(file_get_contents('data/logistik_umum.json'), true) ?? [];
$json_logistik = json_encode($data_logistik);

// LACAK BARANG DIPINJAM (DEEP TRACKING)
foreach ($semua_personil as &$p) {
    $p['alasan_keluar'] = ''; 
    $p['barang_dipinjam'] = []; 
    
    if ($p['senjata'] && $p['senjata']['status_lokasi'] == 'Dibawa Bertugas') {
        $target_tx_id = null;
        foreach ($semua_riwayat as $log) {
            if ($log['jenis_transaksi'] == 'Keluar Gudang' && strpos($log['item'], '(' . $p['senjata']['nomor_seri'] . ')') !== false) {
                $target_tx_id = $log['id_transaksi'];
                if (preg_match('/\[(.*?)\]/', $log['keterangan'], $matches)) {
                    $p['alasan_keluar'] = $matches[1];
                } else {
                    $p['alasan_keluar'] = 'Bertugas';
                }
                break; 
            }
        }
        
        if ($target_tx_id) {
            foreach ($semua_riwayat as $log) {
                if ($log['id_transaksi'] == $target_tx_id && $log['jenis_transaksi'] == 'Keluar Gudang') {
                    $item_name = str_replace('Senjata: ', '', $log['item']); 
                    $p['barang_dipinjam'][] = $item_name;
                }
            }
        }
    }
}
unset($p); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B600%3B700&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" rel="stylesheet" />
    <title>Data Personil - Logistik Brimob</title>
    <style>
        body { font-family: 'Inter', 'Noto Sans', sans-serif; }
        .custom-scroll::-webkit-scrollbar { height: 6px; width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col md:flex-row h-screen overflow-hidden">
  
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-6 relative w-full flex flex-col h-full overflow-hidden">
    
    <div class="mb-4 md:mb-6 flex flex-col lg:flex-row lg:justify-between lg:items-end gap-3 flex-shrink-0">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Manajemen Data Personil</h1>
            <p class="text-sm md:text-base text-gray-500 mt-1">Daftar profil anggota, logistik perorangan, dan pelacakan senjata.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="pencarian-personil" onkeyup="cariPersonil()" class="w-full pl-10 pr-4 py-2 rounded-md border border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white shadow-sm" placeholder="Cari Nama / NRP...">
            </div>
            
            <button onclick="toggleModal('modal-tambah')" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-colors whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Personil
            </button>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm w-full flex-1 flex flex-col min-h-0 overflow-hidden">
        <div class="overflow-auto custom-scroll flex-1 w-full h-full">
            <table class="w-full text-left text-sm whitespace-nowrap min-w-max relative" id="tabel-utama-personil">
                <thead class="bg-gray-50/90 backdrop-blur-sm border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="px-4 md:px-5 py-3 font-semibold">Nama / NRP</th>
                        <th class="px-4 md:px-5 py-3 font-semibold">Satuan / Pangkat</th>
                        <th class="px-4 md:px-5 py-3 font-semibold">Inventaris Organik</th>
                        <th class="px-4 md:px-5 py-3 font-semibold text-center w-64">Status Gudang</th>
                        <th class="px-4 md:px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="body-tabel-personil">
                    <?php if(count($semua_personil) > 0): foreach($semua_personil as $p): 
                        $pkt = $p['pangkat'] ?? '';
                        $sz_baju = $p['size_baju'] ?? '';
                        $sz_celana = $p['size_celana'] ?? '';
                        $sz_tk = $p['size_tutup_kepala'] ?? '';
                        $sz_sepatu = $p['size_sepatu'] ?? '';
                        $hp = $p['no_hp'] ?? '';
                        $pt_array = (isset($p['pinjaman_tetap']) && is_array($p['pinjaman_tetap'])) ? $p['pinjaman_tetap'] : [];
                        $pt_json = htmlspecialchars(json_encode($pt_array), ENT_QUOTES, 'UTF-8');

                        $alasan = $p['alasan_keluar'] ?? '';
                        $brg_dipinjam_str = implode('||', $p['barang_dipinjam']); 
                    ?>
                    <tr class="hover:bg-blue-50/30 transition-colors baris-data">
                        <td class="px-4 md:px-5 py-2.5">
                            <p class="font-bold text-blue-600 hover:text-blue-800 cursor-pointer underline-offset-2 hover:underline transition-colors nama-anggota" 
                               onclick="bukaProfil('<?= $p['id_anggota'] ?>', '<?= addslashes($p['nama']) ?>', '<?= $p['nrp'] ?>', '<?= addslashes($p['satuan']) ?>', '<?= $p['senjata'] ? addslashes($p['senjata']['jenis_senjata']) : '-' ?>', '<?= $p['senjata'] ? $p['senjata']['nomor_seri'] : '-' ?>', '<?= $p['senjata'] ? $p['senjata']['status_lokasi'] : 'Kosong' ?>', '<?= addslashes($pkt) ?>', '<?= addslashes($sz_baju) ?>', '<?= addslashes($sz_celana) ?>', '<?= addslashes($sz_tk) ?>', '<?= addslashes($sz_sepatu) ?>', '<?= addslashes($hp) ?>', '<?= addslashes($alasan) ?>', '<?= addslashes($brg_dipinjam_str) ?>', '<?= $pt_json ?>')" title="Klik untuk lihat profil lengkap">
                                <?= $p['nama'] ?>
                            </p>
                            <p class="text-gray-500 text-[11px] mt-0.5 nrp-anggota">NRP: <?= $p['nrp'] ?></p>
                            <p class="text-gray-400 text-[10px] mt-1"><span class="font-bold text-gray-500">HP:</span> <?= $hp ?: '-' ?></p>
                        </td>
                        <td class="px-4 md:px-5 py-2.5">
                            <p class="text-gray-800 font-medium text-xs satuan-anggota"><?= $p['satuan'] ?: '-' ?></p>
                            <p class="text-gray-500 text-[11px] mt-0.5"><?= $pkt ?: '-' ?></p>
                        </td>
                        <td class="px-4 md:px-5 py-2.5">
                            <?php if($p['senjata']): ?>
                                <p class="font-bold text-gray-800 text-xs senjata-anggota"><?= $p['senjata']['jenis_senjata'] ?></p>
                                <p class="text-gray-500 text-[10px] font-mono mt-0.5 mb-1.5 seri-senjata">SN: <?= $p['senjata']['nomor_seri'] ?></p>
                                <a href="cetak_qr.php?id=<?= $p['senjata']['nomor_seri'] ?>&nama=<?= urlencode($p['senjata']['jenis_senjata']) ?>&tipe=senjata" target="_blank" class="inline-flex items-center gap-1 text-gray-500 hover:text-gray-800 font-medium text-[9px] px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> Cetak QR
                                </a>
                            <?php else: ?>
                                <p class="text-gray-400 italic text-[10px] mb-1">Utama: Belum didata</p>
                            <?php endif; ?>
                            
                            <?php if(count($pt_array) > 0): ?>
                                <span class="bg-blue-100 text-blue-800 text-[9px] font-bold px-1.5 py-0.5 rounded border border-blue-200 uppercase mt-1 block w-max">+ <?= count($pt_array) ?> Barang Tetap</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="px-4 md:px-5 py-2.5 text-center align-top">
                            <?php if($p['senjata']): ?>
                                <?php if ($p['senjata']['status_lokasi'] == 'Di Gudang'): ?>
                                    <span class="inline-flex rounded bg-green-100 px-2 py-1 text-[10px] font-bold text-green-800 border border-green-200 mt-1">DI GUDANG</span>
                                <?php elseif ($p['senjata']['status_lokasi'] == 'Rusak/Perbaikan'): ?>
                                    <span class="inline-flex rounded bg-red-100 px-2 py-1 text-[10px] font-bold text-red-800 border border-red-200 animate-pulse mt-1">🛠️ PERBAIKAN</span>
                                <?php else: ?>
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <span class="inline-flex rounded bg-yellow-100 px-2 py-1 text-[10px] font-bold text-yellow-800 border border-yellow-200">DI LUAR</span>
                                        <?php if($alasan): ?>
                                            <span class="text-[9px] font-bold text-yellow-600 bg-yellow-50 px-1 py-0.5 rounded border border-yellow-100 uppercase tracking-wider"><?= $alasan ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($p['barang_dipinjam'])): ?>
                                            <div class="mt-0.5 text-[9px] text-gray-500 bg-gray-50 p-1.5 rounded border border-gray-100 text-left w-full max-w-[180px] shadow-inner font-medium leading-tight">
                                                <?= implode('<br>• ', array_map('htmlspecialchars', $p['barang_dipinjam'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-300">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 md:px-5 py-2.5 text-right">
                            <?php if($p['senjata']): ?>
                                <form method="POST" action="" class="inline-block mr-1">
                                    <input type="hidden" name="toggle_rusak" value="1">
                                    <input type="hidden" name="nomor_seri" value="<?= $p['senjata']['nomor_seri'] ?>">
                                    <?php if ($p['senjata']['status_lokasi'] == 'Di Gudang'): ?>
                                        <input type="hidden" name="status_baru" value="Rusak/Perbaikan">
                                        <button type="submit" class="text-orange-600 hover:text-orange-800 font-medium text-xs px-2 py-1 bg-orange-50 border border-orange-200 rounded transition-colors" title="Tandai Rusak / Perbaikan">🛠️</button>
                                    <?php elseif ($p['senjata']['status_lokasi'] == 'Rusak/Perbaikan'): ?>
                                        <input type="hidden" name="status_baru" value="Di Gudang">
                                        <button type="submit" class="text-green-600 hover:text-green-800 font-medium text-xs px-2 py-1 bg-green-50 border border-green-200 rounded transition-colors" title="Selesai Diperbaiki">✅</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>

                            <button onclick="bukaEdit('<?= $p['id_anggota'] ?>', '<?= addslashes($p['nama']) ?>', '<?= $p['nrp'] ?>', '<?= addslashes($p['satuan']) ?>', '<?= $p['senjata'] ? addslashes($p['senjata']['jenis_senjata']) : '' ?>', '<?= $p['senjata'] ? $p['senjata']['nomor_seri'] : '' ?>', '<?= addslashes($pkt) ?>', '<?= addslashes($sz_baju) ?>', '<?= addslashes($sz_celana) ?>', '<?= addslashes($sz_tk) ?>', '<?= addslashes($sz_sepatu) ?>', '<?= addslashes($hp) ?>', '<?= $pt_json ?>')" class="text-blue-600 hover:text-blue-800 font-medium text-xs px-2 py-1 bg-blue-50 border border-blue-100 rounded hover:bg-blue-100 transition-colors">Edit</button>
                            
                            <form id="form-hapus-<?= $p['id_anggota'] ?>" method="POST" action="" class="inline-block ml-1">
                                <input type="hidden" name="hapus_personil" value="1">
                                <input type="hidden" name="id_anggota" value="<?= $p['id_anggota'] ?>">
                                <button type="button" onclick="konfirmasiHapus('form-hapus-<?= $p['id_anggota'] ?>', '<?= addslashes($p['nama']) ?>')" class="text-red-600 hover:text-red-800 font-medium text-xs px-2 py-1 bg-red-50 border border-red-100 rounded hover:bg-red-100 transition-colors">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr id="baris-kosong"><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada data personil.</td></tr>
                    <?php endif; ?>
                    
                    <tr id="baris-tidak-ditemukan" class="hidden"><td colspan="5" class="px-4 py-8 text-center text-red-500 font-medium">Pencarian tidak ditemukan.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
  </main>

  <div id="modal-tambah" class="fixed inset-0 z-50 hidden bg-black/60 flex items-center justify-center backdrop-blur-sm p-4 sm:p-0">
      <div class="bg-white rounded-lg shadow-2xl w-[95%] md:w-full max-w-2xl mx-auto overflow-hidden border border-gray-200 max-h-[90vh] flex flex-col">
          <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50 flex-shrink-0">
              <h2 class="text-base md:text-lg font-bold text-gray-800">Tambah Data Personil</h2>
              <button onclick="toggleModal('modal-tambah')" class="text-gray-400 hover:text-red-500 font-bold text-2xl leading-none">&times;</button>
          </div>
          <div class="overflow-y-auto p-4 md:p-6 flex-1 custom-scroll">
            <form method="POST" action="" id="form-tambah-personil">
                <input type="hidden" name="tambah_personil" value="1">
                
                <h3 class="text-[11px] font-bold text-blue-600 uppercase tracking-wider mb-3 border-b border-blue-100 pb-1">1. Identitas Anggota</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama" class="w-full rounded border-gray-300 text-sm p-2 focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">NRP *</label>
                        <input type="number" name="nrp" class="w-full rounded border-gray-300 text-sm p-2 focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Pangkat</label>
                        <input type="text" name="pangkat" class="w-full rounded border-gray-300 text-sm p-2 focus:border-blue-500 focus:ring-blue-500" placeholder="Bripka">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Satuan</label>
                        <input type="text" name="satuan" class="w-full rounded border-gray-300 text-sm p-2 focus:border-blue-500 focus:ring-blue-500" placeholder="Batalyon A">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nomor HP / WhatsApp</label>
                        <input type="tel" name="no_hp" class="w-full rounded border-gray-300 text-sm p-2 focus:border-blue-500 focus:ring-blue-500" placeholder="081234...">
                    </div>
                </div>

                <h3 class="text-[11px] font-bold text-blue-600 uppercase tracking-wider mb-3 border-b border-blue-100 pb-1">2. Ukuran Kaporlap</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 bg-gray-50 p-3 rounded border border-gray-200">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Size Baju</label>
                        <input type="text" name="size_baju" class="w-full rounded border-gray-300 text-sm p-2 uppercase" placeholder="L/XL">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Size Celana</label>
                        <input type="number" name="size_celana" class="w-full rounded border-gray-300 text-sm p-2" placeholder="34">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">T. Kepala</label>
                        <input type="number" name="size_tutup_kepala" class="w-full rounded border-gray-300 text-sm p-2" placeholder="56">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Size Sepatu</label>
                        <input type="number" name="size_sepatu" class="w-full rounded border-gray-300 text-sm p-2" placeholder="42">
                    </div>
                </div>

                <h3 class="text-[11px] font-bold text-blue-600 uppercase tracking-wider mb-3 border-b border-blue-100 pb-1">3. Senjata Organik (Utama)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-blue-50 border border-blue-200 rounded mb-5">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Jenis Senjata</label>
                        <input type="text" name="jenis_senjata" class="w-full rounded border-gray-300 text-sm p-2 bg-white focus:border-blue-500 focus:ring-blue-500" placeholder="SS1-V1">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nomor Seri</label>
                        <input type="text" name="nomor_seri" class="w-full rounded border-gray-300 text-sm p-2 bg-white uppercase focus:border-blue-500 focus:ring-blue-500" placeholder="A8872">
                    </div>
                </div>

                <div class="flex justify-between items-center mb-3 border-b border-blue-100 pb-1">
                    <h3 class="text-[11px] font-bold text-blue-600 uppercase tracking-wider">4. Pinjaman Tetap (Gudang Logistik)</h3>
                    <button type="button" onclick="tambahBarisPT('container-pt-tambah')" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded text-[10px] font-bold border border-blue-300">+ Tambah Barang</button>
                </div>
                <div id="container-pt-tambah" class="p-3 bg-gray-50 border border-gray-200 rounded min-h-[50px]">
                    <p class="text-xs text-gray-400 italic text-center" id="empty-pt-tambah">Tidak ada pinjaman tetap. Klik tombol di atas untuk menambah.</p>
                </div>

            </form>
          </div>
          <div class="flex justify-end gap-3 p-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
              <button type="button" onclick="toggleModal('modal-tambah')" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200 rounded transition-colors">Batal</button>
              <button type="submit" form="form-tambah-personil" class="px-4 py-2 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded transition-colors shadow-sm">Simpan</button>
          </div>
      </div>
  </div>

  <div id="modal-edit" class="fixed inset-0 z-50 hidden bg-black/60 flex items-center justify-center backdrop-blur-sm p-4 sm:p-0">
      <div class="bg-white rounded-lg shadow-2xl w-[95%] md:w-full max-w-2xl mx-auto overflow-hidden border border-gray-200 max-h-[90vh] flex flex-col">
          <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50 flex-shrink-0">
              <h2 class="text-base md:text-lg font-bold text-gray-800">Edit Data Personil</h2>
              <button onclick="toggleModal('modal-edit')" class="text-gray-400 hover:text-red-500 font-bold text-2xl leading-none">&times;</button>
          </div>
          <div class="overflow-y-auto p-4 md:p-6 flex-1 custom-scroll">
            <form method="POST" action="" id="form-edit-personil">
                <input type="hidden" name="edit_personil" value="1">
                <input type="hidden" name="id_anggota" id="edit_id_anggota">
                
                <h3 class="text-[11px] font-bold text-green-600 uppercase tracking-wider mb-3 border-b border-green-100 pb-1">1. Identitas Anggota</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama" id="edit_nama" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">NRP *</label>
                        <input type="number" name="nrp" id="edit_nrp" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Pangkat</label>
                        <input type="text" name="pangkat" id="edit_pangkat" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Satuan</label>
                        <input type="text" name="satuan" id="edit_satuan" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nomor HP / WhatsApp</label>
                        <input type="tel" name="no_hp" id="edit_no_hp" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500">
                    </div>
                </div>

                <h3 class="text-[11px] font-bold text-green-600 uppercase tracking-wider mb-3 border-b border-green-100 pb-1">2. Ukuran Kaporlap</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 bg-gray-50 p-3 rounded border border-gray-200">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Size Baju</label>
                        <input type="text" name="size_baju" id="edit_size_baju" class="w-full rounded border-gray-300 text-sm p-2 uppercase focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Size Celana</label>
                        <input type="number" name="size_celana" id="edit_size_celana" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">T. Kepala</label>
                        <input type="number" name="size_tutup_kepala" id="edit_size_tk" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Size Sepatu</label>
                        <input type="number" name="size_sepatu" id="edit_size_sepatu" class="w-full rounded border-gray-300 text-sm p-2 focus:border-green-500">
                    </div>
                </div>

                <h3 class="text-[11px] font-bold text-green-600 uppercase tracking-wider mb-3 border-b border-green-100 pb-1">3. Senjata Organik (Utama)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-green-50 border border-green-200 rounded mb-5">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Jenis Senjata</label>
                        <input type="text" name="jenis_senjata" id="edit_jenis_senjata" class="w-full rounded border-gray-300 text-sm p-2 bg-white focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Nomor Seri</label>
                        <input type="text" name="nomor_seri" id="edit_nomor_seri" class="w-full rounded border-gray-300 text-sm p-2 bg-white uppercase focus:border-green-500">
                    </div>
                </div>

                <div class="flex justify-between items-center mb-3 border-b border-green-100 pb-1">
                    <h3 class="text-[11px] font-bold text-green-600 uppercase tracking-wider">4. Pinjaman Tetap (Gudang Logistik)</h3>
                    <button type="button" onclick="tambahBarisPT('container-pt-edit')" class="bg-green-100 text-green-700 hover:bg-green-200 px-2 py-1 rounded text-[10px] font-bold border border-green-300">+ Tambah Barang</button>
                </div>
                <div id="container-pt-edit" class="p-3 bg-gray-50 border border-gray-200 rounded min-h-[50px]">
                    </div>
            </form>
          </div>
          <div class="flex justify-end gap-3 p-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
              <button type="button" onclick="toggleModal('modal-edit')" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200 rounded transition-colors">Batal</button>
              <button type="submit" form="form-edit-personil" class="px-4 py-2 text-sm font-bold text-white bg-green-600 hover:bg-green-700 rounded transition-colors shadow-sm">Simpan</button>
          </div>
      </div>
  </div>

  <div id="modal-profil" class="fixed inset-0 z-50 hidden bg-black/70 flex items-center justify-center backdrop-blur-sm p-4 sm:p-5">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-auto overflow-hidden border border-gray-200 max-h-[95vh] flex flex-col">
          
          <div class="flex justify-between items-start p-4 md:p-5 border-b border-gray-200 bg-gradient-to-r from-gray-900 to-gray-800 flex-shrink-0">
              <div class="flex gap-4 items-center">
                  <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-white font-bold text-xl border-2 border-white/30" id="profil-inisial">A</div>
                  <div>
                      <h2 class="text-lg md:text-xl font-bold text-white uppercase" id="profil-nama">Nama Anggota</h2>
                      <p class="text-gray-300 text-xs mt-0.5">NRP: <span id="profil-nrp" class="font-mono text-white">000</span> &bull; <span id="profil-satuan">Satuan</span></p>
                  </div>
              </div>
              <div class="flex items-center gap-3">
                  <button onclick="cetakProfilLaporan()" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold px-3 py-1.5 rounded flex items-center gap-1.5 transition-colors shadow">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                      <span class="hidden sm:inline">Cetak Laporan</span>
                  </button>
                  <button onclick="toggleModal('modal-profil')" class="text-gray-400 hover:text-white font-bold text-2xl leading-none">&times;</button>
              </div>
          </div>

          <div class="overflow-y-auto p-4 md:p-5 flex-1 bg-gray-50 custom-scroll flex flex-col gap-4">
              
              <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between">
                  <div class="flex-1">
                      <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Pangkat</p>
                      <p class="text-sm font-bold text-blue-800" id="profil-pangkat">-</p>
                      <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-2 mb-1">Nomor HP</p>
                      <p class="text-sm font-bold text-gray-800" id="profil-nohp">-</p>
                  </div>
                  <div class="flex-1 sm:flex-[2] border-t sm:border-t-0 sm:border-l border-gray-100 sm:pl-4 pt-2 sm:pt-0">
                      <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Ukuran Kaporlap (Perlengkapan)</p>
                      <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-gray-700">
                          <p class="border-b border-gray-50 pb-1">Baju: <span class="font-bold text-gray-900 uppercase" id="profil-baju">-</span></p>
                          <p class="border-b border-gray-50 pb-1">Celana: <span class="font-bold text-gray-900" id="profil-celana">-</span></p>
                          <p class="border-b border-gray-50 pb-1">T. Kepala: <span class="font-bold text-gray-900" id="profil-tk">-</span></p>
                          <p class="border-b border-gray-50 pb-1">Sepatu: <span class="font-bold text-gray-900" id="profil-sepatu">-</span></p>
                      </div>
                  </div>
              </div>

              <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm flex flex-col gap-3">
                  <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                      <div>
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Senjata Pegangan Organik (Utama)</p>
                          <div class="flex items-center gap-2 mt-0.5">
                              <p class="text-base font-bold text-gray-800" id="profil-senjata">SS1-V1</p>
                          </div>
                          <p class="text-xs text-gray-500 font-mono mt-0.5" id="profil-seri">SN: A123</p>
                      </div>
                      <div class="text-left sm:text-right w-full sm:w-auto">
                          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Status Gudang (Utama)</p>
                          <div id="profil-status" class="w-full sm:w-auto"></div>
                      </div>
                  </div>
                  
                  <div class="border-t border-gray-100 pt-3" id="profil-box-pinjaman-tetap">
                      <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                          Daftar Pinjaman Tetap Lainnya
                      </p>
                      <div id="profil-pinjaman-tetap" class="mt-2 text-xs font-bold text-blue-800 bg-blue-50 border border-blue-100 rounded p-2 leading-relaxed"></div>
                  </div>
              </div>

              <div class="mt-1">
                  <h3 class="text-xs font-bold text-gray-800 mb-2 flex items-center gap-1.5">
                      <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                      Riwayat Peminjaman Logistik
                  </h3>
                  <div class="flex flex-wrap gap-2 items-end bg-white p-2.5 rounded-md border border-gray-200 shadow-sm">
                      <div>
                          <label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Dari Tanggal</label>
                          <input type="date" id="filter-start" class="rounded border-gray-300 text-xs p-1 focus:ring-blue-500">
                      </div>
                      <div>
                          <label class="block text-[9px] font-bold text-gray-500 uppercase mb-1">Sampai Tanggal</label>
                          <input type="date" id="filter-end" class="rounded border-gray-300 text-xs p-1 focus:ring-blue-500">
                      </div>
                      <button onclick="terapkanFilterProfil()" class="bg-gray-800 text-white px-3 py-1 rounded text-[10px] font-bold hover:bg-black transition-colors">Terapkan</button>
                      <button onclick="resetFilterProfil()" class="bg-gray-100 text-gray-600 border border-gray-300 px-2 py-1 rounded text-[10px] font-bold hover:bg-gray-200 transition-colors">Reset</button>
                  </div>
              </div>

              <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden flex-1 flex flex-col min-h-[200px]">
                  <div class="overflow-y-auto overflow-x-auto custom-scroll flex-1">
                      <table class="w-full text-left text-sm whitespace-nowrap min-w-max">
                          <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[10px] uppercase tracking-wider sticky top-0">
                              <tr>
                                  <th class="px-3 py-2 font-semibold">Waktu Transaksi</th>
                                  <th class="px-3 py-2 font-semibold">Transaksi</th>
                                  <th class="px-3 py-2 font-semibold">Item Barang</th>
                                  <th class="px-3 py-2 font-semibold">Keterangan</th>
                              </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-100" id="tbody-profil-riwayat"></tbody>
                      </table>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <script>
      const dbRiwayat = <?= $json_riwayat ?>;
      const dbLogistik = <?= $json_logistik ?>;
      let profilAktifId = null;

      // --- LOGIKA FORM PINJAMAN TETAP (MASTER BARANG) ---
      function hapusBarisPT(btn) {
          btn.parentElement.remove();
          // Cek kalau container kosong
          let containerAdd = document.getElementById('container-pt-tambah');
          if(containerAdd && containerAdd.children.length === 1 && containerAdd.children[0].id === 'empty-pt-tambah'){
              document.getElementById('empty-pt-tambah').classList.remove('hidden');
          }
      }

      function tambahBarisPT(containerId, selectedId = '', qty = 1) {
          let container = document.getElementById(containerId);
          let emptyText = container.querySelector('#empty-pt-tambah');
          if(emptyText) emptyText.classList.add('hidden');

          let row = document.createElement('div');
          row.className = "flex gap-2 items-center mb-2 animate-[pulse_0.5s_ease-in-out_1]";
          
          let selectHtml = `<select name="pt_id[]" class="flex-1 rounded border-gray-300 text-xs p-2 focus:border-blue-500" required>
              <option value="">-- Pilih Barang dari Gudang --</option>`;
          dbLogistik.forEach(l => {
              let isSelected = (l.id_barang === selectedId) ? 'selected' : '';
              let stokInfo = isSelected ? '' : ` (Sisa Stok: ${l.stok_tersedia})`;
              selectHtml += `<option value="${l.id_barang}" ${isSelected}>${l.nama_barang}${stokInfo}</option>`;
          });
          selectHtml += `</select>`;
          
          let inputQtyHtml = `<input type="number" name="pt_qty[]" value="${qty}" min="1" class="w-16 rounded border-gray-300 text-xs p-2 text-center" required title="Kuantitas">`;
          let btnHapus = `<button type="button" onclick="hapusBarisPT(this)" class="bg-red-50 text-red-600 border border-red-200 px-2.5 py-1.5 rounded hover:bg-red-100 font-bold text-xs" title="Hapus Barang">X</button>`;
          
          row.innerHTML = selectHtml + inputQtyHtml + btnHapus;
          container.appendChild(row);
      }

      function renderPtDiEdit(pt_json_str) {
          let container = document.getElementById('container-pt-edit');
          container.innerHTML = ''; 
          if(pt_json_str === '' || pt_json_str === '[]') {
              container.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-2" id="empty-pt-edit">Tidak ada pinjaman tetap. Klik tombol di atas untuk menambah.</p>';
              return;
          }
          let pts = JSON.parse(pt_json_str);
          pts.forEach(pt => {
              tambahBarisPT('container-pt-edit', pt.id_barang, pt.qty);
          });
      }

      // --- LIVE SEARCH ---
      function cariPersonil() {
          let input = document.getElementById("pencarian-personil").value.toLowerCase();
          let barisData = document.querySelectorAll(".baris-data");
          let ditemukan = false;
          barisData.forEach(function(baris) {
              let teksNama = baris.querySelector(".nama-anggota").innerText.toLowerCase();
              let teksNrp = baris.querySelector(".nrp-anggota").innerText.toLowerCase();
              if (teksNama.includes(input) || teksNrp.includes(input)) {
                  baris.style.display = ""; ditemukan = true;
              } else { baris.style.display = "none"; }
          });
          let pesanKosong = document.getElementById("baris-tidak-ditemukan");
          if (!ditemukan && input !== "") { pesanKosong.classList.remove("hidden"); } 
          else { pesanKosong.classList.add("hidden"); }
      }

      function toggleModal(modalID){
          document.getElementById(modalID).classList.toggle('hidden');
      }

      function bukaEdit(id, nama, nrp, satuan, jenis_senjata, nomor_seri, pangkat, sz_baju, sz_celana, sz_tk, sz_sepatu, no_hp, pt_json_str) {
          document.getElementById('edit_id_anggota').value = id;
          document.getElementById('edit_nama').value = nama;
          document.getElementById('edit_nrp').value = nrp;
          document.getElementById('edit_satuan').value = satuan;
          document.getElementById('edit_pangkat').value = pangkat;
          document.getElementById('edit_size_baju').value = sz_baju;
          document.getElementById('edit_size_celana').value = sz_celana;
          document.getElementById('edit_size_tk').value = sz_tk;
          document.getElementById('edit_size_sepatu').value = sz_sepatu;
          document.getElementById('edit_no_hp').value = no_hp;
          
          document.getElementById('edit_jenis_senjata').value = jenis_senjata;
          document.getElementById('edit_nomor_seri').value = nomor_seri;

          renderPtDiEdit(pt_json_str);

          toggleModal('modal-edit');
      }

      function bukaProfil(id, nama, nrp, satuan, jenis_senjata, nomor_seri, status_lokasi, pangkat, sz_baju, sz_celana, sz_tk, sz_sepatu, no_hp, alasan_keluar, brg_dipinjam_str, pt_json_str) {
          profilAktifId = id; 
          
          document.getElementById('profil-nama').innerText = nama;
          document.getElementById('profil-nrp').innerText = nrp;
          document.getElementById('profil-satuan').innerText = satuan || '-';
          document.getElementById('profil-inisial').innerText = nama.charAt(0).toUpperCase();

          document.getElementById('profil-pangkat').innerText = pangkat || '-';
          document.getElementById('profil-nohp').innerText = no_hp || '-';
          
          document.getElementById('profil-baju').innerText = sz_baju || '-';
          document.getElementById('profil-celana').innerText = sz_celana || '-';
          document.getElementById('profil-tk').innerText = sz_tk || '-';
          document.getElementById('profil-sepatu').innerText = sz_sepatu || '-';

          document.getElementById('profil-senjata').innerText = jenis_senjata;
          document.getElementById('profil-seri').innerText = nomor_seri !== '-' ? 'SN: ' + nomor_seri : 'Belum didata';

          // RENDER PINJAMAN TETAP (DARI MASTER BARANG)
          let pts = pt_json_str ? JSON.parse(pt_json_str) : [];
          let textPT = document.getElementById('profil-pinjaman-tetap');
          if (pts.length > 0) {
              textPT.innerHTML = pts.map(pt => `• ${pt.qty}x ${pt.nama_barang}`).join('<br>');
              textPT.className = "mt-2 text-xs font-bold text-blue-800 bg-blue-50 border border-blue-100 rounded p-2 leading-relaxed";
          } else {
              textPT.innerHTML = '<span class="text-gray-400 italic font-normal">Tidak ada pinjaman tetap lainnya.</span>';
              textPT.className = "mt-2 text-xs bg-gray-50 border border-gray-100 rounded p-2 leading-relaxed";
          }

          // LOGIKA STATUS GUDANG BERIKUT DAFTAR BARANG
          let statusHtml = '';
          if(status_lokasi === 'Di Gudang') {
              statusHtml = '<span class="inline-flex rounded bg-green-100 px-3 py-1 text-[10px] font-bold text-green-800 border border-green-200 mt-1">DI GUDANG</span>';
          } else if(status_lokasi === 'Rusak/Perbaikan') {
              statusHtml = '<span class="inline-flex rounded bg-red-100 px-3 py-1 text-[10px] font-bold text-red-800 border border-red-200 animate-pulse mt-1">🛠️ PERBAIKAN</span>';
          } else if(status_lokasi === 'Kosong') {
              statusHtml = '<span class="inline-flex rounded bg-gray-100 px-3 py-1 text-[10px] font-bold text-gray-500">-</span>';
          } else {
              let textAlasan = alasan_keluar ? alasan_keluar : 'BERTUGAS';
              
              let listBarangHtml = '';
              if (brg_dipinjam_str && brg_dipinjam_str !== '') {
                  let items = brg_dipinjam_str.split('||');
                  let itemsLi = items.map(item => `• ${item}`).join('<br>');
                  listBarangHtml = `<div class="mt-1.5 text-[10px] text-gray-600 bg-gray-100 p-2 rounded border border-gray-200 w-full sm:w-auto text-left leading-relaxed shadow-inner font-medium">${itemsLi}</div>`;
              }

              statusHtml = `<div class="flex flex-col items-start sm:items-end gap-1 w-full">
                                <div class="flex gap-1 items-center">
                                    <span class="inline-flex rounded bg-yellow-100 px-2 py-0.5 text-[10px] font-bold text-yellow-800 border border-yellow-200">DI LUAR</span>
                                    <span class="text-[9px] font-bold text-yellow-600 bg-yellow-50 px-1.5 py-0.5 rounded border border-yellow-100 uppercase tracking-wider">${textAlasan}</span>
                                </div>
                                ${listBarangHtml}
                            </div>`;
          }
          document.getElementById('profil-status').innerHTML = statusHtml;

          resetFilterProfil(); 
          toggleModal('modal-profil');
      }

      function renderRiwayatProfil(startDate = '', endDate = '') {
          let tbody = document.getElementById('tbody-profil-riwayat');
          let filterData = dbRiwayat.filter(log => log.id_anggota == profilAktifId);

          if (startDate !== '' && endDate !== '') {
              filterData = filterData.filter(log => {
                  let d = log.waktu.split(' ')[0]; 
                  return (d >= startDate && d <= endDate);
              });
          }

          let html = '';
          if(filterData.length > 0) {
              filterData.forEach(log => {
                  let d = new Date(log.waktu);
                  let formatWaktu = d.getDate().toString().padStart(2, '0') + '/' + (d.getMonth()+1).toString().padStart(2, '0') + '/' + d.getFullYear() + ' ' + d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
                  let badge = log.jenis_transaksi == 'Keluar Gudang' ? '<span class="font-bold text-[9px] px-1.5 py-0.5 rounded border border-red-200 bg-red-50 text-red-600">KELUAR</span>' : '<span class="font-bold text-[9px] px-1.5 py-0.5 rounded border border-green-200 bg-green-50 text-green-600">MASUK</span>';

                  html += `<tr class="hover:bg-blue-50/30 transition-colors">
                      <td class="px-3 py-2 text-gray-500 font-mono text-[10px]">${formatWaktu}</td>
                      <td class="px-3 py-2">${badge}</td>
                      <td class="px-3 py-2 text-gray-800 font-bold text-[11px] max-w-[200px] truncate" title="${log.item}">${log.item}</td>
                      <td class="px-3 py-2 text-gray-500 text-[11px] truncate max-w-[150px]" title="${log.keterangan}">${log.keterangan}</td>
                  </tr>`;
              });
          } else {
              html = '<tr><td colspan="4" class="px-3 py-8 text-center text-gray-400 text-xs font-medium">Tidak ada riwayat.</td></tr>';
          }
          tbody.innerHTML = html;
      }

      function terapkanFilterProfil() {
          let start = document.getElementById('filter-start').value;
          let end = document.getElementById('filter-end').value;
          if(!start || !end) return Swal.fire({toast: true, position: 'top-end', icon: 'warning', title: 'Pilih tanggal!', showConfirmButton: false, timer: 2000});
          renderRiwayatProfil(start, end);
      }

      function resetFilterProfil() {
          document.getElementById('filter-start').value = '';
          document.getElementById('filter-end').value = '';
          renderRiwayatProfil(); 
      }

      function cetakProfilLaporan() {
          let nama = document.getElementById('profil-nama').innerText;
          let nrp = document.getElementById('profil-nrp').innerText;
          let satuan = document.getElementById('profil-satuan').innerText;
          
          let pangkat = document.getElementById('profil-pangkat').innerText;
          let nohp = document.getElementById('profil-nohp').innerText;
          
          let baju = document.getElementById('profil-baju').innerText;
          let celana = document.getElementById('profil-celana').innerText;
          let tk = document.getElementById('profil-tk').innerText;
          let sepatu = document.getElementById('profil-sepatu').innerText;

          let senjata = document.getElementById('profil-senjata').innerText;
          let seri = document.getElementById('profil-seri').innerText.replace('SN: ', '');
          let pinjamanLainnya = document.getElementById('profil-pinjaman-tetap').innerHTML.replace(/<br>/g, ', ').replace(/• /g, '');
          if(pinjamanLainnya.includes('Tidak ada')) pinjamanLainnya = '-';
          
          let statusBlock = document.getElementById('profil-status');
          let statusText = statusBlock.innerText.replace(/\n/g, ', ');

          let start = document.getElementById('filter-start').value;
          let end = document.getElementById('filter-end').value;
          
          let filterData = dbRiwayat.filter(log => log.id_anggota == profilAktifId);
          let periodeText = "Keseluruhan Waktu";

          if (start !== '' && end !== '') {
              filterData = filterData.filter(log => { let d = log.waktu.split(' ')[0]; return (d >= start && d <= end); });
              let ds = new Date(start); let de = new Date(end);
              periodeText = ds.getDate()+'/'+(ds.getMonth()+1)+'/'+ds.getFullYear() + " s/d " + de.getDate()+'/'+(de.getMonth()+1)+'/'+de.getFullYear();
          }

          let tableRows = '';
          if(filterData.length > 0) {
              filterData.forEach(log => {
                  let d = new Date(log.waktu);
                  let formatWaktu = d.getDate().toString().padStart(2, '0') + '/' + (d.getMonth()+1).toString().padStart(2, '0') + '/' + d.getFullYear() + ' ' + d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
                  let txColor = log.jenis_transaksi == 'Keluar Gudang' ? 'color: #dc2626;' : 'color: #16a34a;';

                  tableRows += `<tr>
                      <td style="text-align: center;">${formatWaktu}</td>
                      <td style="${txColor} font-weight: bold; text-align: center;">${log.jenis_transaksi.toUpperCase()}</td>
                      <td>${log.item}</td>
                      <td>${log.keterangan}</td>
                  </tr>`;
              });
          } else {
              tableRows = '<tr><td colspan="4" style="text-align:center; padding: 20px;">Tidak ada riwayat peminjaman pada periode ini.</td></tr>';
          }

          let printContent = `
              <!DOCTYPE html>
              <html>
              <head>
                  <title>Laporan Logistik Personil - ${nama}</title>
                  <style>
                      body { font-family: 'Times New Roman', Times, serif; color: #000; margin: 0; padding: 20px; font-size: 14px; }
                      .header { text-align: center; border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 25px; }
                      .header h2 { margin: 0 0 5px 0; font-size: 20px; text-transform: uppercase; }
                      .header p { margin: 0; font-size: 14px; }
                      .info-table { width: 100%; margin-bottom: 25px; }
                      .info-table td { padding: 4px 0; vertical-align: top; }
                      .info-table td.label { width: 140px; font-weight: bold; }
                      .info-table td.colon { width: 10px; text-align:center; }
                      .data-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
                      .data-table th, .data-table td { border: 1px solid #000; padding: 6px; }
                      .data-table th { background-color: #f0f0f0; text-align: center; }
                      .footer { width: 100%; margin-top: 50px; }
                      .signature-box { float: right; width: 250px; text-align: center; }
                      .signature-line { margin-top: 70px; border-bottom: 1px solid #000; margin-bottom: 5px; }
                      @media print {
                          @page { size: A4 portrait; margin: 2cm; }
                          body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                      }
                  </style>
              </head>
              <body>
                  <div class="header">
                      <h2>LAPORAN DATA & RIWAYAT LOGISTIK PERSONIL</h2>
                      <p>Sistem Informasi Logistik Brimob</p>
                  </div>
                  
                  <table class="info-table">
                      <tr>
                          <td class="label">Nama Lengkap</td><td class="colon">:</td><td><b>${nama}</b></td>
                          <td class="label">Senjata Organik</td><td class="colon">:</td><td><b>${senjata}</b></td>
                      </tr>
                      <tr>
                          <td class="label">NRP</td><td class="colon">:</td><td>${nrp}</td>
                          <td class="label">Nomor Seri</td><td class="colon">:</td><td>${seri}</td>
                      </tr>
                      <tr>
                          <td class="label">Pangkat</td><td class="colon">:</td><td>${pangkat}</td>
                          <td class="label">Status Inventaris</td><td class="colon">:</td><td>${statusText}</td>
                      </tr>
                      <tr>
                          <td class="label">Satuan</td><td class="colon">:</td><td>${satuan}</td>
                          <td class="label">Periode Log</td><td class="colon">:</td><td>${periodeText}</td>
                      </tr>
                      <tr>
                          <td class="label">No. HP</td><td class="colon">:</td><td>${nohp}</td>
                          <td colspan="3"></td>
                      </tr>
                      <tr><td colspan="6" style="padding-top:10px;"></td></tr>
                      <tr>
                          <td class="label">Ukuran Kaporlap</td><td class="colon">:</td>
                          <td colspan="4">
                              Baju: <b>${baju}</b> &nbsp;|&nbsp; Celana: <b>${celana}</b> &nbsp;|&nbsp; T. Kepala: <b>${tk}</b> &nbsp;|&nbsp; Sepatu: <b>${sepatu}</b>
                          </td>
                      </tr>
                      <tr>
                          <td class="label">Pinjaman Lainnya</td><td class="colon">:</td>
                          <td colspan="4"><b>${pinjamanLainnya}</b></td>
                      </tr>
                  </table>

                  <h3 style="font-size: 14px; margin-bottom:10px;">Riwayat Pergerakan Barang</h3>
                  <table class="data-table">
                      <thead>
                          <tr>
                              <th width="15%">Waktu</th>
                              <th width="15%">Jenis Transaksi</th>
                              <th width="40%">Rincian Item Barang</th>
                              <th width="30%">Keterangan Kegiatan</th>
                          </tr>
                      </thead>
                      <tbody>
                          ${tableRows}
                      </tbody>
                  </table>

                  <div class="footer">
                      <div class="signature-box">
                          <p>Dicetak pada: ${new Date().toLocaleDateString('id-ID')}</p>
                          <p>Petugas Logistik</p>
                          <div class="signature-line"></div>
                          <p><b><?= addslashes($_SESSION['nama_petugas']) ?></b></p>
                      </div>
                  </div>
                  
                  <script>
                      window.onload = function() { 
                          setTimeout(function() { window.print(); window.close(); }, 500);
                      };
                  <\/script>
              </body>
              </html>
          `;

          let printWindow = window.open('', '_blank');
          printWindow.document.open();
          printWindow.document.write(printContent);
          printWindow.document.close();
      }

      function konfirmasiHapus(formId, namaAnggota) {
          Swal.fire({
              title: 'Hapus Personil?',
              html: `Anda akan menghapus data <b>${namaAnggota}</b>.<br><br>Peringatan: Senjata yang terikat pada anggota ini juga akan ikut terhapus!`,
              icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6b7280',
              confirmButtonText: 'Ya, Hapus Permanen!', cancelButtonText: 'Batal'
          }).then((result) => {
              if (result.isConfirmed) { document.getElementById(formId).submit(); }
          });
      }

      <?php
$pesan_get = $_GET['pesan'] ?? '';
if ($pesan_get !== ''):
?>
document.addEventListener('DOMContentLoaded', function() {
    let pesan = '';
    let icon = 'success';

    <?php
        if ($pesan_get === 'tambah_sukses') {
            echo "pesan = 'Data personil baru telah masuk ke sistem.';";
        } elseif ($pesan_get === 'edit_sukses') {
            echo "pesan = 'Perubahan data personil telah disimpan.';";
        } elseif ($pesan_get === 'hapus_sukses') {
            echo "pesan = 'Data personil beserta senjatanya telah dihapus. Stok pinjaman tetap telah dikembalikan.';";
        } elseif ($pesan_get === 'status_sukses') {
            echo "pesan = 'Status kondisi senjata berhasil diperbarui.';";
        } elseif ($pesan_get === 'stok_tidak_cukup') {
            echo "pesan = 'Stok logistik tidak mencukupi untuk pinjaman tetap yang dipilih.';";
            echo "icon = 'error';";
        } elseif ($pesan_get === 'barang_tidak_ditemukan') {
            echo "pesan = 'Barang logistik untuk pinjaman tetap tidak ditemukan.';";
            echo "icon = 'error';";
        }
    ?>

    if (pesan !== '') {
        Swal.fire({
            icon: icon,
            title: icon === 'success' ? 'Berhasil!' : 'Gagal!',
            text: pesan,
            timer: 2500,
            showConfirmButton: false
        });
    }
});
<?php endif; ?>
  </script>
</body>
</html>
