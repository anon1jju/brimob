<?php
date_default_timezone_set('Asia/Jakarta');

class LogistikDB {
    private $dataDir;

    // Konstruktor: Set folder default ke 'data/'
    public function __construct($folderPath = 'data/') {
        $this->dataDir = $folderPath;
    }

    // --- FUNGSI INTERNAL ---
    private function read($filename) {
        $filepath = $this->dataDir . $filename;
        if (!file_exists($filepath)) return [];
        return json_decode(file_get_contents($filepath), true) ?? [];
    }

    private function write($filename, $data) {
        $filepath = $this->dataDir . $filename;
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }

    // --- FUNGSI AUTENTIKASI ---
    public function login($email, $password) {
        $pengguna = $this->read('pengguna.json');
        foreach ($pengguna as $user) {
            if ($user['email'] === $email && $user['password'] === $password) {
                return $user; // Kembalikan data user jika cocok
            }
        }
        return false; // Gagal login
    }

    // --- FUNGSI PENCARIAN (DASHBOARD) ---
    public function cariAnggotaDanSenjata($nrp) {
        $anggota_data = $this->read('anggota.json');
        $senjata_data = $this->read('senjata.json');
        
        $hasil = ['anggota' => null, 'senjata' => null];

        // 1. Cari Anggota
        foreach ($anggota_data as $anggota) {
            if ($anggota['nrp'] == $nrp) {
                $hasil['anggota'] = $anggota;
                break;
            }
        }

        // 2. Jika anggota ketemu, cari senjatanya
        if ($hasil['anggota']) {
            foreach ($senjata_data as $senjata) {
                if ($senjata['id_pemegang_tetap'] == $hasil['anggota']['id_anggota']) {
                    $hasil['senjata'] = $senjata;
                    break;
                }
            }
        }

        return $hasil;
    }

    // --- FUNGSI TRANSAKSI ---
    public function prosesTransaksiSenjata($nomor_seri, $id_anggota, $aksi, $keterangan) {
        $senjata_data = $this->read('senjata.json');
        $riwayat_data = $this->read('riwayat.json');

        $status_baru = ($aksi == 'keluar') ? 'Dibawa Bertugas' : 'Di Gudang';
        $jenis_transaksi = ($aksi == 'keluar') ? 'Keluar Gudang' : 'Masuk Gudang';
        $keterangan_fix = !empty($keterangan) ? $keterangan : 'Tugas Rutin';

        // 1. Update status di file senjata.json
        foreach ($senjata_data as &$senjata) {
            if ($senjata['nomor_seri'] === $nomor_seri) {
                $senjata['status_lokasi'] = $status_baru;
                break;
            }
        }
        $this->write('senjata.json', $senjata_data);

        // 2. Catat ke file riwayat.json
        $riwayat_baru = [
            "id_transaksi" => "TRX-" . time(),
            "waktu" => date('Y-m-d H:i:s'),
            "id_anggota" => $id_anggota,
            "jenis_transaksi" => $jenis_transaksi,
            "item" => "Senjata: " . $nomor_seri,
            "keterangan" => $keterangan_fix
        ];
        array_unshift($riwayat_data, $riwayat_baru); // Taruh di paling atas
        $this->write('riwayat.json', $riwayat_data);

        return true;
    }

    // --- FUNGSI RIWAYAT ---
    public function getRiwayatTerakhir($limit = 5) {
        $riwayat_data = $this->read('riwayat.json');
        return array_slice($riwayat_data, 0, $limit);
    }
    
    // --- FUNGSI DATA PERSONIL ---
    
    // 1. Ambil semua data anggota beserta senjatanya
    public function getAllPersonil() {
        $anggota_data = $this->read('anggota.json');
        $senjata_data = $this->read('senjata.json');
        
        $hasil = [];
        foreach ($anggota_data as $anggota) {
            $anggota['senjata'] = null; // Default kosong
            // Cari senjata miliknya
            foreach ($senjata_data as $senjata) {
                if ($senjata['id_pemegang_tetap'] == $anggota['id_anggota']) {
                    $anggota['senjata'] = $senjata;
                    break;
                }
            }
            $hasil[] = $anggota;
        }
        return $hasil;
    }

