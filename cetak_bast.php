<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: signin.php");
    exit;
}

$tx_id = $_GET['tx_id'] ?? '';
$nrp   = $_GET['nrp'] ?? '';

if ($tx_id === '' || $nrp === '') {
    die("Parameter transaksi tidak lengkap.");
}

function bacaJsonAman($path, $default = []) {
    if (!file_exists($path)) return $default;

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') return $default;

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

// --- FUNGSI TERBILANG ---
function penyebut($nilai) {
    $nilai = abs((int)$nilai);
    $huruf = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
    $temp = "";

    if ($nilai < 12) {
        $temp = " " . $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = penyebut($nilai - 10) . " belas";
    } else if ($nilai < 100) {
        $temp = penyebut((int)($nilai / 10)) . " puluh" . penyebut($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " seratus" . penyebut($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = penyebut((int)($nilai / 100)) . " ratus" . penyebut($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " seribu" . penyebut($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = penyebut((int)($nilai / 1000)) . " ribu" . penyebut($nilai % 1000);
    }

    return $temp;
}

function terbilang($nilai) {
    $nilai = (int)$nilai;
    if ($nilai < 0) {
        return "minus " . trim(penyebut($nilai));
    }
    return trim(penyebut($nilai));
}

// --- AMBIL DATA ---
$riwayat_data = bacaJsonAman('data/riwayat.json', []);
$anggota_data = bacaJsonAman('data/anggota.json', []);
$users_data   = bacaJsonAman('data/users.json', []);
$settings     = bacaJsonAman('data/settings.json', []);

// --- PIHAK PERTAMA ---
$peminjam = null;
foreach ($anggota_data as $a) {
    if (($a['nrp'] ?? '') == $nrp) {
        $peminjam = $a;
        break;
    }
}

// --- AMBIL LOG TRANSAKSI BERDASARKAN TX_ID ---
$tx_logs = array_values(array_filter($riwayat_data, function ($l) use ($tx_id) {
    return (($l['id_transaksi'] ?? '') == $tx_id);
}));

if (count($tx_logs) === 0 || !$peminjam) {
    die("Data Transaksi Tidak Ditemukan.");
}

// --- PIHAK KEDUA (PETUGAS LOGISTIK) ---
$petugas_nama = $_SESSION['nama_petugas'] ?? 'TIDAK DIKENAL';
$session_username = $_SESSION['username'] ?? '';
$petugas_pangkat = '';
$petugas_nrp = '';

if ($session_username !== '') {
    foreach ($users_data as $u) {
        if (($u['username'] ?? '') === $session_username) {
            $petugas_pangkat = strtoupper($u['pangkat'] ?? '');
            $petugas_nrp = $u['nrp'] ?? '';
            break;
        }
    }
}

if ($petugas_pangkat === '' && isset($_SESSION['pangkat_petugas'])) {
    $petugas_pangkat = strtoupper($_SESSION['pangkat_petugas']);
}
if ($petugas_nrp === '' && isset($_SESSION['nrp_petugas'])) {
    $petugas_nrp = $_SESSION['nrp_petugas'];
}

$petugas_jabatan = "BA SI LOGISTIK KOMPI 3 BATALYON B PELOPOR";

// --- RINCIAN BARANG DARI LOG ---
$senjata = [];
$magazin = 0;
$ammo_tajam = 0;
$ammo_karet = 0;
$ammo_hampa = 0;
$barang_lain = [];
$kegiatan_text = '';

foreach ($tx_logs as $log) {
    $kegiatan_text = $log['keterangan'] ?? '';
    $item_str = $log['item'] ?? '';

    if (strpos($item_str, 'Senjata:') !== false) {
        preg_match('/Senjata:\s*(.*?)\s*\((.*?)\)/', $item_str, $matches);
        if (!empty($matches) && isset($matches[1], $matches[2])) {
            $senjata[] = [
                'nama' => trim($matches[1]),
                'seri' => trim($matches[2])
            ];
        }
    } else {
        preg_match('/(\d+)x\s*(.*)/', $item_str, $matches);
        if (!empty($matches) && isset($matches[1], $matches[2])) {
            $qty = (int)$matches[1];
            $nama_brg_asli = trim($matches[2]);
            $nama_brg = strtolower($nama_brg_asli);

            if (strpos($nama_brg, 'mag') !== false) {
                $magazin += $qty;
            } elseif (strpos($nama_brg, 'tajam') !== false) {
                $ammo_tajam += $qty;
            } elseif (strpos($nama_brg, 'karet') !== false) {
                $ammo_karet += $qty;
            } elseif (strpos($nama_brg, 'hampa') !== false) {
                $ammo_hampa += $qty;
            } else {
                $barang_lain[] = [
                    'qty' => $qty,
                    'nama' => $nama_brg_asli
                ];
            }
        }
    }
}

// --- AMBIL NAMA KEGIATAN YANG BERSIH ---
$kegiatan_bersih = preg_replace('/^\[(.*?)\]\s*/', '', $kegiatan_text);
if (empty($kegiatan_bersih)) {
    if (preg_match('/^\[(.*?)\]/', $kegiatan_text, $m)) {
        $kegiatan_bersih = $m[1];
    } else {
        $kegiatan_bersih = $kegiatan_text;
    }
}
if (trim($kegiatan_bersih) === '' || strtolower(trim($kegiatan_bersih)) === 'kegiatan') {
    if (preg_match('/^\[(.*?)\]/', $kegiatan_text, $m)) {
        $kegiatan_bersih = $m[1];
    }
}
if (trim($kegiatan_bersih) === '') {
    $kegiatan_bersih = '-';
}

// --- RANGKAI NARASI BARANG ---
$rincian = [];

// Senjata
if (count($senjata) > 0) {
    foreach ($senjata as $s) {
        $rincian[] = "Senjata Api berupa 1 (satu) pucuk senjata api bahu " . htmlspecialchars($s['nama']) . " dengan Nomor Senjata Api: <span class=\"bold\">(" . htmlspecialchars($s['seri']) . ")</span>";
    }
}

// Magazen dan amunisi
if ($magazin > 0) {
    $rincian[] = "<span class=\"bold\">$magazin (" . terbilang($magazin) . ") buah magazen</span>";
}
if ($ammo_tajam > 0) {
    $rincian[] = "<span class=\"bold\">$ammo_tajam (" . terbilang($ammo_tajam) . ") butir amunisi tajam</span>";
}
if ($ammo_karet > 0) {
    $rincian[] = "<span class=\"bold\">$ammo_karet (" . terbilang($ammo_karet) . ") butir amunisi karet</span>";
}
if ($ammo_hampa > 0) {
    $rincian[] = "<span class=\"bold\">$ammo_hampa (" . terbilang($ammo_hampa) . ") butir amunisi hampa</span>";
}

// Barang logistik lain
if (count($barang_lain) > 0) {
    foreach ($barang_lain as $b) {
        $rincian[] = "<span class=\"bold\">{$b['qty']} (" . terbilang($b['qty']) . ") " . htmlspecialchars($b['nama']) . "</span>";
    }
}

$teks_barang = implode(", ", $rincian);

if ($teks_barang === '') {
    $teks_barang = "barang logistik";
}

// --- TANGGAL ---
$hari_array = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$bulan_array = [
    '',
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
];

$hari_ini = $hari_array[date('l')] ?? date('l');
$tanggal_huruf = terbilang(date('j'));
$bulan_huruf = strtolower($bulan_array[(int)date('n')] ?? '');
$tahun_huruf = terbilang(date('Y'));
$tanggal_ttd = date('d ') . ($bulan_array[(int)date('n')] ?? '') . date(' Y');

$bulan_romawi = [
    '1'  => 'I',
    '2'  => 'II',
    '3'  => 'III',
    '4'  => 'IV',
    '5'  => 'V',
    '6'  => 'VI',
    '7'  => 'VII',
    '8'  => 'VIII',
    '9'  => 'IX',
    '10' => 'X',
    '11' => 'XI',
    '12' => 'XII'
];

$nomor_bast = "BA/" . date('d') . "/" . ($bulan_romawi[date('n')] ?? '-') . "/" . date('Y') . "/KOMPI 3 YON B POR";

$nama_komandan = $settings['nama_komandan'] ?? '-';
$pangkat_komandan = $settings['pangkat_komandan'] ?? '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Berita Acara Serah Terima</title>
  <style>
    body{ font-family: "Times New Roman", serif; background:#f2f2f2; margin:0; padding:30px; }
    .document{ width:800px; margin:auto; background:#fff; padding:40px 60px; color:#000; line-height:1.5; box-shadow:0 0 10px rgba(0,0,0,0.1); }
    .kop{ text-align:left; font-size:20px; font-weight:bold; line-height:1.3; width:max-content; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 20px;}
    .logo{ text-align:center; font-size:55px; margin-top:15px; }
    .title{ text-align:center; margin-top:10px; }
    .title h2{ margin:0; font-size:24px; font-weight:bold; text-transform:uppercase; letter-spacing: 1px;}
    .title p{ margin:0; font-size:18px; font-weight:bold; text-decoration:underline; }
    .content{ margin-top:30px; font-size:20px; text-align:justify; }
    .indent{ text-indent:50px; }
    .table-data{ margin-top:20px; margin-left:40px; font-size:20px; }
    .table-data table{ border-collapse:collapse; }
    .table-data td{ padding:2px 10px; vertical-align:top; }
    .bold{ font-weight:bold; }
    .signature{ margin-top:60px; display:flex; justify-content:space-between; text-align:center; font-size:20px; }
    .signature .box{ width:300px; }
    .uppercase{ text-transform:uppercase; }
    .underline{ text-decoration: underline; }

    @media print {
        body { background: white; padding: 0; }
        .document { box-shadow: none; padding: 0; width: 100%; }
    }
  </style>
</head>
<body onload="setTimeout(function(){ window.print(); }, 500)">
  <div class="document">
    <div class="kop">
      SATUAN BRIMOB POLDA ACEH<br>
      <div style="text-align: center;">BATALYON B PELOPOR KOMPI 3</div>
    </div>

    <div class="logo" style="margin-top: 15px; text-align: center;">
      <img src="img/bagus.png" alt="Logo" style="width: 120px; height: auto;">
    </div>

    <div class="title">
      <h2>BERITA ACARA SERAH TERIMA</h2>
      <p>Nomor: <?= htmlspecialchars($nomor_bast) ?></p>
    </div>

    <div class="content">
      <p class="indent">
        ---------Pada hari ini <?= htmlspecialchars($hari_ini) ?>, tanggal <?= htmlspecialchars($tanggal_huruf) ?>, bulan <?= htmlspecialchars($bulan_huruf) ?>, tahun <?= htmlspecialchars($tahun_huruf) ?>,
        bertempat di Mako Kompi 3 Batalyon B Pelopor Satbrimob Polda Aceh,
        yang bertanda tangan di bawah ini:--------------------
      </p>

      <div class="table-data">
        <table>
          <tr>
            <td>1.</td>
            <td>Nama</td>
            <td>:</td>
            <td class="uppercase"><?= htmlspecialchars($peminjam['nama'] ?? '-') ?></td>
          </tr>
          <tr>
            <td></td>
            <td>Pangkat/NRP</td>
            <td>:</td>
            <td class="uppercase"><?= htmlspecialchars(($peminjam['pangkat'] ?? '') . "/" . ($peminjam['nrp'] ?? '')) ?></td>
          </tr>
          <tr>
            <td></td>
            <td>Jabatan</td>
            <td>:</td>
            <td class="uppercase"><?= htmlspecialchars($peminjam['satuan'] ?? '-') ?></td>
          </tr>
        </table>
        <p>Yang selanjutnya disebut <span class="bold">PIHAK PERTAMA</span></p>

        <table style="margin-top: 10px;">
          <tr>
            <td>2.</td>
            <td>Nama</td>
            <td>:</td>
            <td class="uppercase"><?= htmlspecialchars($petugas_nama) ?></td>
          </tr>
          <tr>
            <td></td>
            <td>Pangkat/NRP</td>
            <td>:</td>
            <td class="uppercase"><?= htmlspecialchars($petugas_pangkat . "/" . $petugas_nrp) ?></td>
          </tr>
          <tr>
            <td></td>
            <td>Jabatan</td>
            <td>:</td>
            <td class="uppercase"><?= htmlspecialchars($petugas_jabatan) ?></td>
          </tr>
        </table>
        <p>Yang selanjutnya disebut <span class="bold">PIHAK KEDUA</span></p>
      </div>

      <p class="indent mt-6">
        ---------Selanjutnya <span class="bold uppercase">PIHAK PERTAMA</span> telah menerima pinjam pakai
        <?= $teks_barang ?>
        dalam kondisi baik dan lengkap dari
        <span class="bold uppercase">PIHAK KEDUA</span> untuk melaksanakan pengamanan
        <span class="bold uppercase"><?= htmlspecialchars($kegiatan_bersih) ?></span>
        --------------------------------
      </p>
    </div>

    <div class="signature">
      <div class="box">
        Yang Menyerahkan<br>
        <span class="bold uppercase">PIHAK KEDUA</span>
        <br><br><br><br>
        <span class="bold uppercase underline"><?= htmlspecialchars($petugas_nama) ?></span>
        <br><span class="uppercase"><?= htmlspecialchars($petugas_pangkat." NRP ".$petugas_nrp) ?></span>
      </div>

      <div class="box">
        Aceh Utara, <?= htmlspecialchars($tanggal_ttd) ?><br>
        Yang Menerima<br>
        <span class="bold uppercase">PIHAK PERTAMA</span>
        <br><br><br><br>
        <span class="bold uppercase underline"><?= htmlspecialchars($peminjam['nama'] ?? '-') ?></span>
        <br>
        <span class="uppercase"><?= htmlspecialchars($peminjam['pangkat']." NRP ".$peminjam['nrp']) ?></span>
      </div>
    </div>

    <div class="signature" style="margin-top: 30px; justify-content: center;">
      <div class="box" style="width: 400px;">
        Mengetahui,<br>
        <span class="bold uppercase">KOMANDAN KOMPI 3 BATALYON B PELOPOR</span>
        <br><br><br><br>
        <span class="bold uppercase underline"><?= htmlspecialchars($nama_komandan) ?></span><br>
        <span class="uppercase"><?= htmlspecialchars($pangkat_komandan) ?></span>
      </div>
    </div>
  </div>
</body>
</html>