<?php 
  $current_page = basename($_SERVER['PHP_SELF']); 
?>

<div class="md:hidden flex items-center justify-between bg-white border-b border-gray-200 p-4 sticky top-0 z-30 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-blue-600 rounded-md flex items-center justify-center text-white font-bold text-xs">BR</div>
        <h1 class="text-lg font-bold text-gray-800">Logistik</h1>
    </div>
    <button onclick="toggleSidebar()" class="text-gray-500 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-200 rounded p-1">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>
</div>

<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/60 z-40 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

<aside id="main-sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col fixed inset-y-0 left-0 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:translate-x-0 shadow-xl md:shadow-none h-screen">
  
  <div class="p-6 pb-4 flex-shrink-0 flex justify-between items-center border-b border-gray-50">
    <div class="hidden md:flex items-center gap-3">
      <div class="w-8 h-8 bg-blue-600 rounded-md flex items-center justify-center text-white font-bold text-xs shadow-sm">BR</div>
      <h1 class="text-xl font-black text-gray-800 tracking-tight">Logistik</h1>
    </div>
    
    <div class="md:hidden flex w-full justify-end">
        <button onclick="toggleSidebar()" class="text-gray-400 hover:text-red-500 p-2 rounded-full hover:bg-red-50 focus:outline-none transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
  </div>
  
  <nav class="flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-1.5 custom-scrollbar">
      
      <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'home.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="home.php">
        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        <span class="text-sm">Beranda Utama</span>
      </a>

      <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'transaksi.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="transaksi.php">
        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
        <span class="text-sm">Scanner Peminjaman</span>
      </a>

      <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'dashboard.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="dashboard.php">
        <svg fill="currentColor" height="20px" viewBox="0 0 256 256" width="20px" xmlns="http://www.w3.org/2000/svg"><path d="M224,115.55V208a16,16,0,0,1-16,16H168a16,16,0,0,1-16-16V168a8,8,0,0,0-8-8H112a8,8,0,0,0-8,8v40a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V115.55a16,16,0,0,1,5.17-11.78l80-75.48.11-.11a16,16,0,0,1,21.53,0,1.14,1.14,0,0,0,.11.11l80,75.48A16,16,0,0,1,224,115.55Z"></path></svg>
        <span class="text-sm">Lacak Pergerakan</span>
      </a>

      <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <div class="h-px bg-gray-200 my-3"></div> 
          <p class="px-3 text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Master Data</p>
          
          <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'personil.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="personil.php">
            <svg fill="currentColor" height="20px" viewBox="0 0 256 256" width="20px" xmlns="http://www.w3.org/2000/svg"><path d="M117.25,157.92a60,60,0,1,0-66.5,0A95.83,95.83,0,0,0,3.53,195.63a8,8,0,1,0,13.4,8.74,80,80,0,0,1,134.14,0,8,8,0,0,0,13.4-8.74A95.83,95.83,0,0,0,117.25,157.92ZM40,108a44,44,0,1,1,44,44A44.05,44.05,0,0,1,40,108Zm210.14,98.7a8,8,0,0,1-11.07-2.33A79.83,79.83,0,0,0,172,168a8,8,0,0,1,0-16,44,44,0,1,0-16.34-84.87,8,8,0,1,1-5.94-14.85,60,60,0,0,1,55.53,105.64,95.83,95.83,0,0,1,47.22,37.71A8,8,0,0,1,250.14,206.7Z"></path></svg>
            <span class="text-sm">Data Personil</span>
          </a>

          <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'logistik.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="logistik.php">
            <svg fill="currentColor" height="20px" viewBox="0 0 256 256" width="20px" xmlns="http://www.w3.org/2000/svg"><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm0,160H40V56H216V200ZM176,88a48,48,0,0,1-96,0,8,8,0,0,1,16,0,32,32,0,0,0,64,0,8,8,0,0,1,16,0Z"></path></svg>
            <span class="text-sm">Master Barang</span>
          </a>

          <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'kategori.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="kategori.php">
            <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            <span class="text-sm">Parameter Sistem</span>
          </a>
          
          <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'pengguna.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="pengguna.php">
            <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            <span class="text-sm">Kelola Pengguna</span>
          </a>
      <?php endif; ?>

      <div class="h-px bg-gray-200 my-3"></div> 
      <p class="px-3 text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Laporan</p>

      <a class="flex items-center gap-3 px-3 py-2.5 rounded-md transition-colors <?= $current_page == 'laporan.php' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50 hover:text-blue-600 font-medium' ?>" href="laporan.php">
        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        <span class="text-sm">Rekapitulasi PDF</span>
      </a>
  </nav>

  <div class="p-5 border-t border-gray-100 flex-shrink-0 bg-gray-50">
    <a href="logout.php" onclick="konfirmasiLogout(event)" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-md bg-white border border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300 font-bold text-sm transition-all shadow-sm">
      <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
      <span>Keluar Sistem</span>
    </a>
  </div>
</aside>

<style>
  .custom-scrollbar::-webkit-scrollbar { width: 4px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
  .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: #d1d5db; }
</style>

<script>
    function toggleSidebar() {
        document.getElementById('main-sidebar').classList.toggle('-translate-x-full');
        document.getElementById('sidebar-overlay').classList.toggle('hidden');
    }
    
    function konfirmasiLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Keluar dari Sistem?', text: "Anda harus login kembali untuk mengakses data.",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Keluar!', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = 'logout.php';
        });
    }
</script>