    // 2. Tambah Anggota Baru
    public function tambahAnggota($nama, $nrp, $satuan) {
        $anggota_data = $this->read('anggota.json');
        
        // Buat ID unik (contoh: ANG-17123098)
        $id_baru = "ANG-" . time(); 
        
        $anggota_baru = [
            "id_anggota" => $id_baru,
            "nama" => strtoupper($nama),
            "nrp" => $nrp,
            "satuan" => $satuan
        ];
        
        array_push($anggota_data, $anggota_baru);
        $this->write('anggota.json', $anggota_data);
        return $id_baru;
    }

    // 3. Daftarkan Senjata ke Anggota
    public function tambahSenjata($nomor_seri, $jenis_senjata, $id_anggota) {
        $senjata_data = $this->read('senjata.json');
        
        $senjata_baru = [
            "nomor_seri" => strtoupper($nomor_seri),
            "jenis_senjata" => strtoupper($jenis_senjata),
            "id_pemegang_tetap" => $id_anggota,
            "status_lokasi" => "Di Gudang"
        ];
        
        array_push($senjata_data, $senjata_baru);
        $this->write('senjata.json', $senjata_data);
        return true;
    }
    
    // --- FUNGSI LOGISTIK UMUM (SISTEM STOK) ---
    
    // 1. Ambil semua data logistik umum
    public function getLogistikUmum() {
        return $this->read('logistik_umum.json');
    }

    // 3. Proses Transaksi (Pinjam = Kurang Stok, Kembali = Tambah Stok)
    public function transaksiLogistikUmum($id_barang, $nrp, $jumlah, $aksi, $keterangan) {
        $logistik = $this->read('logistik_umum.json');
        $anggota_data = $this->read('anggota.json');
        $riwayat_data = $this->read('riwayat.json');

        // Validasi: Cari ID Anggota berdasarkan NRP
        $id_anggota = null;
        foreach ($anggota_data as $anggota) {
            if ($anggota['nrp'] == $nrp) {
                $id_anggota = $anggota['id_anggota'];
                break;
            }
        }

        if (!$id_anggota) return "Gagal: NRP Anggota tidak ditemukan di database!";

        $jumlah = (int)$jumlah;
        $nama_barang = "";
        $stok_berhasil = false;

        // Potong atau Tambah Stok
        foreach ($logistik as &$item) {
            if ($item['id_barang'] == $id_barang) {
                $nama_barang = $item['nama_barang'];
                
                if ($aksi == 'pinjam') {
                    if ($item['stok_tersedia'] < $jumlah) return "Gagal: Sisa stok tidak mencukupi!";
                    $item['stok_tersedia'] -= $jumlah;
                    $jenis_transaksi = 'Pinjam Barang';
                } else { 
                    // Aksi Kembali
                    $item['stok_tersedia'] += $jumlah;
                    // Pastikan stok tidak melebihi batas awal saat dikembalikan
                    if ($item['stok_tersedia'] > $item['total_stok']) {
                        $item['stok_tersedia'] = $item['total_stok'];
                    }
                    $jenis_transaksi = 'Kembali Barang';
                }
                $stok_berhasil = true;
                break;
            }
        }

        if ($stok_berhasil) {
            $this->write('logistik_umum.json', $logistik);

            // Catat ke log riwayat
            $riwayat_baru = [
                "id_transaksi" => "TRX-" . time(),
                "waktu" => date('Y-m-d H:i:s'),
                "id_anggota" => $id_anggota,
                "jenis_transaksi" => $jenis_transaksi,
                "item" => $jumlah . "x " . $nama_barang,
                "keterangan" => empty($keterangan) ? 'Giat Operasional' : $keterangan
            ];
            array_unshift($riwayat_data, $riwayat_baru);
            $this->write('riwayat.json', $riwayat_data);
            
            return "sukses";
        }
        
        return "Gagal: Barang tidak ditemukan.";
    }
    
    // 3. Update / Edit Barang
    public function updateLogistikUmum($id_barang, $nama, $kategori, $stok_baru) {
        $logistik = $this->read('logistik_umum.json');
        
        foreach ($logistik as &$item) {
            if ($item['id_barang'] == $id_barang) {
                $item['nama_barang'] = strtoupper($nama);
                $item['kategori'] = $kategori;
                
                // Hitung selisih agar stok_tersedia ikut menyesuaikan tanpa merusak data peminjaman
                $selisih = (int)$stok_baru - $item['total_stok'];
                $item['total_stok'] = (int)$stok_baru;
                $item['stok_tersedia'] += $selisih; 
                
                break;
            }
        }
        $this->write('logistik_umum.json', $logistik);
        return true;
    }
    
