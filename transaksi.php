<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: signin.php");
    exit;
}

require_once 'class/LogistikDB.php';
$db = new LogistikDB();

// ==========================================
// PROSES SUBMIT TRANSAKSI (MENDUKUNG AJAX & KERANJANG)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_transaksi'])) {
    $input_peminjam = isset($_POST['nrp']) ? trim($_POST['nrp']) : ''; 
    $aksi = isset($_POST['aksi_transaksi']) ? $_POST['aksi_transaksi'] : ''; 
    $jenis_pinjaman = isset($_POST['jenis_pinjaman']) ? $_POST['jenis_pinjaman'] : '';
    
    // PERBAIKAN BUG NOTICE KETERANGAN LINE 18
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : "";
    
    $ket_final = "[$jenis_pinjaman] " . $keterangan;
    $waktu = date('Y-m-d H:i:s');
    $tx_id = "TRX-" . time();
    $is_ajax = isset($_POST['is_ajax']);

    $senjata_data = $db->getAllSenjata();
    $logistik_data = json_decode(file_get_contents('data/logistik_umum.json'), true);
    $riwayat_data = json_decode(file_get_contents('data/riwayat.json'), true);
    $anggota_data = json_decode(file_get_contents('data/anggota.json'), true);

    $berhasil = false;
    $error_msg = "";

    // 1. Cari Data Penanggung Jawab Utama
    $id_anggota_global = null;
    $nama_peminjam_global = "Tidak Dikenal";
    foreach ($anggota_data as $a) {
        if ($a['nrp'] == $input_peminjam || strtolower($a['nama']) == strtolower($input_peminjam)) { 
            $id_anggota_global = $a['id_anggota']; 
            $nama_peminjam_global = $a['nama']; 
            break; 
        }
    }

    if (!$id_anggota_global) {
        $error_msg = "Petugas tidak ditemukan di database!";
    } else {
        $cart_items = isset($_POST['cart_data']) ? json_decode($_POST['cart_data'], true) : [];
        
        if (!empty($cart_items)) {
            foreach ($cart_items as $item) {
                // JIKA BARANG ADALAH SENJATA
                if ($item['tipe'] == 'senjata') {
                    $pemilik_senjata_id = $id_anggota_global; 
                    $pemilik_senjata_nama = $nama_peminjam_global; 

                    foreach ($senjata_data as &$s) {
                        if ($s['nomor_seri'] == $item['id']) {
                            $s['status_lokasi'] = ($aksi == 'keluar') ? 'Dibawa Bertugas' : 'Di Gudang';
                            
                            // Cari pemilik asli senjata ini
                            foreach ($anggota_data as $ang) {
                                if ($ang['id_anggota'] == $s['id_pemegang_tetap']) {
                                    $pemilik_senjata_id = $ang['id_anggota'];
                                    $pemilik_senjata_nama = $ang['nama'];
                                    break;
                                }
                            }
                            break;
                        }
                    }
                    array_unshift($riwayat_data, [
                        "id_transaksi" => $tx_id, "waktu" => $waktu, "id_anggota" => $pemilik_senjata_id,
                        "nama_personil" => $pemilik_senjata_nama,
                        "jenis_transaksi" => ($aksi == 'keluar') ? "Keluar Gudang" : "Masuk Gudang", 
                        "item" => "Senjata: " . $item['nama'] . " (" . $item['id'] . ")", "keterangan" => $ket_final
                    ]);
                } 
                // JIKA BARANG ADALAH LOGISTIK UMUM
                else if ($item['tipe'] == 'logistik') {
                    foreach ($logistik_data as &$l) {
                        if ($l['id_barang'] == $item['id']) {
                            if ($aksi == 'keluar') {
                                $l['stok_tersedia'] -= (int)$item['qty'];
                            } else {
                                $l['stok_tersedia'] += (int)$item['qty'];
                                if ($l['stok_tersedia'] > $l['total_stok']) $l['stok_tersedia'] = $l['total_stok'];
                            }
                            break;
                        }
                    }
                    array_unshift($riwayat_data, [
                        "id_transaksi" => $tx_id, "waktu" => $waktu, "id_anggota" => $pemilik_senjata_id ?? $id_anggota_global,
                        "nama_personil" => $pemilik_senjata_nama ?? $nama_peminjam_global,
                        "jenis_transaksi" => ($aksi == 'keluar') ? "Keluar Gudang" : "Masuk Gudang", 
                        "item" => $item['qty'] . "x " . $item['nama'], "keterangan" => $ket_final
                    ]);
                }
            }
            $berhasil = true;
            file_put_contents('data/senjata.json', json_encode($senjata_data, JSON_PRETTY_PRINT));
            file_put_contents('data/logistik_umum.json', json_encode($logistik_data, JSON_PRETTY_PRINT));
            file_put_contents('data/riwayat.json', json_encode($riwayat_data, JSON_PRETTY_PRINT));
        }
    }

    // --- CEK APAKAH PERLU BAST ---
    $kegiatan_lower = strtolower($jenis_pinjaman);
    $perlu_bast = !(strpos($kegiatan_lower, 'apel') !== false || strpos($kegiatan_lower, 'rutin') !== false || strpos($kegiatan_lower, 'piket') !== false);

    // RESPON KHUSUS AJAX (FAST TRACK)
    if ($is_ajax) {
        header('Content-Type: application/json');
        if ($berhasil) {
            $added_logs = array_slice($riwayat_data, 0, count($cart_items));
            foreach($added_logs as &$al) { $al['waktu_jam'] = date('H:i:s', strtotime($al['waktu'])); }
            echo json_encode([
                "status" => "success", 
                "riwayat_array" => $added_logs,
                "perlu_bast" => $perlu_bast, // Kirim sinyal ke Javascript
                "tx_id" => $tx_id,
                "nrp" => $input_peminjam
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => $error_msg]);
        }
        exit;
    }

    // RESPON NORMAL (MODE KERANJANG) DENGAN RELOAD ATAU REDIRECT BAST
    if ($berhasil) {
        if ($perlu_bast) {
            // Arahkan otomatis ke halaman cetak BAST
            header("Location: cetak_bast.php?tx_id=$tx_id&nrp=$input_peminjam");
        } else {
            // Transaksi rutin, reload biasa
            header("Location: transaksi.php?sukses=1");
        }
        exit;
    }
}

