<?php

session_start();

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: home.php");
    exit;
}

$error_msg = "";

// 3. PROSES SAAT TOMBOL LOGIN DITEKAN (Metode POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    
    // Ambil data yang diketik di form
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    // Buka file database user
    $file_users = 'data/users.json';
    
    // Pastikan file users.json ada
    if (file_exists($file_users)) {
        // Baca isinya dan ubah jadi array PHP
        $users_data = json_decode(file_get_contents($file_users), true);
        
        $login_berhasil = false;

        // Loop (Cari) kecocokan di dalam array
        foreach ($users_data as $user) {
            // Cek apakah username dan password cocok persis
            if ($user['username'] === $input_username && $user['password'] === $input_password) {
                
                // JIKA COCOK: Buat "Kartu Pengenal" (Session)
                $_SESSION['login'] = true;
                $_SESSION['nama_petugas'] = $user['nama_petugas'];
                $_SESSION['pangkat'] = $user['pangkat'];
                
                // INI YANG PALING PENTING UNTUK HAK AKSES:
                $_SESSION['role'] = $user['role']; // 'admin' atau 'user'
                
                $login_berhasil = true;
                break; // Berhenti mencari karena sudah ketemu
            }
        }

        // Arahkan sesuai hasil
        if ($login_berhasil) {
            header("Location: home.php");
            exit;
        } else {
            $error_msg = "Username atau Password salah!";
        }
        
    } else {
        $error_msg = "Sistem Error: Database user tidak ditemukan.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect" />
  <link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B600%3B700&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" onload="this.rel='stylesheet'" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <style type="text/tailwindcss">
    :root {
      --primary-50: #eef7ff;
      --primary-100: #d9edff;
      --primary-200: #bce1ff;
      --primary-300: #8ed0ff;
      --primary-400: #5ab8ff;
      --primary-500: #309eff;
      --primary-600: #0d7ff2;
      --primary-700: #0269d3;
      --primary-800: #0355ad;
      --primary-900: #06478a;
      --primary-950: #0b2d54;
    }
  </style>
  <title>Admin Panel - Sign In</title>
  <link href="data:image/x-icon;base64," rel="icon" type="image/x-icon" />
</head>
<body class="bg-gray-50 dark:bg-gray-900">
  <div class="flex min-h-screen flex-col items-center justify-center px-6 py-8" style='font-family: Inter, "Noto Sans", sans-serif;'>
    <a class="mb-6 flex items-center gap-3 text-2xl font-semibold text-gray-900 dark:text-white" href="#">
      <div class="flex h-8 w-8 items-center justify-center rounded-md bg-[var(--primary-600)] text-white">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
          <path d="M42.4379 44C42.4379 44 36.0744 33.9038 41.1692 24C46.8624 12.9336 42.2078 4 42.2078 4L7.01134 4C7.01134 4 11.6577 12.932 5.96912 23.9969C0.876273 33.9029 7.27094 44 7.27094 44L42.4379 44Z" fill="currentColor"></path>
        </svg>
      </div>
      <span class="font-bold">Admin Panel</span>
    </a>
    <div class="w-full rounded-lg bg-white shadow-md dark:border dark:border-gray-700 dark:bg-gray-800 sm:max-w-md md:mt-0 xl:p-0">
      <div class="space-y-4 p-6 sm:p-8 md:space-y-6">
        <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 dark:text-white md:text-2xl">Sign in to your account</h1>
        
        <?php if(!empty($error_msg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline text-sm"><?= $error_msg ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4 md:space-y-6">
          <input type="hidden" name="login_submit" value="1">
          
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white" for="username">Username</label>
            <input class="form-input block w-full rounded-md border border-gray-300 bg-gray-50 p-2.5 text-gray-900 focus:border-[var(--primary-600)] focus:ring-[var(--primary-600)] dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500 sm:text-sm" id="username" name="username" placeholder="Username" required="" type="text" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white" for="password">Password</label>
            <input class="form-input block w-full rounded-md border border-gray-300 bg-gray-50 p-2.5 text-gray-900 focus:border-[var(--primary-600)] focus:ring-[var(--primary-600)] dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500 sm:text-sm" id="password" name="password" placeholder="••••••••" required="" type="password" />
          </div>
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start">
              <div class="flex h-5 items-center">
                <input aria-describedby="remember" class="form-checkbox h-4 w-4 rounded border border-gray-300 bg-gray-50 text-[var(--primary-600)] focus:ring-3 focus:ring-[var(--primary-300)] dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-[var(--primary-600)]" id="remember" type="checkbox" />
              </div>
              <div class="ml-3 text-sm">
                <label class="text-gray-500 dark:text-gray-300" for="remember">Remember me</label>
              </div>
            </div>
          </div>
          <button class="w-full rounded-md bg-[var(--primary-600)] px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-[var(--primary-700)] focus:outline-none focus:ring-4 focus:ring-[var(--primary-300)] dark:bg-[var(--primary-600)] dark:hover:bg-[var(--primary-700)] dark:focus:ring-[var(--primary-800)]" type="submit">
            Sign in
          </button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>