    // --- FUNGSI MASTER KATEGORI ---
    
    public function getKategori() {
        return $this->read('kategori.json');
    }

    public function tambahKategori($nama) {
        $kategori = $this->read('kategori.json');
        $kat_baru = [
            "id_kategori" => "KAT-" . time(),
            "nama_kategori" => strtoupper($nama)
        ];
        array_push($kategori, $kat_baru);
        $this->write('kategori.json', $kategori);
        return true;
    }

    public function updateKategori($id_kategori, $nama) {
        $kategori = $this->read('kategori.json');
        foreach ($kategori as &$kat) {
            if ($kat['id_kategori'] == $id_kategori) {
                $kat['nama_kategori'] = strtoupper($nama);
                break;
            }
        }
        $this->write('kategori.json', $kategori);
        return true;
    }

    public function hapusKategori($id_kategori) {
        $kategori = $this->read('kategori.json');
        $kategori = array_filter($kategori, function($kat) use ($id_kategori) {
            return $kat['id_kategori'] !== $id_kategori;
        });
        $this->write('kategori.json', array_values($kategori));
        return true;
    }
    
    // --- FUNGSI TRANSAKSI BARCODE / KASIR ---

    // Ambil semua data senjata untuk dicocokkan dengan scanner
    public function getAllSenjata() {
        return $this->read('senjata.json');
    }

    // Proses keranjang peminjaman (Bisa campur senjata & logistik)
    public function prosesTransaksiKeranjang($nrp, $jenis_pinjaman, $keterangan, $cart_json) {
        $anggota_data = $this->read('anggota.json');
        $senjata_data = $this->read('senjata.json');
        $logistik_data = $this->read('logistik_umum.json');
        $riwayat_data = $this->read('riwayat.json');

        // 1. Validasi NRP
        $anggota = null;
        foreach ($anggota_data as $a) {
            if ($a['nrp'] == $nrp) { $anggota = $a; break; }
        }
        if (!$anggota) return "Gagal: NRP tidak ditemukan!";

        // Decode string keranjang dari Javascript
        $cart_items = json_decode($cart_json, true);
        if (empty($cart_items)) return "Gagal: Keranjang kosong!";

        $waktu = date('Y-m-d H:i:s');
        $tx_id = "TRX-" . time();
        $ket_final = "[$jenis_pinjaman] " . ($keterangan ?: "Peminjaman Apel/Rutin");

        // 2. Loop semua barang di keranjang dan eksekusi
        foreach ($cart_items as $item) {
            if ($item['tipe'] == 'senjata') {
                // Update status senjata
                foreach ($senjata_data as &$s) {
                    if ($s['nomor_seri'] == $item['id']) {
                        $s['status_lokasi'] = 'Dibawa Bertugas';
                        break;
                    }
                }
                // Catat ke log
                array_unshift($riwayat_data, [
                    "id_transaksi" => $tx_id, "waktu" => $waktu, "id_anggota" => $anggota['id_anggota'],
                    "jenis_transaksi" => "Keluar Gudang", "item" => "Senjata: " . $item['nama'], "keterangan" => $ket_final
                ]);
            } else if ($item['tipe'] == 'logistik') {
                // Potong stok logistik umum
                foreach ($logistik_data as &$l) {
                    if ($l['id_barang'] == $item['id']) {
                        $l['stok_tersedia'] -= (int)$item['qty'];
                        break;
                    }
                }
                // Catat ke log
                array_unshift($riwayat_data, [
                    "id_transaksi" => $tx_id, "waktu" => $waktu, "id_anggota" => $anggota['id_anggota'],
                    "jenis_transaksi" => "Keluar Gudang", "item" => $item['qty'] . "x " . $item['nama'], "keterangan" => $ket_final
                ]);
            }
        }

        // 3. Simpan semua perubahan
        $this->write('senjata.json', $senjata_data);
        $this->write('logistik_umum.json', $logistik_data);
        $this->write('riwayat.json', $riwayat_data);

        return "sukses";
    }
    
    // --- FUNGSI UPDATE & DELETE PERSONIL ---

