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
    file_put_contents($settings_file, json_encode([
        'nama_komandan' => 'Nama Komandan',
        'pangkat_komandan' => 'Pangkat Komandan'
    ]));
}

if (isset($_POST['update_sistem'])) {
    $settings = [
        'nama_komandan' => $_POST['nama_komandan'],
        'pangkat_komandan' => $_POST['pangkat_komandan']
    ];
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    header("Location: kategori.php?pesan=setting_sukses");
    exit;
}

$settings = json_decode(file_get_contents($settings_file), true);

// --- LOGIKA KATEGORI BARANG ---
if (isset($_POST['tambah_kategori'])) {
    $hasil = $db->tambahKategori(trim($_POST['nama_kategori']));
    header("Location: kategori.php?pesan=" . ($hasil ? "tambah_kategori_sukses" : "gagal"));
    exit;
}

if (isset($_POST['hapus_kategori'])) {
    $hasil = $db->hapusKategori($_POST['id_kategori']);
    header("Location: kategori.php?pesan=" . ($hasil ? "hapus_kategori_sukses" : "gagal"));
    exit;
}

// --- LOGIKA JENIS KEGIATAN ---
if (isset($_POST['tambah_kegiatan'])) {
    $hasil = $db->tambahJenisPinjaman(trim($_POST['nama_kegiatan']));
    header("Location: kategori.php?pesan=" . ($hasil ? "tambah_kegiatan_sukses" : "gagal"));
    exit;
}

if (isset($_POST['edit_kegiatan'])) {
    $hasil = $db->editJenisPinjaman($_POST['index_kegiatan'], trim($_POST['nama_baru']));
    header("Location: kategori.php?pesan=" . ($hasil ? "edit_kegiatan_sukses" : "edit_kegiatan_gagal"));
    exit;
}

