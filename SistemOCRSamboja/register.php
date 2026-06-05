<?php
session_start();
require_once 'proses/csrf.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="../assetimage/logo.png" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registrasi Admin - Sistem OCR KTP</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
    <script src="../assets/js/chart.min.js"></script>
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

    /* Deklarasi Font Inter Bold (700) */
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
</style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-600 to-green-500 p-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 animate-fade-in">
    <div class="text-center mb-8">
      <h1 class="text-2xl font-bold text-gray-800 mb-1">Registrasi Akun Admin</h1>
      <p class="text-sm text-gray-500">Buat akun admin baru untuk sistem</p>
    </div>

    <?php
    // Tampilkan pesan error jika ada
    if (isset($_GET['error'])) {
        echo '<p class="text-red-500 text-sm text-center mb-4 font-medium">' . htmlspecialchars($_GET['error']) . '</p>';
    }
    ?>

    <form action="proses/proses_login_dan_register.php" method="POST" class="space-y-6">
      <input type="hidden" name="action" value="register">
      <?php echo csrf_field(); ?>
      
      <div>
        <label class="block text-gray-700 font-medium mb-2 text-sm">Username Admin Baru</label>
        <input type="text" name="username" required
          class="w-full px-4 py-3 border-2 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none text-gray-700"
          placeholder="Masukkan username admin">
      </div>


      <div>
        <label class="block text-gray-700 font-medium mb-2 text-sm">Nama Lengkaps</label>
        <input type="text" name="nama_lengkap" required
          class="w-full px-4 py-3 border-2 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none text-gray-700"
          placeholder="Masukkan username admin">
      </div>

      <div>
        <label class="block text-gray-700 font-medium mb-2 text-sm">Password</label>
        <input type="password" name="password" required
          class="w-full px-4 py-3 border-2 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none text-gray-700"
          placeholder="Masukkan password">
      </div>

      <button type="submit"
        class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition-all">
        Daftarkan Akun Admin
      </button>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
        <a href="index.php" class="text-sm text-gray-500 hover:text-green-600 hover:underline">Kembali ke Halaman Login</a>
    </div>
  </div>

  <style>
    @keyframes fade-in {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fade-in 0.5s ease-out; }
  </style>
</body>
</html>