    // 1. Update Anggota & Senjatanya
    public function updateAnggotaDanSenjata($id_anggota, $nama, $nrp, $satuan, $jenis_senjata, $nomor_seri) {
        $anggota_data = $this->read('anggota.json');
        $senjata_data = $this->read('senjata.json');
        
        // Update data diri anggota
        foreach ($anggota_data as &$a) {
            if ($a['id_anggota'] == $id_anggota) {
                $a['nama'] = strtoupper($nama);
                $a['nrp'] = $nrp;
                $a['satuan'] = $satuan;
                break;
            }
        }
        $this->write('anggota.json', $anggota_data);

        // Jika form senjata tidak kosong
        if (!empty($jenis_senjata) && !empty($nomor_seri)) {
            $senjata_ditemukan = false;
            
            // Coba update senjata yang sudah ada
            foreach ($senjata_data as &$s) {
                if ($s['id_pemegang_tetap'] == $id_anggota) {
                    $s['jenis_senjata'] = strtoupper($jenis_senjata);
                    $s['nomor_seri'] = strtoupper($nomor_seri);
                    $senjata_ditemukan = true;
                    break;
                }
            }
            
            // Jika sebelumnya belum punya senjata, tambahkan baru
            if (!$senjata_ditemukan) {
                $this->tambahSenjata($nomor_seri, $jenis_senjata, $id_anggota);
                return true; // fungsi tambahSenjata sudah melakukan proses write
            }
            
            $this->write('senjata.json', $senjata_data);
        }
        return true;
    }

    // 2. Hapus Anggota (beserta senjatanya jika ada)
    public function hapusAnggota($id_anggota) {
        $anggota_data = $this->read('anggota.json');
        $senjata_data = $this->read('senjata.json');
        
        // Filter buang anggota
        $anggota_data = array_filter($anggota_data, function($a) use ($id_anggota) {
            return $a['id_anggota'] !== $id_anggota;
        });
        
        // Filter buang senjata milik anggota tersebut
        $senjata_data = array_filter($senjata_data, function($s) use ($id_anggota) {
            return $s['id_pemegang_tetap'] !== $id_anggota;
        });
        
        $this->write('anggota.json', array_values($anggota_data));
        $this->write('senjata.json', array_values($senjata_data));
        return true;
    }
    
    // --- FUNGSI MAINTENANCE / BARANG RUSAK ---
    public function setStatusSenjata($nomor_seri, $status_baru) {
        $senjata_data = $this->read('senjata.json');
        foreach ($senjata_data as &$s) {
            if ($s['nomor_seri'] == $nomor_seri) {
                $s['status_lokasi'] = $status_baru;
                break;
            }
        }
        $this->write('senjata.json', $senjata_data);
        return true;
    }
    
    // --- FUNGSI BARANG RUSAK (LOGISTIK UMUM) ---
    public function updateStokRusak($id_barang, $jumlah, $aksi) {
        $logistik = $this->read('logistik_umum.json');
        
        foreach ($logistik as &$l) {
            if ($l['id_barang'] == $id_barang) {
                // Pastikan key stok_rusak ada (untuk data lama)
                if (!isset($l['stok_rusak'])) $l['stok_rusak'] = 0;
                
                $jumlah = (int)$jumlah;

                if ($aksi == 'tambah_rusak') {
                    // Pindah dari Tersedia -> Rusak
                    if ($l['stok_tersedia'] >= $jumlah) {
                        $l['stok_tersedia'] -= $jumlah;
                        $l['stok_rusak'] += $jumlah;
                    }
                } else if ($aksi == 'perbaiki') {
                    // Pindah dari Rusak -> Tersedia
                    if ($l['stok_rusak'] >= $jumlah) {
                        $l['stok_rusak'] -= $jumlah;
                        $l['stok_tersedia'] += $jumlah;
                    }
                }
                break;
            }
        }
        $this->write('logistik_umum.json', $logistik);
        return true;
    }
    
    // ====================================================
    // FUNGSI CRUD UNTUK LOGISTIK UMUM
    // ====================================================

    // 1. Tambah Logistik Baru
    public function tambahLogistikUmum($nama_barang, $kategori, $total_stok) {
        $logistik = $this->read('logistik_umum.json');
        
        // Bikin ID unik, misal: LOG-17120001
        $id_baru = "LOG-" . time() . rand(10, 99);
        
        $item_baru = [
            "id_barang" => $id_baru,
            "nama_barang" => strtoupper($nama_barang),
            "kategori" => $kategori,
            "total_stok" => (int)$total_stok,
            "stok_tersedia" => (int)$total_stok, // Awal tambah, tersedia = total
            "stok_rusak" => 0
        ];
        
        array_push($logistik, $item_baru);
        $this->write('logistik_umum.json', $logistik);
        return true;
    }

