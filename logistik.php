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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_logistik'])) {
    $db->tambahLogistikUmum($_POST['nama_barang'], $_POST['kategori'], $_POST['total_stok']);
    header("Location: logistik.php?pesan=tambah_sukses");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_logistik'])) {
    $db->editLogistikUmum($_POST['id_barang'], $_POST['nama_barang'], $_POST['kategori'], $_POST['total_stok']);
    header("Location: logistik.php?pesan=edit_sukses");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_logistik'])) {
    $db->hapusLogistikUmum($_POST['id_barang']);
    header("Location: logistik.php?pesan=hapus_sukses");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kondisi'])) {
    $db->updateStokRusak($_POST['id_barang'], $_POST['jumlah_proses'], $_POST['aksi_kondisi']);
    header("Location: logistik.php?pesan=kondisi_sukses");
    exit;
}

$semua_logistik = json_decode(file_get_contents('data/logistik_umum.json'), true) ?? [];
$kategori_data = json_decode(file_get_contents('data/kategori.json'), true) ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B600%3B700&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" rel="stylesheet" />
    <title>Master Logistik - Logistik Brimob</title>
    <style>body { font-family: 'Inter', 'Noto Sans', sans-serif; }</style>
</head>
<body class="bg-gray-50 flex flex-col md:flex-row h-screen overflow-hidden">
  
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-4 md:p-8 overflow-y-auto relative w-full">
    
    <div class="mb-6 md:mb-8 flex flex-col lg:flex-row lg:justify-between lg:items-end gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Master Logistik Umum</h1>
            <p class="text-sm md:text-base text-gray-500 mt-1">Kelola stok perlengkapan, helm, rompi, dan barang umum lainnya.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="pencarian-logistik" onkeyup="cariLogistik()" class="w-full pl-10 pr-4 py-2.5 rounded-md border border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white shadow-sm" placeholder="Cari Nama / Kode Barang...">
            </div>
            
            <button onclick="toggleModal('modal-tambah')" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-md font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-colors whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Barang Baru
            </button>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm w-full overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap min-w-max">
            <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 md:px-6 py-4 font-semibold">Kode / Nama Barang</th>
                    <th class="px-4 md:px-6 py-4 font-semibold text-center">Rincian Kondisi Stok</th>
                    <th class="px-4 md:px-6 py-4 font-semibold text-center">Total Awal</th>
                    <th class="px-4 md:px-6 py-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="body-tabel-logistik">
                <?php if(count($semua_logistik) > 0): foreach($semua_logistik as $l): 
                    $stok_rusak = isset($l['stok_rusak']) ? (int)$l['stok_rusak'] : 0;
                    $stok_tersedia = (int)$l['stok_tersedia'];
                    $total_stok = (int)$l['total_stok'];
                    $stok_dipinjam = $total_stok - $stok_tersedia - $stok_rusak;
                ?>
                <tr class="hover:bg-gray-50 transition-colors baris-data">
                    <td class="px-4 md:px-6 py-4">
                        <p class="font-bold text-gray-800 text-base nama-logistik"><?= htmlspecialchars($l['nama_barang']) ?></p>
                        <p class="text-gray-500 text-xs font-mono mt-0.5 mb-1.5 kode-logistik"><?= $l['id_barang'] ?></p>
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600"><?= htmlspecialchars($l['kategori']) ?></span>
                    </td>
                    
                    <td class="px-4 md:px-6 py-4">
                        <div class="flex items-center justify-center gap-2 text-xs font-bold">
                            <div class="flex flex-col items-center bg-green-50 border border-green-100 rounded px-3 py-1.5 min-w-[70px]" title="Siap Dipinjam">
                                <span class="text-green-600 text-[10px] uppercase">Tersedia</span>
                                <span class="text-green-700 text-base"><?= $stok_tersedia ?></span>
                            </div>
                            <div class="flex flex-col items-center bg-blue-50 border border-blue-100 rounded px-3 py-1.5 min-w-[70px]" title="Sedang Dibawa Bertugas">
                                <span class="text-blue-600 text-[10px] uppercase">Dipinjam</span>
                                <span class="text-blue-700 text-base"><?= $stok_dipinjam ?></span>
                            </div>
                            <div class="flex flex-col items-center <?= $stok_rusak > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200' ?> border rounded px-3 py-1.5 min-w-[70px]" title="Rusak / Perbaikan">
                                <span class="<?= $stok_rusak > 0 ? 'text-red-600' : 'text-gray-400' ?> text-[10px] uppercase">Rusak</span>
                                <span class="<?= $stok_rusak > 0 ? 'text-red-700 animate-pulse' : 'text-gray-500' ?> text-base"><?= $stok_rusak ?></span>
                            </div>
                        </div>
                    </td>
                    
                    <td class="px-4 md:px-6 py-4 text-center">
                        <span class="font-black text-gray-800 text-lg"><?= $total_stok ?></span>
                    </td>
                    
                    <td class="px-4 md:px-6 py-4 text-right">
                        <button type="button" onclick="bukaKondisi('<?= $l['id_barang'] ?>', '<?= addslashes($l['nama_barang']) ?>', <?= $stok_tersedia ?>, <?= $stok_rusak ?>)" class="text-orange-600 hover:text-orange-800 font-medium text-sm px-2.5 py-1.5 bg-orange-50 border border-orange-100 rounded hover:bg-orange-100 transition-colors mr-1" title="Kelola Barang Rusak">🛠️ Kondisi</button>
                        
                        <a href="cetak_qr.php?id=<?= $l['id_barang'] ?>&nama=<?= urlencode($l['nama_barang']) ?>&tipe=logistik" target="_blank" class="text-gray-600 hover:text-gray-900 font-medium text-sm px-2.5 py-1.5 bg-gray-100 border border-gray-200 rounded hover:bg-gray-200 transition-colors mr-1" title="Cetak Barcode QR">🖨️</a>

                        <button onclick="bukaEdit('<?= $l['id_barang'] ?>', '<?= addslashes($l['nama_barang']) ?>', '<?= htmlspecialchars($l['kategori']) ?>', <?= $total_stok ?>)" class="text-blue-600 hover:text-blue-800 font-medium text-sm px-2 py-1 bg-blue-50 rounded hover:bg-blue-100 transition-colors">Edit</button>
                        <span class="text-gray-300 mx-1">|</span>
                        
                        <form id="form-hapus-<?= $l['id_barang'] ?>" method="POST" action="" class="inline-block">
                            <input type="hidden" name="hapus_logistik" value="1">
                            <input type="hidden" name="id_barang" value="<?= $l['id_barang'] ?>">
                            <button type="button" onclick="konfirmasiHapus('form-hapus-<?= $l['id_barang'] ?>', '<?= addslashes($l['nama_barang']) ?>')" class="text-red-600 hover:text-red-800 font-medium text-sm px-2 py-1 bg-red-50 rounded hover:bg-red-100 transition-colors">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr id="baris-kosong"><td colspan="4" class="px-4 py-8 text-center text-gray-400">Belum ada master logistik.</td></tr>
                <?php endif; ?>
                
                <tr id="baris-tidak-ditemukan" class="hidden"><td colspan="4" class="px-4 py-8 text-center text-red-500 font-medium">Pencarian tidak ditemukan.</td></tr>
            </tbody>
        </table>
    </div>
  </main>

  <div id="modal-kondisi" class="fixed inset-0 z-50 hidden bg-black/60 flex items-center justify-center backdrop-blur-sm p-4 sm:p-0">
      <div class="bg-white rounded-lg shadow-2xl w-[95%] md:w-full max-w-md mx-auto overflow-hidden border border-gray-200 flex flex-col">
          <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-orange-50">
              <h2 class="text-base md:text-lg font-bold text-orange-800 flex items-center gap-2">🛠️ Kelola Kondisi Fisik</h2>
              <button onclick="toggleModal('modal-kondisi')" class="text-orange-400 hover:text-orange-600 font-bold text-2xl leading-none">&times;</button>
          </div>
          <div class="p-6">
            <h3 id="kondisi_nama_barang" class="font-bold text-gray-800 text-lg mb-4 text-center border-b pb-2">Nama Barang</h3>
            
            <form method="POST" action="" id="form-kondisi">
                <input type="hidden" name="update_kondisi" value="1">
                <input type="hidden" name="id_barang" id="kondisi_id_barang">
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Aksi Perubahan</label>
                    <select name="aksi_kondisi" id="aksi_kondisi" class="w-full rounded-md border-gray-300 text-sm p-3 font-bold focus:border-orange-500 focus:ring-orange-500" onchange="ubahBatasMaxKondisi()">
                        <option value="tambah_rusak">Pindahkan ke Barang Rusak 🔻</option>
                        <option value="perbaiki">Barang Selesai Diperbaiki ✅</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Jumlah (Qty)</label>
                    <div class="flex items-center gap-3">
                        <input type="number" name="jumlah_proses" id="jumlah_proses" min="1" value="1" class="w-full rounded-md border-gray-300 text-lg text-center font-bold p-3 focus:border-orange-500 focus:ring-orange-500" required>
                        <span class="text-xs text-gray-500 font-medium whitespace-nowrap" id="hint_max_kondisi">Max: 0</span>
                    </div>
                </div>
            </form>
          </div>
          <div class="flex justify-end gap-3 p-4 border-t border-gray-200 bg-gray-50">
              <button type="button" onclick="toggleModal('modal-kondisi')" class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200 rounded transition-colors">Batal</button>
              <button type="submit" form="form-kondisi" class="px-4 py-2 text-sm font-bold text-white bg-orange-600 hover:bg-orange-700 rounded transition-colors shadow-sm">Simpan Status</button>
          </div>
      </div>
  </div>

  <div id="modal-tambah" class="fixed inset-0 z-50 hidden bg-black/60 flex items-center justify-center backdrop-blur-sm p-4 sm:p-0">
      <div class="bg-white rounded-lg shadow-2xl w-[95%] md:w-full max-w-md mx-auto overflow-hidden border border-gray-200 flex flex-col">
          <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
              <h2 class="text-base font-bold text-gray-800">Tambah Logistik Baru</h2>
              <button onclick="toggleModal('modal-tambah')" class="text-gray-400 hover:text-red-500 font-bold text-xl leading-none">&times;</button>
          </div>
          <div class="p-6">
            <form method="POST" action="" id="form-tambah">
                <input type="hidden" name="tambah_logistik" value="1">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Barang</label>
                        <input type="text" name="nama_barang" class="w-full rounded-md border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                        <select name="kategori" class="w-full rounded-md border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <?php foreach($kategori_data as $kat): ?>
                                <option value="<?= htmlspecialchars($kat['nama_kategori']) ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Stok Awal</label>
                        <input type="number" name="total_stok" min="1" class="w-full rounded-md border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                </div>
            </form>
          </div>
          <div class="flex justify-end gap-3 p-4 border-t border-gray-200 bg-gray-50">
              <button type="button" onclick="toggleModal('modal-tambah')" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 rounded">Batal</button>
              <button type="submit" form="form-tambah" class="px-4 py-2 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded">Simpan</button>
          </div>
      </div>
  </div>

  <div id="modal-edit" class="fixed inset-0 z-50 hidden bg-black/60 flex items-center justify-center backdrop-blur-sm p-4 sm:p-0">
      <div class="bg-white rounded-lg shadow-2xl w-[95%] md:w-full max-w-md mx-auto overflow-hidden border border-gray-200 flex flex-col">
          <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
              <h2 class="text-base font-bold text-gray-800">Edit Logistik</h2>
              <button onclick="toggleModal('modal-edit')" class="text-gray-400 hover:text-red-500 font-bold text-xl leading-none">&times;</button>
          </div>
          <div class="p-6">
            <form method="POST" action="" id="form-edit">
                <input type="hidden" name="edit_logistik" value="1">
                <input type="hidden" name="id_barang" id="edit_id_barang">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Barang</label>
                        <input type="text" name="nama_barang" id="edit_nama_barang" class="w-full rounded-md border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                        <select name="kategori" id="edit_kategori" class="w-full rounded-md border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <?php foreach($kategori_data as $kat): ?>
                                <option value="<?= htmlspecialchars($kat['nama_kategori']) ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Stok Keseluruhan</label>
                        <input type="number" name="total_stok" id="edit_total_stok" min="1" class="w-full rounded-md border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <p class="text-[10px] text-red-500 mt-1">Peringatan: Mengubah total stok dapat mengacaukan kalkulasi jika barang sedang dipinjam.</p>
                    </div>
                </div>
            </form>
          </div>
          <div class="flex justify-end gap-3 p-4 border-t border-gray-200 bg-gray-50">
              <button type="button" onclick="toggleModal('modal-edit')" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 rounded">Batal</button>
              <button type="submit" form="form-edit" class="px-4 py-2 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded">Simpan Perubahan</button>
          </div>
      </div>
  </div>

  <script>
      // --- FITUR LIVE SEARCH LOGISTIK ---
      function cariLogistik() {
          let input = document.getElementById("pencarian-logistik").value.toLowerCase();
          let barisData = document.querySelectorAll(".baris-data");
          let ditemukan = false;

          barisData.forEach(function(baris) {
              let teksNama = baris.querySelector(".nama-logistik").innerText.toLowerCase();
              let teksKode = baris.querySelector(".kode-logistik").innerText.toLowerCase();
              
              if (teksNama.includes(input) || teksKode.includes(input)) {
                  baris.style.display = "";
                  ditemukan = true;
              } else {
                  baris.style.display = "none";
              }
          });

          let pesanKosong = document.getElementById("baris-tidak-ditemukan");
          if (!ditemukan && input !== "") {
              pesanKosong.classList.remove("hidden");
          } else {
              pesanKosong.classList.add("hidden");
          }
      }
      // ----------------------------------

      function toggleModal(modalID){
          document.getElementById(modalID).classList.toggle('hidden');
      }

      function bukaEdit(id, nama, kategori, total) {
          document.getElementById('edit_id_barang').value = id;
          document.getElementById('edit_nama_barang').value = nama;
          document.getElementById('edit_kategori').value = kategori;
          document.getElementById('edit_total_stok').value = total;
          toggleModal('modal-edit');
      }

      let tempTersedia = 0;
      let tempRusak = 0;

      function bukaKondisi(id, nama, stokTersedia, stokRusak) {
          document.getElementById('kondisi_id_barang').value = id;
          document.getElementById('kondisi_nama_barang').innerText = nama;
          tempTersedia = parseInt(stokTersedia);
          tempRusak = parseInt(stokRusak);
          document.getElementById('aksi_kondisi').value = 'tambah_rusak';
          document.getElementById('jumlah_proses').value = 1;
          ubahBatasMaxKondisi();
          toggleModal('modal-kondisi');
      }

      function ubahBatasMaxKondisi() {
          let aksi = document.getElementById('aksi_kondisi').value;
          let inputJumlah = document.getElementById('jumlah_proses');
          let hintMax = document.getElementById('hint_max_kondisi');
          if (aksi === 'tambah_rusak') {
              inputJumlah.max = tempTersedia;
              hintMax.innerText = `Max: ${tempTersedia} (Dari stok gudang)`;
              if(tempTersedia === 0) { inputJumlah.value = 0; }
          } else {
              inputJumlah.max = tempRusak;
              hintMax.innerText = `Max: ${tempRusak} (Barang rusak saat ini)`;
              if(tempRusak === 0) { inputJumlah.value = 0; }
          }
      }

      function konfirmasiHapus(formId, namaBarang) {
          Swal.fire({
              title: 'Hapus Logistik?', html: `Anda yakin ingin menghapus <b>${namaBarang}</b> dari Master Data?`,
              icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6b7280',
              confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
          }).then((result) => {
              if (result.isConfirmed) { document.getElementById(formId).submit(); }
          });
      }

      <?php if(isset($_GET['pesan'])): ?>
      document.addEventListener('DOMContentLoaded', function() {
          let pesan = '';
          <?php 
              if($_GET['pesan'] == 'tambah_sukses') echo "pesan = 'Barang logistik baru telah disimpan.';";
              if($_GET['pesan'] == 'edit_sukses') echo "pesan = 'Perubahan data logistik telah disimpan.';";
              if($_GET['pesan'] == 'hapus_sukses') echo "pesan = 'Data barang telah dihapus dari sistem.';";
              if($_GET['pesan'] == 'kondisi_sukses') echo "pesan = 'Status kondisi stok berhasil diperbarui.';";
          ?>
          Swal.fire({ icon: 'success', title: 'Berhasil!', text: pesan, timer: 2500, showConfirmButton: false });
      });
      <?php endif; ?>
  </script>
</body>
</html>