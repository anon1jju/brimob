<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) { 
    header("Location: signin.php"); 
    exit; 
}
if (($_SESSION['role'] ?? '') !== 'admin') { 
    header("Location: home.php"); 
    exit; 
}

require_once 'class/LogistikDB.php';
$db = new LogistikDB();

$current_username = $_SESSION['username'] ?? '';

// PROSES TAMBAH USER
if (isset($_POST['tambah_user'])) {
    $db->tambahUser(
        $_POST['username'] ?? '',
        $_POST['password'] ?? '',
        $_POST['nama_petugas'] ?? '',
        $_POST['pangkat'] ?? '',
        $_POST['nrp'] ?? '',
        $_POST['role'] ?? 'user'
    );
    header("Location: pengguna.php?pesan=tambah");
    exit;
}

// PROSES EDIT USER
if (isset($_POST['edit_user'])) {
    $db->editUser(
        $_POST['username'] ?? '',
        $_POST['password'] ?? '',
        $_POST['nama_petugas'] ?? '',
        $_POST['pangkat'] ?? '',
        $_POST['nrp'] ?? '',
        $_POST['role'] ?? 'user'
    );
    header("Location: pengguna.php?pesan=edit");
    exit;
}

// PROSES HAPUS
if (isset($_POST['hapus_user'])) {
    $username_hapus = $_POST['username'] ?? '';

    if ($username_hapus !== '' && $username_hapus !== $current_username) {
        $db->hapusUser($username_hapus);
        header("Location: pengguna.php?pesan=hapus");
        exit;
    }

    header("Location: pengguna.php?pesan=gagal_hapus");
    exit;
}