$data_logistik_php = $db->getLogistikUmum();
$data_senjata_php = $db->getAllSenjata();
$semua_anggota = json_decode(file_get_contents('data/anggota.json'), true); 

$json_logistik = json_encode($data_logistik_php);
$json_senjata = json_encode($data_senjata_php);
$json_anggota = json_encode($semua_anggota);

$semua_riwayat = json_decode(file_get_contents('data/riwayat.json'), true) ?? [];
$riwayat_terakhir = array_slice($semua_riwayat, 0, 5);

$file_pinjaman = 'data/jenis_pinjaman.json';
$daftar_kegiatan = file_exists($file_pinjaman) ? json_decode(file_get_contents($file_pinjaman), true) : [
    ["nama" => "Rutin / Apel Pagi"], ["nama" => "Piket Jaga"], ["nama" => "Dinas Luar"], ["nama" => "Patroli"], ["nama" => "Dinas Operasi"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B600%3B700&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" rel="stylesheet" />
    <title>Scanner Cerdas - Logistik Brimob</title>
    <style>
        body { font-family: 'Inter', 'Noto Sans', sans-serif; }
        #reader video { border-radius: 0.5rem; width: 100% !important; }
        #reader { border: none !important; }
        .search-scroll::-webkit-scrollbar { width: 6px; }
        .search-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .search-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col md:flex-row h-screen overflow-hidden">
  
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-8 overflow-y-auto relative w-full flex flex-col h-full">
    
    <div class="mb-4 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-3 flex-shrink-0">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Sirkulasi Barang</h1>
            <p class="text-sm md:text-base text-gray-500 mt-1">Mode Cerdas: Apel/Piket (1 Mag) & Patroli/Dinas Luar (3 Mag + Amunisi).</p>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 flex-shrink-0">
        
        <div class="w-full lg:w-1/3 flex flex-col gap-4 flex-shrink-0">
            <div id="box-scanner" class="bg-gray-900 rounded-lg p-5 shadow-lg border-2 border-transparent transition-all duration-300 relative">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">ALAT SCANNER</label>
                    <span id="indikator-mode" class="text-[9px] bg-red-600 text-white px-2 py-0.5 rounded font-bold uppercase tracking-widest hidden animate-pulse">FAST TRACK AKTIF</span>
                </div>
                
                <div class="relative">
                    <input type="text" id="input-scanner" class="w-full bg-gray-800 text-white border border-gray-700 rounded-md py-4 px-4 text-lg font-mono focus:ring-0 focus:outline-none placeholder-gray-500" placeholder="Arahkan scanner fisik..." autofocus autocomplete="off">
                    <div class="absolute right-3 top-4 text-blue-500 animate-pulse">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-700">
                    <button type="button" id="btn-kamera" class="w-full bg-gray-700 hover:bg-gray-600 text-white text-xs font-bold py-2.5 px-4 rounded-md border border-gray-600 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Nyalakan Kamera HP
                    </button>
                    <div id="reader" class="mt-3 w-full rounded-md overflow-hidden hidden"></div>
                </div>
            </div>

            <form id="form-checkout" method="POST" action="" class="bg-white rounded-lg p-5 shadow-sm border border-gray-200 flex-1 flex flex-col transition-all">
                <input type="hidden" name="submit_transaksi" value="1">
                <input type="hidden" name="cart_data" id="cart_data_input">
                
                <h3 class="text-sm font-bold text-gray-800 mb-4 border-b pb-2">Informasi Transaksi</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase">Kegiatan / Tujuan</label>
                        <select name="jenis_pinjaman" id="jenis_pinjaman" onchange="cekModeKegiatan()" class="w-full rounded-md border-gray-300 text-sm p-2 focus:ring-blue-500 font-bold text-blue-700 bg-blue-50">
                            <?php foreach($daftar_kegiatan as $kegiatan): ?>
                                <option value="<?= htmlspecialchars($kegiatan['nama']) ?>"><?= htmlspecialchars($kegiatan['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="box-input-nrp">
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Penanggung Jawab <span class="text-red-500">*</span></label>
                        <input type="text" name="nrp" id="input-nrp" class="w-full rounded-md border-gray-300 text-sm p-2.5 font-bold focus:ring-blue-500 bg-gray-50" placeholder="Ketik NRP atau Nama..." onkeyup="cekAnggota(this.value)" autocomplete="off">
                        <p id="nama-anggota" class="text-[11px] text-gray-500 font-medium mt-1.5 min-h-[16px] uppercase">Wajib diisi untuk selain Mode Fast Track</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Aksi Scanner</label>
                        <select name="aksi_transaksi" id="aksi_transaksi" class="w-full rounded-md border-gray-300 text-sm p-2.5 font-bold bg-gray-50 focus:ring-blue-500">
                            <option value="keluar">KELUARKAN DARI GUDANG</option>
                            <option value="masuk">TERIMA MASUK GUDANG</option>
                        </select>
                    </div>
                    
                    <div id="box-manual" class="border-t border-gray-100 pt-4 mt-4 bg-orange-50/30 -mx-5 px-5 py-4 border-b">
                        <label class="block text-xs font-bold text-orange-800 mb-2 uppercase">Pencarian Barang Manual</label>
                        <div class="relative">
                            <input type="text" id="input-cari-barang" class="w-full rounded-md border-gray-300 text-sm p-2.5 bg-white" placeholder="Ketik nama barang..." onkeyup="cariBarangManual(this.value)" autocomplete="off">
                            <div id="hasil-pencarian-barang" class="absolute z-50 w-full bg-white border border-gray-200 shadow-xl max-h-48 overflow-y-auto hidden rounded-md mt-1 divide-y divide-gray-100 search-scroll"></div>
                        </div>
                    </div>
                </div>

                <button type="button" id="btn-eksekusi" onclick="prosesCheckout()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-4 rounded-md shadow-sm mt-6 flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    EKSEKUSI TRANSAKSI
                </button>
            </form>
        </div>

        <div class="w-full lg:w-2/3 bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col min-h-[300px] relative">
            <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center rounded-t-lg">
                <h2 id="judul-tabel-kanan" class="font-bold text-gray-800">Log Transaksi Terakhir</h2>
                <span id="badge-total-items" class="bg-blue-100 text-blue-800 text-xs font-bold px-2.5 py-1 rounded-full hidden"><span id="total-items">0</span> Jenis Item</span>
            </div>
            
            <div class="flex-1 overflow-y-auto p-0 relative">
                <table id="tabel-keranjang" class="w-full text-left text-sm whitespace-nowrap min-w-max hidden">
                    <thead class="bg-white border-b border-gray-100 text-gray-400 text-xs uppercase sticky top-0 shadow-sm z-10">
                        <tr><th class="px-6 py-3">Rincian Barang</th><th class="px-6 py-3 text-center">Tipe</th><th class="px-6 py-3 text-center w-32">Kuantitas</th><th class="px-6 py-3 text-right">Aksi</th></tr>
                    </thead>
                    <tbody id="cart-body" class="divide-y divide-gray-50"></tbody>
                </table>

                <table id="tabel-riwayat" class="w-full text-left text-sm whitespace-nowrap min-w-max">
                    <thead class="bg-gray-100 border-b border-gray-200 text-gray-600 text-[11px] uppercase tracking-wider sticky top-0 z-10">
                        <tr><th class="px-6 py-3">Waktu</th><th class="px-6 py-3">Personil</th><th class="px-6 py-3">Barang</th><th class="px-6 py-3">Status</th></tr>
                    </thead>
                    <tbody id="riwayat-body" class="divide-y divide-gray-50">
                        <?php if(count($riwayat_terakhir) > 0): foreach($riwayat_terakhir as $log): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3 text-gray-500 font-mono text-xs"><?= date('H:i:s', strtotime($log['waktu'])) ?></td>
                            <td class="px-6 py-3 font-bold text-gray-800 uppercase text-xs"><?= htmlspecialchars($log['nama_personil'] ?? '-') ?></td>
                            <td class="px-6 py-3 text-gray-700 font-medium"><?= htmlspecialchars($log['item']) ?></td>
                            <td class="px-6 py-3"><span class="font-bold text-[10px] px-2 py-1 rounded <?= $log['jenis_transaksi'] == 'Keluar Gudang' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>"><?= strtoupper($log['jenis_transaksi']) ?></span></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr id="row-kosong"><td colspan="4" class="px-6 py-8 text-center text-gray-400">Belum ada riwayat transaksi.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </main>

  <script>
    let dbLogistik = <?= $json_logistik ?>;
    let dbSenjata = <?= $json_senjata ?>;
    let dbAnggota = <?= $json_anggota ?>;
    let keranjang = []; 
    let modeFastTrack = false;

    // --- KAMERA SCANNER (PRESERVED) ---
    const html5QrCode = new Html5Qrcode("reader");
    let isKameraNyala = false;
    let lastCameraScanTime = 0;

    document.addEventListener('DOMContentLoaded', function() {
        cekModeKegiatan();
        if (localStorage.getItem('kamera_aktif') === 'ya') { setTimeout(() => { nyalakanKamera(); }, 500); }
    });

    document.getElementById('btn-kamera').addEventListener('click', function() {
        if (isKameraNyala) { matikanKamera(); localStorage.setItem('kamera_aktif', 'tidak'); } 
        else { nyalakanKamera(); localStorage.setItem('kamera_aktif', 'ya'); }
    });

    function nyalakanKamera() {
        const btn = document.getElementById('btn-kamera');
        document.getElementById('reader').classList.remove('hidden');
        btn.innerHTML = 'Memulai Kamera...';

        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => {
                if (Date.now() - lastCameraScanTime < 2000) return; 
                lastCameraScanTime = Date.now();
                prosesBarcode(decodedText.toUpperCase());
            }, (err) => {}
        ).then(() => { 
            isKameraNyala = true; 
            btn.innerHTML = 'Matikan Kamera HP';
            btn.classList.replace('bg-gray-700', 'bg-red-600');
        }).catch((err) => {
            localStorage.setItem('kamera_aktif', 'tidak');
            matikanKamera();
        });
    }

    function matikanKamera() {
        if(isKameraNyala) {
            html5QrCode.stop().then(() => {
                isKameraNyala = false;
                const btn = document.getElementById('btn-kamera');
                document.getElementById('reader').classList.add('hidden');
                btn.innerHTML = 'Nyalakan Kamera HP';
                btn.classList.replace('bg-red-600', 'bg-gray-700');
            });
        }
    }

    // --- MANAJEMEN MODE KEGIATAN ---
    function cekModeKegiatan() {
        let val = document.getElementById('jenis_pinjaman').value.toLowerCase();
        // Fast Track berlaku untuk Apel Pagi, Dinas Luar, Patroli, DAN Piket
        modeFastTrack = val.includes('apel') || val.includes('rutin') || val.includes('dinas luar') || val.includes('patroli') || val.includes('piket');
        
        let indikator = document.getElementById('indikator-mode');
        let boxNrp = document.getElementById('box-input-nrp');
        let boxManual = document.getElementById('box-manual');
        let btnEksekusi = document.getElementById('btn-eksekusi');

        if (modeFastTrack) {
            indikator.classList.remove('hidden');
            boxNrp.classList.add('hidden');
            boxManual.classList.add('hidden');
            btnEksekusi.classList.add('hidden');
            keranjang = []; renderKeranjang();
        } else {
            indikator.classList.add('hidden');
            boxNrp.classList.remove('hidden');
            boxManual.classList.remove('hidden');
            btnEksekusi.classList.remove('hidden');
        }
        document.getElementById('input-scanner').focus();
    }

    // --- ALAT SCANNER FISIK (ANTI-SPAM) ---
    const inputScanner = document.getElementById('input-scanner');
    let lastPhysicalInput = "";
    let lastPhysicalTime = 0;

    inputScanner.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let kodeScan = this.value.trim().toUpperCase();
            this.value = ''; this.focus();
            if (kodeScan !== '') {
                if (kodeScan === lastPhysicalInput && (Date.now() - lastPhysicalTime) < 800) return;
                lastPhysicalInput = kodeScan; lastPhysicalTime = Date.now();
                prosesBarcode(kodeScan);
            }
        }
    });

    function prosesBarcode(kode) {
        let aksi = document.getElementById('aksi_transaksi').value;
        let kegiatan = document.getElementById('jenis_pinjaman').value;
        let senjataMatch = dbSenjata.find(s => s.nomor_seri.toUpperCase() === kode);
        
        if (senjataMatch) {
            if (senjataMatch.status_lokasi === 'Rusak/Perbaikan') { bunyikanError(); return Swal.fire('Ditolak!', 'Senjata sedang perbaikan.', 'error'); }
            if (aksi === 'keluar' && senjataMatch.status_lokasi !== 'Di Gudang') { bunyikanError(); return Swal.fire('Ditolak!', 'Senjata sedang dibawa bertugas.', 'error'); }
            if (aksi === 'masuk' && senjataMatch.status_lokasi === 'Di Gudang') { bunyikanError(); return Swal.fire('Ditolak!', 'Senjata sudah di gudang.', 'error'); }

            let pemilik = dbAnggota.find(a => a.id_anggota === senjataMatch.id_pemegang_tetap);
            if (!pemilik) return Swal.fire('Error', 'Pemilik senjata tidak terdata!', 'error');

            if (modeFastTrack) {
                let fastCart = [ { id: senjataMatch.nomor_seri, nama: senjataMatch.jenis_senjata, qty: 1, tipe: 'senjata', keterangan: pemilik.nama } ];
                
                // Pengecekan Logika Apel / Piket vs Dinas / Patroli
                let isApelAtauPiket = kegiatan.toLowerCase().includes('apel') || kegiatan.toLowerCase().includes('rutin') || kegiatan.toLowerCase().includes('piket');
                let magQty = isApelAtauPiket ? 1 : 3;
                
                let magazen = dbLogistik.find(l => l.id_barang === "LOG-177614982830");
                if (magazen) fastCart.push({ id: magazen.id_barang, nama: magazen.nama_barang, qty: magQty, tipe: 'logistik' });

                if (!isApelAtauPiket) {
                    // AMUNISI UNTUK PATROLI / DINAS LUAR
                    let tj = dbLogistik.find(l => l.nama_barang.toLowerCase().includes('4 tj'));
                    if (tj) fastCart.push({ id: tj.id_barang, nama: tj.nama_barang, qty: 20, tipe: 'logistik' });
                    
                    let karet = dbLogistik.find(l => l.nama_barang.toLowerCase().includes('karet'));
                    if (karet) fastCart.push({ id: karet.id_barang, nama: karet.nama_barang, qty: 37, tipe: 'logistik' });
                    
                    let hampa = dbLogistik.find(l => l.nama_barang.toLowerCase().includes('hampa'));
                    if (hampa) fastCart.push({ id: hampa.id_barang, nama: hampa.nama_barang, qty: 3, tipe: 'logistik' });
                }
                
                kirimAjaxFastTrack(pemilik.nrp, aksi, kegiatan, fastCart, pemilik.nama);
            } else {
                let inputNrp = document.getElementById('input-nrp');
                if(inputNrp.value === '') { inputNrp.value = pemilik.nama; cekAnggota(pemilik.nrp); }
                tambahKeKeranjang(senjataMatch.nomor_seri, senjataMatch.jenis_senjata, 1, 1, 'senjata', pemilik.nama);
            }
            return;
        }

        let logistikMatch = dbLogistik.find(l => l.id_barang.toUpperCase() === kode);
        if (logistikMatch) {
            if (modeFastTrack) { bunyikanError(); return Swal.fire('Info', 'Mode Cerdas Fast Track hanya menerima Scan Senjata!', 'info'); }
            tambahKeKeranjang(logistikMatch.id_barang, logistikMatch.nama_barang, 1, logistikMatch.stok_tersedia, 'logistik', 'Logistik Umum');
        }
    }

    function kirimAjaxFastTrack(nrp, aksi, kegiatan, cartData, namaPemilik) {
        let formData = new FormData();
        formData.append('submit_transaksi', '1'); 
        formData.append('is_ajax', '1');
        formData.append('nrp', nrp); 
        formData.append('aksi_transaksi', aksi);
        formData.append('jenis_pinjaman', kegiatan); 
        formData.append('keterangan', 'kegiatan'); // Pastikan keterangan dikirim
        formData.append('cart_data', JSON.stringify(cartData));

        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                bunyikanSukses();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '✔ Selesai: ' + namaPemilik, showConfirmButton: false, timer: 1500 });
                updateUI(data.riwayat_array);
                
                // LOGIKA OTOMATIS BUKA BAST (JIKA PERLU)
                if (data.perlu_bast) {
                    window.open(`cetak_bast.php?tx_id=${data.tx_id}&nrp=${data.nrp}`, '_blank');
                }
            } else {
                bunyikanError();
                Swal.fire('Error', data.message, 'error');
            }
        }).catch(err => console.error(err));
    }

    function updateUI(logs) {
        let tbody = document.getElementById('riwayat-body');
        let rowKosong = document.getElementById('row-kosong');
        if(rowKosong) rowKosong.remove();
        logs.slice().reverse().forEach(log => {
            let tr = document.createElement('tr');
            tr.className = "bg-green-50/50";
            let spanClass = log.jenis_transaksi == 'Keluar Gudang' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600';
            tr.innerHTML = `<td class="px-6 py-3 text-gray-500 font-mono text-xs">${log.waktu_jam}</td><td class="px-6 py-3 font-bold text-gray-800 uppercase text-xs">${log.nama_personil}</td><td class="px-6 py-3 text-gray-700 font-medium">${log.item}</td><td class="px-6 py-3"><span class="font-bold text-[10px] px-2 py-1 rounded ${spanClass}">${log.jenis_transaksi.toUpperCase()}</span></td>`;
            tbody.prepend(tr);
        });
    }

    // --- FUNGSI KERANJANG STANDAR ---
    function tambahKeKeranjang(id, nama, qty, maxStok, tipe, keterangan = '') {
        let index = keranjang.findIndex(item => item.id === id);
        if (index !== -1) { if(tipe === 'senjata') return false; keranjang[index].qty++; } 
        else { keranjang.push({ id, nama, qty, maxStok, tipe, keterangan }); }
        renderKeranjang();
        bunyikanSukses();
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: nama + ' Masuk Keranjang', showConfirmButton: false, timer: 1000 });
        return true;
    }

    function renderKeranjang() {
        const cartBody = document.getElementById('cart-body');
        if (keranjang.length > 0) {
            document.getElementById('tabel-riwayat').classList.add('hidden');
            document.getElementById('tabel-keranjang').classList.remove('hidden');
            document.getElementById('badge-total-items').classList.remove('hidden');
        } else {
            document.getElementById('tabel-riwayat').classList.remove('hidden');
            document.getElementById('tabel-keranjang').classList.add('hidden');
            document.getElementById('badge-total-items').classList.add('hidden');
            return;
        }
        let html = '';
        keranjang.forEach((item, index) => {
            let labelTipe = item.tipe === 'senjata' ? '<span class="bg-gray-800 text-white text-[10px] px-2 py-0.5 rounded">SENJATA</span>' : '<span class="bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded border border-blue-200">LOGISTIK</span>';
            let inputQty = item.tipe === 'senjata' ? `1` : `<input type="number" value="${item.qty}" oninput="keranjang[${index}].qty=this.value" class="w-16 text-center border-gray-300 rounded font-bold py-1 bg-yellow-50">`;
            html += `<tr class="border-b"><td class="px-6 py-3"><p class="font-bold text-sm text-gray-800">${item.nama}</p><p class="text-[10px] font-mono text-gray-500">${item.id}</p>${item.keterangan ? `<span class="text-[9px] font-bold text-blue-600 bg-blue-50 px-1 rounded">Pemilik: ${item.keterangan}</span>` : ''}</td><td class="px-6 py-3 text-center">${labelTipe}</td><td class="px-6 py-3 text-center">${inputQty}</td><td class="px-6 py-3 text-right"><button type="button" onclick="keranjang.splice(${index},1);renderKeranjang()" class="text-red-500 font-bold text-xs">X Batal</button></td></tr>`;
        });
        cartBody.innerHTML = html;
        document.getElementById('total-items').innerText = keranjang.length;
    }

    function cekAnggota(val) {
        let anggota = dbAnggota.find(a => a.nrp == val || a.nama.toLowerCase().includes(val.toLowerCase()));
        let text = document.getElementById('nama-anggota');
        if (anggota) { text.innerText = "✓ " + anggota.nama; text.className = "text-[11px] text-blue-600 font-bold mt-1.5 uppercase"; }
        else { text.innerText = "✖ TIDAK DITEMUKAN"; text.className = "text-[11px] text-red-600 font-bold mt-1.5 uppercase"; }
    }

    function prosesCheckout() {
        let inputPeminjam = document.getElementById('input-nrp').value.trim();
        if (inputPeminjam === '') return Swal.fire('Peringatan', 'Penanggung jawab wajib diisi!', 'warning');
        let validAnggota = dbAnggota.find(a => a.nrp == inputPeminjam || a.nama.toLowerCase().includes(inputPeminjam.toLowerCase()));
        if (!validAnggota) return Swal.fire('Error', 'Petugas tidak terdaftar!', 'error');
        if (keranjang.length === 0) return Swal.fire('Kosong', 'Keranjang masih kosong!', 'warning');
        document.getElementById('input-nrp').value = validAnggota.nrp;
        document.getElementById('cart_data_input').value = JSON.stringify(keranjang);
        document.getElementById('form-checkout').submit();
    }

    function cariBarangManual(keyword) {
        let box = document.getElementById('hasil-pencarian-barang');
        if(keyword.length < 2) { box.classList.add('hidden'); return; }
        let html = '';
        dbSenjata.forEach(s => { if(s.jenis_senjata.toLowerCase().includes(keyword.toLowerCase()) || s.nomor_seri.toLowerCase().includes(keyword.toLowerCase())) { html += `<div class="p-3 cursor-pointer hover:bg-gray-100" onclick="pilihBarangManual('${s.nomor_seri}')"><p class="text-sm font-bold">${s.jenis_senjata}</p><p class="text-[10px] font-mono">SN: ${s.nomor_seri}</p></div>`; } });
        dbLogistik.forEach(l => { if(l.nama_barang.toLowerCase().includes(keyword.toLowerCase())) { html += `<div class="p-3 cursor-pointer hover:bg-blue-50" onclick="pilihBarangManual('${l.id_barang}')"><p class="text-sm font-bold">${l.nama_barang}</p><p class="text-[10px]">Stok: ${l.stok_tersedia}</p></div>`; } });
        box.innerHTML = html || '<p class="p-4 text-xs">Tidak ada data</p>';
        box.classList.remove('hidden');
    }
    function pilihBarangManual(id) { document.getElementById('input-cari-barang').value = ''; document.getElementById('hasil-pencarian-barang').classList.add('hidden'); prosesBarcode(id); }
    function bunyikanSukses() { try { let ctx = new (window.AudioContext || window.webkitAudioContext)(); let osc = ctx.createOscillator(); osc.type = 'sine'; osc.frequency.setValueAtTime(900, ctx.currentTime); osc.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 0.1); } catch (e) {} }
    function bunyikanError() { try { let ctx = new (window.AudioContext || window.webkitAudioContext)(); let osc = ctx.createOscillator(); osc.type = 'square'; osc.frequency.setValueAtTime(150, ctx.currentTime); osc.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 0.3); } catch (e) {} }

    <?php if(isset($_GET['sukses'])): ?>
    Swal.fire({ icon: 'success', title: 'Transaksi Berhasil!', text: 'Data sirkulasi diperbarui.', timer: 2000, showConfirmButton: false });
    <?php endif; ?>
  </script>
</body>
</html>