    // 2. Edit Logistik
    public function editLogistikUmum($id_barang, $nama_barang, $kategori, $total_stok) {
        $logistik = $this->read('logistik_umum.json');
        
        foreach ($logistik as &$l) {
            if ($l['id_barang'] == $id_barang) {
                $l['nama_barang'] = strtoupper($nama_barang);
                $l['kategori'] = $kategori;
                
                // Kalkulasi penyesuaian stok cerdas
                $stok_lama = (int)$l['total_stok'];
                $total_baru = (int)$total_stok;
                $selisih = $total_baru - $stok_lama;
                
                $l['total_stok'] = $total_baru;
                $l['stok_tersedia'] += $selisih; // Jika ditambah, tersedia naik. Jika dikurangi, turun.
                
                // Cegah minus jika admin salah ketik
                if ($l['stok_tersedia'] < 0) $l['stok_tersedia'] = 0;
                
                break;
            }
        }
        
        $this->write('logistik_umum.json', $logistik);
        return true;
    }

    // 3. Hapus Logistik
    public function hapusLogistikUmum($id_barang) {
        $logistik = $this->read('logistik_umum.json');
        
        // Buang data yang ID-nya cocok
        $logistik = array_filter($logistik, function($l) use ($id_barang) {
            return $l['id_barang'] !== $id_barang;
        });
        
        // Re-index array dan simpan
        $this->write('logistik_umum.json', array_values($logistik));
        return true;
    }
    
    public function getJenisPinjaman() {
        return $this->read('jenis_pinjaman.json');
    }

    public function tambahJenisPinjaman($nama) {
        $data = $this->getJenisPinjaman();
        $data[] = ["nama" => $nama];
        $this->write('jenis_pinjaman.json', $data);
        return true;
    }

    public function editJenisPinjaman($index, $nama_baru) {
        $data = $this->getJenisPinjaman();
        if (isset($data[$index])) {
            $data[$index]['nama'] = $nama_baru;
            $this->write('jenis_pinjaman.json', $data);
            return true;
        }
        return false;
    }

    public function hapusJenisPinjaman($index) {
        $data = $this->getJenisPinjaman();
        if (isset($data[$index])) {
            array_splice($data, $index, 1);
            $this->write('jenis_pinjaman.json', $data);
            return true;
        }
        return false;
    }
    
    // ====================================================
    // FUNGSI MANAJEMEN USER (users.json)
    // ====================================================

    public function getAllUsers() {
        return $this->read('users.json');
    }

    public function tambahUser($username, $password, $nama_petugas, $pangkat, $nrp, $role) {
        $users = $this->getAllUsers();
        // Cek jika username sudah ada
        foreach ($users as $u) {
            if ($u['username'] === $username) return "Username sudah digunakan!";
        }
        
        $users[] = [
            "username"     => $username,
            "password"     => $password, // Disarankan menggunakan password_hash di masa depan
            "nama_petugas" => strtoupper($nama_petugas),
            "pangkat"      => strtoupper($pangkat),
            "nrp"          => $nrp,
            "role"         => $role
        ];
        $this->write('users.json', $users);
        return true;
    }

    public function editUser($username, $password_baru, $nama_baru, $pangkat_baru, $nrp_baru, $role_baru) {
        $users = $this->getAllUsers();
        foreach ($users as &$u) {
            if ($u['username'] === $username) {
                // Update password hanya jika diisi
                if (!empty($password_baru)) $u['password'] = $password_baru;
                
                $u['nama_petugas'] = strtoupper($nama_baru);
                $u['pangkat']      = strtoupper($pangkat_baru);
                $u['nrp']          = $nrp_baru;
                $u['role']         = $role_baru;
                break;
            }
        }
        $this->write('users.json', $users);
        return true;
    }

    public function hapusUser($username) {
        $users = $this->getAllUsers();
        $users = array_filter($users, function($u) use ($username) {
            return $u['username'] !== $username;
        });
        $this->write('users.json', array_values($users));
        return true;
    }
}
?>