$semua_user = $db->getAllUsers();
$semua_user = is_array($semua_user) ? $semua_user : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <title>Kelola Pengguna - Logistik</title>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-6 overflow-hidden flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Manajemen Hak Akses</h1>
                <p class="text-gray-500 text-sm">Daftar petugas dan NRP.</p>
            </div>
            <button onclick="bukaModalTambah()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded font-bold text-sm">
                + Tambah Petugas
            </button>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm flex-1 flex flex-col min-h-0 overflow-hidden">
            <div class="overflow-auto custom-scroll flex-1 w-full h-full">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-50 sticky top-0 border-b border-gray-200 text-[10px] uppercase text-gray-400 font-bold">
                        <tr>
                            <th class="px-6 py-3">Nama Petugas</th>
                            <th class="px-6 py-3">Pangkat</th>
                            <th class="px-6 py-3">NRP</th>
                            <th class="px-6 py-3">Username</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($semua_user as $u): ?>
                        <tr class="hover:bg-gray-50 text-sm">
                            <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($u['nama_petugas'] ?? '-') ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($u['pangkat'] ?? '-') ?></td>
                            <td class="px-6 py-4 font-mono"><?= htmlspecialchars($u['nrp'] ?? '-') ?></td>
                            <td class="px-6 py-4">
                                <span class="bg-gray-100 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($u['username'] ?? '-') ?></span>
                            </td>
                            <td class="px-6 py-4 uppercase font-bold text-[10px] text-blue-600"><?= htmlspecialchars($u['role'] ?? '-') ?></td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    onclick="bukaModalEdit(
                                        '<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($u['nama_petugas'] ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($u['pangkat'] ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($u['nrp'] ?? '', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($u['role'] ?? 'user', ENT_QUOTES) ?>'
                                    )"
                                    class="text-indigo-600 hover:underline font-bold text-xs"
                                >
                                    Edit
                                </button>

                                <?php if (($u['username'] ?? '') !== $current_username): ?>
                                    <form method="POST" action="" class="inline-block ml-3" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                        <input type="hidden" name="hapus_user" value="1">
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($u['username'] ?? '') ?>">
                                        <button class="text-red-500 hover:underline font-bold text-xs">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (count($semua_user) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">Belum ada data pengguna.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        <?php if (isset($_GET['pesan'])): ?>
            <?php if ($_GET['pesan'] === 'tambah'): ?>
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Petugas berhasil ditambahkan.', timer: 1800, showConfirmButton: false });
            <?php elseif ($_GET['pesan'] === 'edit'): ?>
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data petugas berhasil diperbarui.', timer: 1800, showConfirmButton: false });
            <?php elseif ($_GET['pesan'] === 'hapus'): ?>
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Petugas berhasil dihapus.', timer: 1800, showConfirmButton: false });
            <?php elseif ($_GET['pesan'] === 'gagal_hapus'): ?>
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'User aktif tidak boleh dihapus.', timer: 1800, showConfirmButton: false });
            <?php endif; ?>
        <?php endif; ?>

        function getFormHTML(isEdit, username, nama, pangkat, nrp, role) {
            return `
                <div class="text-left space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Nama Lengkap</label>
                        <input id="swal-nama" type="text" class="w-full border rounded p-2" value="${nama}">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Pangkat</label>
                            <input id="swal-pangkat" type="text" class="w-full border rounded p-2" value="${pangkat}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">NRP</label>
                            <input id="swal-nrp" type="number" class="w-full border rounded p-2" value="${nrp}">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Username</label>
                        <input id="swal-username" type="text" class="w-full border rounded p-2" value="${username}" ${isEdit ? 'readonly' : ''}>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Password</label>
                        <input id="swal-password" type="password" class="w-full border rounded p-2" placeholder="${isEdit ? 'Kosongkan jika tidak diubah' : '********'}">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Role</label>
                        <select id="swal-role" class="w-full border rounded p-2">
                            <option value="user" ${role === 'user' ? 'selected' : ''}>User</option>
                            <option value="admin" ${role === 'admin' ? 'selected' : ''}>Admin</option>
                        </select>
                    </div>
                </div>
            `;
        }

        function bukaModalTambah() {
            Swal.fire({
                title: 'Tambah Petugas',
                html: getFormHTML(false, '', '', '', '', 'user'),
                confirmButtonText: 'Simpan',
                focusConfirm: false,
                preConfirm: () => {
                    const data = {
                        username: document.getElementById('swal-username').value.trim(),
                        password: document.getElementById('swal-password').value.trim(),
                        nama: document.getElementById('swal-nama').value.trim(),
                        pangkat: document.getElementById('swal-pangkat').value.trim(),
                        nrp: document.getElementById('swal-nrp').value.trim(),
                        role: document.getElementById('swal-role').value
                    };

                    if (!data.username || !data.password || !data.nama) {
                        Swal.showValidationMessage('Username, password, dan nama wajib diisi.');
                        return false;
                    }

                    submitForm('tambah_user', data);
                }
            });
        }

        function bukaModalEdit(username, nama, pangkat, nrp, role) {
            Swal.fire({
                title: 'Edit Petugas',
                html: getFormHTML(true, username, nama, pangkat, nrp, role),
                confirmButtonText: 'Update',
                focusConfirm: false,
                preConfirm: () => {
                    const data = {
                        username: username,
                        password: document.getElementById('swal-password').value.trim(),
                        nama: document.getElementById('swal-nama').value.trim(),
                        pangkat: document.getElementById('swal-pangkat').value.trim(),
                        nrp: document.getElementById('swal-nrp').value.trim(),
                        role: document.getElementById('swal-role').value
                    };

                    if (!data.username || !data.nama) {
                        Swal.showValidationMessage('Username dan nama wajib diisi.');
                        return false;
                    }

                    submitForm('edit_user', data);
                }
            });
        }

        function submitForm(action, data) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            for (let key in data) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = (key === 'nama') ? 'nama_petugas' : key;
                input.value = data[key];
                form.appendChild(input);
            }

            const act = document.createElement('input');
            act.type = 'hidden';
            act.name = action;
            act.value = '1';
            form.appendChild(act);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>