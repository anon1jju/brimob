<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) { header("Location: signin.php"); exit; }
if ($_SESSION['role'] !== 'admin') { header("Location: home.php"); exit; }

require_once 'class/LogistikDB.php';
$db = new LogistikDB();

// PROSES TAMBAH USER
if (isset($_POST['tambah_user'])) {
    $res = $db->tambahUser($_POST['username'], $_POST['password'], $_POST['nama_petugas'], $_POST['pangkat'], $_POST['nrp'], $_POST['role']);
    header("Location: pengguna.php?pesan=tambah"); exit;
}

// PROSES EDIT USER
if (isset($_POST['edit_user'])) {
    $db->editUser($_POST['username'], $_POST['password'], $_POST['nama_petugas'], $_POST['pangkat'], $_POST['nrp'], $_POST['role']);
    header("Location: pengguna.php?pesan=edit"); exit;
}

// PROSES HAPUS
if (isset($_POST['hapus_user'])) {
    $db->hapusUser($_POST['username']);
    header("Location: pengguna.php?pesan=hapus"); exit;
}

$semua_user = $db->getAllUsers();
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
            <button onclick="bukaModalTambah()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded font-bold text-sm">+ Tambah Petugas</button>
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
                            <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($u['nama_petugas']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($u['pangkat'] ?? '-') ?></td>
                            <td class="px-6 py-4 font-mono"><?= htmlspecialchars($u['nrp'] ?? '-') ?></td>
                            <td class="px-6 py-4"><span class="bg-gray-100 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($u['username']) ?></span></td>
                            <td class="px-6 py-4 uppercase font-bold text-[10px] text-blue-600"><?= $u['role'] ?></td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="bukaModalEdit('<?= $u['username'] ?>', '<?= addslashes($u['nama_petugas']) ?>', '<?= addslashes($u['pangkat'] ?? '') ?>', '<?= addslashes($u['nrp'] ?? '') ?>', '<?= $u['role'] ?>')" class="text-indigo-600 hover:underline font-bold text-xs">Edit</button>
                                <?php if($u['username'] !== $_SESSION['username']): ?>
                                    <form method="POST" action="" class="inline-block ml-3">
                                        <input type="hidden" name="hapus_user" value="1"><input type="hidden" name="username" value="<?= $u['username'] ?>">
                                        <button class="text-red-500 hover:underline font-bold text-xs">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function getFormHTML(isEdit, username, nama, pangkat, nrp, role) {
            return `
                <div class="text-left space-y-3">
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Nama Lengkap</label>
                    <input id="swal-nama" type="text" class="w-full border rounded p-2" value="${nama}"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="block text-xs font-bold text-gray-500 uppercase">Pangkat</label>
                        <input id="swal-pangkat" type="text" class="w-full border rounded p-2" value="${pangkat}"></div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase">NRP</label>
                        <input id="swal-nrp" type="number" class="w-full border rounded p-2" value="${nrp}"></div>
                    </div>
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Username</label>
                    <input id="swal-username" type="text" class="w-full border rounded p-2" value="${username}" ${isEdit ? 'readonly':''}></div>
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Password</label>
                    <input id="swal-password" type="password" class="w-full border rounded p-2" placeholder="********"></div>
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Role</label>
                    <select id="swal-role" class="w-full border rounded p-2"><option value="user" ${role=='user'?'selected':''}>User</option><option value="admin" ${role=='admin'?'selected':''}>Admin</option></select></div>
                </div>`;
        }

        function bukaModalTambah() {
            Swal.fire({ title: 'Tambah Petugas', html: getFormHTML(false, '', '', '', '', 'user'), confirmButtonText: 'Simpan',
                preConfirm: () => {
                    const data = { username: document.getElementById('swal-username').value, password: document.getElementById('swal-password').value, nama: document.getElementById('swal-nama').value, pangkat: document.getElementById('swal-pangkat').value, nrp: document.getElementById('swal-nrp').value, role: document.getElementById('swal-role').value };
                    submitForm('tambah_user', data);
                }
            });
        }

        function bukaModalEdit(username, nama, pangkat, nrp, role) {
            Swal.fire({ title: 'Edit Petugas', html: getFormHTML(true, username, nama, pangkat, nrp, role), confirmButtonText: 'Update',
                preConfirm: () => {
                    const data = { username: username, password: document.getElementById('swal-password').value, nama: document.getElementById('swal-nama').value, pangkat: document.getElementById('swal-pangkat').value, nrp: document.getElementById('swal-nrp').value, role: document.getElementById('swal-role').value };
                    submitForm('edit_user', data);
                }
            });
        }

        function submitForm(action, data) {
            const form = document.createElement('form'); form.method = 'POST'; form.action = '';
            for(let key in data) {
                const i = document.createElement('input'); i.type = 'hidden'; i.name = (key=='nama')?'nama_petugas':key; i.value = data[key];
                form.appendChild(i);
            }
            const act = document.createElement('input'); act.type = 'hidden'; act.name = action; act.value = '1';
            form.appendChild(act);
            document.body.appendChild(form); form.submit();
        }
    </script>
</body>
</html>