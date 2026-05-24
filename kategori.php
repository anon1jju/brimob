<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: signin.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: home.php");
    exit;
}

require_once 'class/LogistikDB.php';
$db = new LogistikDB();

// --- LOGIKA SETTINGS (KOMANDAN) ---
$settings_file = 'data/settings.json';
if (!file_exists($settings_file)) {
    file_put_contents($settings_file, json_encode(['nama_komandan' => 'Nama Komandan', 'pangkat_komandan' => 'Pangkat Komandan']));
}

if (isset($_POST['update_sistem'])) {
    $settings = [
        'nama_komandan' => $_POST['nama_komandan'],
        'pangkat_komandan' => $_POST['pangkat_komandan']
    ];
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    header("Location: kategori.php?pesan=setting_sukses"); exit;
}
$settings = json_decode(file_get_contents($settings_file), true);

// --- LOGIKA KATEGORI BARANG ---
if (isset($_POST['tambah_kategori'])) {
    $db->tambahKategori($_POST['nama_kategori']);
    header("Location: kategori.php?pesan=sukses"); exit;
}
if (isset($_POST['hapus_kategori'])) {
    $db->hapusKategori($_POST['id_kategori']);
    header("Location: kategori.php?pesan=hapus_sukses"); exit;
}

// --- LOGIKA JENIS KEGIATAN ---
if (isset($_POST['tambah_kegiatan'])) {
    $db->tambahJenisPinjaman($_POST['nama_kegiatan']);
    header("Location: kategori.php?pesan=kegiatan_sukses"); exit;
}
if (isset($_POST['edit_kegiatan'])) {
    $db->editJenisPinjaman($_POST['index_kegiatan'], $_POST['nama_baru']);
    header("Location: kategori.php?pesan=edit_kegiatan_sukses"); exit;
}
if (isset($_POST['hapus_kegiatan'])) {
    $db->hapusJenisPinjaman($_POST['index_kegiatan']);
    header("Location: kategori.php?pesan=hapus_kegiatan_sukses"); exit;
}

$kategori_barang = $db->getKategori() ?? [];
$jenis_kegiatan = $db->getJenisPinjaman() ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <title>Pengaturan Parameter - Logistik</title>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 flex flex-col md:flex-row h-screen overflow-hidden">
  
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-6 overflow-y-auto">
    
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Pengaturan Parameter</h1>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6 max-w-2xl">
        <div class="p-3 border-b border-gray-200 bg-gray-50">
            <h2 class="font-bold text-gray-800 text-sm">Pengaturan Data Komandan (Laporan)</h2>
        </div>
        <form method="POST" action="" class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="update_sistem" value="1">
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase">Nama Lengkap</label>
                <input type="text" name="nama_komandan" value="<?= htmlspecialchars($settings['nama_komandan'] ?? '') ?>" class="w-full rounded border-gray-300 p-2 text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pangkat / Jabatan</label>
                <input type="text" name="pangkat_komandan" value="<?= htmlspecialchars($settings['pangkat_komandan'] ?? '') ?>" class="w-full rounded border-gray-300 p-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-blue-700">Simpan Pengaturan</button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm flex flex-col h-[400px]">
            <div class="p-3 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 text-sm">Kategori Barang</h2>
                <button onclick="bukaModalKategori()" class="bg-blue-600 text-white text-[10px] font-bold px-3 py-1 rounded hover:bg-blue-700">Tambah</button>
            </div>
            <div class="overflow-y-auto flex-1">
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($kategori_barang as $kat): ?>
                        <tr>
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($kat['nama_kategori']) ?></td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="" onsubmit="return confirm('Hapus?')">
                                    <input type="hidden" name="hapus_kategori" value="1"><input type="hidden" name="id_kategori" value="<?= htmlspecialchars($kat['id_kategori']) ?>">
                                    <button class="text-red-500 font-bold hover:text-red-700">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm flex flex-col h-[400px]">
            <div class="p-3 border-b border-gray-200 bg-blue-50 flex justify-between items-center">
                <h2 class="font-bold text-blue-800 text-sm">Jenis Kegiatan / Tujuan</h2>
                <button onclick="bukaModalKegiatan()" class="bg-blue-700 text-white text-[10px] font-bold px-3 py-1 rounded hover:bg-blue-800">Tambah</button>
            </div>
            <div class="overflow-y-auto flex-1">
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($jenis_kegiatan as $idx => $keg): ?>
                        <tr>
                            <td class="px-4 py-3 font-bold text-gray-700"><?= htmlspecialchars($keg['nama']) ?></td>
                            <td class="px-4 py-3 text-right flex justify-end gap-2">
                                <button onclick="bukaEditKegiatan(<?= $idx ?>, '<?= addslashes($keg['nama']) ?>')" class="text-blue-600 font-bold hover:underline">Edit</button>
                                <form method="POST" action="" onsubmit="return confirm('Hapus?')">
                                    <input type="hidden" name="hapus_kegiatan" value="1"><input type="hidden" name="index_kegiatan" value="<?= $idx ?>">
                                    <button class="text-red-500 font-bold hover:text-red-700">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </main>
  
  <script>
      function bukaModalKategori() { document.getElementById('modal-kategori').classList.remove('hidden'); }
      function bukaModalKegiatan() { document.getElementById('modal-kegiatan').classList.remove('hidden'); }
      function tutupModal(id) { document.getElementById(id).classList.add('hidden'); }
      function bukaEditKegiatan(index, nama) {
          document.getElementById('edit_index_kegiatan').value = index;
          document.getElementById('edit_nama_kegiatan').value = nama;
          document.getElementById('modal-edit-kegiatan').classList.remove('hidden');
      }
      <?php if(isset($_GET['pesan'])): ?>
      Swal.fire({ icon: 'success', title: 'Berhasil!', timer: 1500, showConfirmButton: false });
      <?php endif; ?>
  </script>
</body>
</html>