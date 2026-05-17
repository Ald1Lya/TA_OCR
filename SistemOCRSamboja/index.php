<?php
session_start();

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    if (strtolower($_SESSION['role']) === 'admin') {
        header('Location: admin/dashboard_admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

require_once 'proses/config.php';

// Kontrol tampilan tombol daftar
$tampilkan_tombol_daftar = true;

if ($db) {
    // Cek apakah admin sudah terdaftar
    $query = "SELECT id FROM staf_kecamatan WHERE role = 'admin' LIMIT 1";
    $result = mysqli_query($db, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $tampilkan_tombol_daftar = false;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Sistem OCR KTP</title>
  
  <link rel="stylesheet" href="../assets/css/style.css" />

  <style>
    /* Deklarasi Font Inter Regular (400) */
    @font-face {
        font-family: 'Inter';
        src: url('../assets/fonts/Inter-Regular.ttf') format('truetype');
        font-weight: 400;
        font-style: normal;
    }

    /* Deklarasi Font Inter SemiBold (600) */
    @font-face {
        font-family: 'Inter';
        src: url('../assets/fonts/Inter-SemiBold.ttf') format('truetype');
        font-weight: 600;
        font-style: normal;
    }

    /* Deklarasi Font Inter Bold (700) -> PERHATIKAN PATHNYA SESUAIKAN DENGAN FOLDER LU */
    @font-face {
        font-family: 'Inter';
        src: url('../assets/fonts/static/Inter-Bold.ttf') format('truetype');
        font-weight: 700;
        font-style: normal;
    }

    /* Terapkan ke body */
    body { 
        font-family: 'Inter', sans-serif; 
    }

    /* Animasi Bawaan Login */
    @keyframes fade-in {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fade-in 0.5s ease-out; }
    
    a.link-disabled {
      color: #9ca3af; 
      pointer-events: none;
      cursor: not-allowed;
      text-decoration: none;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-600 to-green-500 p-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 animate-fade-in">
    <div class="text-center mb-8">
      <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center bg-green-600">
      <img src="../assetimage/logo.png" class="w-18 h-18 object-contain object-center" alt="Logo">

      </div>
      <h1 class="text-2xl font-bold text-gray-800 mb-1">Sistem OCR KTP Digital</h1>
      <p class="text-sm text-gray-500">Kecamatan Samboja Kuala</p>
    </div>

    <?php
    // Tampilkan pesan error jika ada
    if (isset($_GET['error'])) {
        echo '<p class="text-red-500 text-sm text-center mb-4 font-medium">Username, password, atau role salah!</p>';
    }
    // Tampilkan pesan sukses registrasi jika ada
    if (isset($_GET['register_success'])) {
        echo '<p class="text-green-600 text-sm text-center mb-4 font-medium">Akun admin berhasil dibuat! Silakan login.</p>';
    }
    ?>

    <form action="proses/proses_login_dan_register.php" method="POST" class="space-y-6">
      <input type="hidden" name="action" value="login">
      
     <div>
        <label class="block text-gray-700 font-medium mb-2 text-sm">Username</label>
        <input type="text" name="username" required
          class="w-full px-4 py-3 border border-gray-200 shadow-sm rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:outline-none text-gray-700 transition-all"
          placeholder="Masukkan username">
      </div>

      <div>
        <label class="block text-gray-700 font-medium mb-2 text-sm">Password</label>
        <input type="password" name="password" required
          class="w-full px-4 py-3 border border-gray-200 shadow-sm rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:outline-none text-gray-700 transition-all"
          placeholder="Masukkan password">
      </div>

      <div>
        <label class="block text-gray-700 font-medium mb-2 text-sm">Masuk Sebagai</label>
        <select name="role" id="roleSelect" required
          class="w-full px-4 py-3 border border-gray-200 shadow-sm rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:outline-none text-gray-700 bg-white transition-all">
          <option value="admin">Admin</option>
          <option value="operator">Operator</option>
        </select>
      </div>

      <div class="flex items-center justify-between">
        <label class="flex items-center space-x-2">
          <input type="checkbox" class="w-4 h-4 text-green-600 focus:ring-green-500 rounded">
          <span class="text-sm text-gray-600">Ingat saya</span>
        </label>
        
        <?php if ($tampilkan_tombol_daftar): ?>
            <a href="register.php" id="registerLink" class="text-sm text-green-600 hover:underline">Buat Akun Admin?</a>
        <?php endif; ?>
        
      </div>

      <button type="submit"
        class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition-all">
        Masuk ke Sistem
      </button>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
      <p class="text-xs text-gray-500">Silahkan Login Ke Sistem</p>
    </div>
  </div>

  <script>
    const roleSelect = document.getElementById('roleSelect');
    // Kita ambil elemen registerLink, TAPI bisa jadi null jika dihapus PHP
    const registerLink = document.getElementById('registerLink');

    function updateLinkStatus() {
      // PENTING: Cek dulu apakah tombolnya ada?
      if (!registerLink) return; 

      const selectedRole = roleSelect.value;

      if (selectedRole === 'operator') {
        registerLink.classList.add('link-disabled');
        registerLink.classList.remove('text-green-600', 'hover:underline');
        registerLink.removeAttribute('href');
      } else {
        registerLink.classList.remove('link-disabled');
        registerLink.classList.add('text-green-600', 'hover:underline');
        registerLink.setAttribute('href', 'register.php');
      }
    }

    roleSelect.addEventListener('change', updateLinkStatus);
    document.addEventListener('DOMContentLoaded', updateLinkStatus);
  </script>
</body>
</html>