if (isset($_POST['hapus_kegiatan'])) {
    $hasil = $db->hapusJenisPinjaman($_POST['index_kegiatan']);
    header("Location: kategori.php?pesan=" . ($hasil ? "hapus_kegiatan_sukses" : "gagal"));
    exit;
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
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
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
                <input
                    type="text"
                    name="nama_komandan"
                    value="<?= htmlspecialchars($settings['nama_komandan'] ?? '') ?>"
                    class="w-full rounded border border-gray-300 p-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase">Pangkat / Jabatan</label>
                <input
                    type="text"
                    name="pangkat_komandan"
                    value="<?= htmlspecialchars($settings['pangkat_komandan'] ?? '') ?>"
                    class="w-full rounded border border-gray-300 p-2 text-sm"
                >
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-blue-700">
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- KATEGORI BARANG -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm flex flex-col h-[400px]">
            <div class="p-3 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 text-sm">Kategori Barang</h2>
                <button
                    type="button"
                    onclick="bukaModalKategori()"
                    class="bg-blue-600 text-white text-[10px] font-bold px-3 py-1 rounded hover:bg-blue-700"
                >
                    Tambah
                </button>
            </div>
            <div class="overflow-y-auto flex-1">
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-gray-100">
                        <?php if (!empty($kategori_barang)): ?>
                            <?php foreach ($kategori_barang as $kat): ?>
                                <tr>
                                    <td class="px-4 py-3 font-medium">
                                        <?= htmlspecialchars($kat['nama_kategori']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="" class="form-hapus-kategori inline">
                                            <input type="hidden" name="hapus_kategori" value="1">
                                            <input type="hidden" name="id_kategori" value="<?= htmlspecialchars($kat['id_kategori']) ?>">
                                            <button type="submit" class="text-red-500 font-bold hover:text-red-700">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="px-4 py-4 text-center text-gray-500">
                                    Belum ada kategori barang.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- JENIS KEGIATAN -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm flex flex-col h-[400px]">
            <div class="p-3 border-b border-gray-200 bg-blue-50 flex justify-between items-center">
                <h2 class="font-bold text-blue-800 text-sm">Jenis Kegiatan / Tujuan</h2>
                <button
                    type="button"
                    onclick="bukaModalKegiatan()"
                    class="bg-blue-700 text-white text-[10px] font-bold px-3 py-1 rounded hover:bg-blue-800"
                >
                    Tambah
                </button>
            </div>
            <div class="overflow-y-auto flex-1">
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-gray-100">
                        <?php if (!empty($jenis_kegiatan)): ?>
                            <?php foreach ($jenis_kegiatan as $idx => $keg): ?>
                                <tr>
                                    <td class="px-4 py-3 font-bold text-gray-700">
                                        <?= htmlspecialchars($keg['nama']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                onclick='bukaEditKegiatan(<?= (int)$idx ?>, <?= json_encode($keg["nama"]) ?>)'
                                                class="text-blue-600 font-bold hover:underline"
                                            >
                                                Edit
                                            </button>

                                            <form method="POST" action="" class="form-hapus-kegiatan inline">
                                                <input type="hidden" name="hapus_kegiatan" value="1">
                                                <input type="hidden" name="index_kegiatan" value="<?= (int)$idx ?>">
                                                <button type="submit" class="text-red-500 font-bold hover:text-red-700">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="px-4 py-4 text-center text-gray-500">
                                    Belum ada jenis kegiatan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- MODAL TAMBAH KATEGORI -->
<div id="modal-kategori" class="hidden fixed inset-0 z-50 bg-black/50 items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md">
        <div class="flex items-center justify-between border-b px-4 py-3">
            <h3 class="font-bold text-gray-800">Tambah Kategori Barang</h3>
            <button type="button" onclick="tutupModal('modal-kategori')" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
        </div>
        <form method="POST" action="" class="p-4">
            <input type="hidden" name="tambah_kategori" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori</label>
                <input
                    type="text"
                    name="nama_kategori"
                    class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Masukkan nama kategori"
                    required
                >
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="tutupModal('modal-kategori')" class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 font-semibold">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH KEGIATAN -->
<div id="modal-kegiatan" class="hidden fixed inset-0 z-50 bg-black/50 items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md">
        <div class="flex items-center justify-between border-b px-4 py-3">
            <h3 class="font-bold text-gray-800">Tambah Jenis Kegiatan</h3>
            <button type="button" onclick="tutupModal('modal-kegiatan')" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
        </div>
        <form method="POST" action="" class="p-4">
            <input type="hidden" name="tambah_kegiatan" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kegiatan</label>
                <input
                    type="text"
                    name="nama_kegiatan"
                    class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Masukkan nama kegiatan"
                    required
                >
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="tutupModal('modal-kegiatan')" class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 font-semibold">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT KEGIATAN -->
<div id="modal-edit-kegiatan" class="hidden fixed inset-0 z-50 bg-black/50 items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md">
        <div class="flex items-center justify-between border-b px-4 py-3">
            <h3 class="font-bold text-gray-800">Edit Jenis Kegiatan</h3>
            <button type="button" onclick="tutupModal('modal-edit-kegiatan')" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
        </div>
        <form method="POST" action="" class="p-4">
            <input type="hidden" name="edit_kegiatan" value="1">
            <input type="hidden" id="edit_index_kegiatan" name="index_kegiatan">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kegiatan</label>
                <input
                    type="text"
                    id="edit_nama_kegiatan"
                    name="nama_baru"
                    class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Masukkan nama kegiatan baru"
                    required
                >
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="tutupModal('modal-edit-kegiatan')" class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 font-semibold">
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function bukaModalKategori() {
        const modal = document.getElementById('modal-kategori');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function bukaModalKegiatan() {
        const modal = document.getElementById('modal-kegiatan');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function bukaEditKegiatan(index, nama) {
        document.getElementById('edit_index_kegiatan').value = index;
        document.getElementById('edit_nama_kegiatan').value = nama;

        const modal = document.getElementById('modal-edit-kegiatan');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function tutupModal(id) {
        const modal = document.getElementById(id);
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    window.addEventListener('click', function(e) {
        ['modal-kategori', 'modal-kegiatan', 'modal-edit-kegiatan'].forEach(function(id) {
            const modal = document.getElementById(id);
            if (e.target === modal) {
                tutupModal(id);
            }
        });
    });

    document.querySelectorAll('.form-hapus-kategori').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Hapus kategori?',
                text: 'Data kategori akan dihapus permanen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('.form-hapus-kegiatan').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Hapus kegiatan?',
                text: 'Data kegiatan akan dihapus permanen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    <?php if (isset($_GET['pesan'])): ?>
        <?php if ($_GET['pesan'] === 'setting_sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Pengaturan komandan berhasil disimpan.',
                confirmButtonColor: '#2563eb'
            });
        <?php elseif ($_GET['pesan'] === 'tambah_kategori_sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Kategori berhasil ditambahkan.',
                confirmButtonColor: '#2563eb'
            });
        <?php elseif ($_GET['pesan'] === 'hapus_kategori_sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Kategori berhasil dihapus.',
                confirmButtonColor: '#2563eb'
            });
        <?php elseif ($_GET['pesan'] === 'tambah_kegiatan_sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Jenis kegiatan berhasil ditambahkan.',
                confirmButtonColor: '#2563eb'
            });
        <?php elseif ($_GET['pesan'] === 'edit_kegiatan_sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Jenis kegiatan berhasil diupdate.',
                confirmButtonColor: '#2563eb'
            });
        <?php elseif ($_GET['pesan'] === 'hapus_kegiatan_sukses'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Jenis kegiatan berhasil dihapus.',
                confirmButtonColor: '#2563eb'
            });
        <?php elseif ($_GET['pesan'] === 'edit_kegiatan_gagal'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Edit kegiatan gagal diproses.',
                confirmButtonColor: '#dc2626'
            });
        <?php else: ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Terjadi kesalahan saat memproses data.',
                confirmButtonColor: '#dc2626'
            });
        <?php endif; ?>
    <?php endif; ?>
</script>

</body>
</html>