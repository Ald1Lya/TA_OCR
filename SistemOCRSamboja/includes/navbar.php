<?php
  $current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="w-64 bg-white shadow-lg flex flex-col fixed h-full">

  <div class="p-6 border-b border-gray-200">
    <div class="flex items-center gap-3 mb-2">
      <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-green-600">
       <img src="../assetimage/logo.png" class="w-18 h-18 object-contain object-center" alt="Logo">
      </div>
      <div>
        <h2 class="font-bold text-gray-800">OCR KTP</h2>
        <p class="text-xs text-gray-500">Kecamatan Samboja Kuala</p>
      </div>
    </div>
  </div>


  <nav class="flex-1 p-4 space-y-2">
    <!-- Dashboard -->
    <a href="dashboard.php"
       class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition font-medium
       <?php echo $current_page == 'dashboard.php' ? 'bg-green-100 text-green-600 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7m-9 13V9m0 0l-2 2m2-2l2 2" />
      </svg>
      Dashboard
    </a>

 
    <a href="upload.php"
       class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition font-medium
       <?php echo $current_page == 'upload.php' ? 'bg-green-100 text-green-600 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6a5 5 0 011 9.9" />
      </svg>
      Upload KTP
    </a>

   
    <a href="riwayat.php"
       class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition font-medium
       <?php echo $current_page == 'riwayat.php' ? 'bg-green-100 text-green-600 font-semibold' : 'text-gray-700 hover:bg-gray-100'; ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 6h16M4 10h16M4 14h16M4 18h16" />
      </svg>
      Riwayat
    </a>

  </nav>

  <!-- Logout -->
  <div class="p-4 border-t border-gray-200">
    <a href="proses/proses_logout.php"
       class="w-full flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-lg transition font-medium">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
      </svg>
      Keluar
    </a>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <script>feather.replace();</script>
</aside>
