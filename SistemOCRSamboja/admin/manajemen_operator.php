<?php
session_start();

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once '../proses/config.php'; 
require_once '../proses/csrf.php';

$current_user = [
    'id'           => $_SESSION['user_id'],
    'username'     => $_SESSION['username'],
    'nama_lengkap' => $_SESSION['nama_lengkap'],
    'role'         => strtolower($_SESSION['role'])
];


$highlight_id = isset($_GET['highlight_id']) ? (int)$_GET['highlight_id'] : null;

$perPage = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $perPage;

$sql_count = "SELECT COUNT(*) as total FROM staf_kecamatan";
$res_count = mysqli_query($db, $sql_count);
$total_rows = mysqli_fetch_assoc($res_count)['total'];
$totalPages = ceil($total_rows / $perPage);

$users = [];
$sql = "SELECT id, username, nama_lengkap, role, status FROM staf_kecamatan ORDER BY id ASC LIMIT $start, $perPage";
$result = mysqli_query($db, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

function getInitials($name) {
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) {
        if(!empty($w)) $initials .= strtoupper($w[0]);
    }
    return substr($initials, 0, 2);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="../../assetimage/logo.png" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Operator - OCR KTP</title>
    <link rel="stylesheet" href="../../assets/css/style.css" />
    <script src="../../assets/js/chart.min.js"></script>

    <style> 
        @keyframes highlightFade {
            0% { background-color: #dcfce7; } /* green-100 */
            100% { background-color: transparent; }
        }
        .row-highlight {
            animation: highlightFade 3s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-800 antialiased">

<?php include 'includes/navbar_admin.php'; ?>

<main class="flex-1 ml-64 p-8 transition-all duration-300 relative">
  
  <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Manajemen Operator & Staf</h1>
      <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
        <i data-feather="info" class="w-4 h-4 text-green-600"></i>
        Kelola akses sistem secara interaktif.
      </p>
    </div>

    <div>
      <button id="addUserBtn" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold text-sm px-6 py-3 rounded-lg shadow-md transform transition-all hover:-translate-y-0.5 border border-green-700 active:scale-95">
        <i data-feather="user-plus" class="h-5 w-5"></i>
        Tambah Pengguna Baru
      </button>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 shadow-md overflow-hidden transition-all">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-green-50 border-b border-green-200 text-sm uppercase tracking-wider text-green-800 font-bold">
            <th class="px-6 py-4">Profil Pengguna</th>
            <th class="px-6 py-4">Peran (Role)</th>
            <th class="px-6 py-4 text-center">Status</th>
            <th class="px-6 py-4 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if(empty($users)): ?>
            <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500 italic">Belum ada data pengguna.</td></tr>
          <?php endif; ?>

          <?php foreach ($users as $u): ?>
          <?php 
            $is_current = $u['id'] == $current_user['id']; 
            $is_highlighted = $u['id'] == $highlight_id;
          ?>
          <tr class="transition duration-150 <?= $is_highlighted ? 'row-highlight' : 'hover:bg-green-50/30' ?> <?= $is_current && !$is_highlighted ? 'bg-yellow-50/50' : '' ?>">
            <td class="px-6 py-4">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full flex items-center justify-center font-bold text-lg bg-green-100 text-green-700 border-2 border-green-200 shadow-sm uppercase">
                   <?= getInitials($u['nama_lengkap']) ?>
                </div>
                <div>
                  <div class="font-bold text-gray-900 text-base flex items-center gap-2">
                    <?= htmlspecialchars($u['nama_lengkap']) ?>
                    <?php if($is_current): ?>
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-yellow-200 text-yellow-800 border border-yellow-300">ANDA</span>
                    <?php endif; ?>
                  </div>
                  <div class="text-sm font-medium text-gray-500">@<?= htmlspecialchars($u['username']) ?></div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 align-middle">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold uppercase <?= strtolower($u['role']) === 'admin' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-gray-100 text-gray-700 border border-gray-300' ?>">
                  <i data-feather="<?= strtolower($u['role']) === 'admin' ? 'shield' : 'monitor' ?>" class="w-3.5 h-3.5"></i> <?= $u['role'] ?>
                </span>
            </td>
            <td class="px-6 py-4 text-center align-middle">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold <?= strtolower($u['status']) === 'aktif' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                  <?= $u['status'] ?>
                </span>
            </td>
            <td class="px-6 py-4 text-center align-middle">
              <div class="flex items-center justify-center gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 hover:text-blue-700 hover:border-blue-500 rounded-lg shadow-sm font-medium text-sm transition-all editBtn active:scale-90"
                    data-id="<?= $u['id'] ?>" 
                    data-username="<?= htmlspecialchars($u['username']) ?>" 
                    data-nama="<?= htmlspecialchars($u['nama_lengkap']) ?>" 
                    data-role="<?= $u['role'] ?>" 
                    data-status="<?= $u['status'] ?>">
                  <i data-feather="edit" class="w-4 h-4"></i> Edit
                </button>
                <?php if(!$is_current): ?>
                <form action="../proses/proses_pengaturan_pengguna.php" method="POST" class="inline m-0 form-hapus">
                  <?= csrf_field() ?>
                  <input type="hidden" name="hapus_id" value="<?= $u['id'] ?>">
                  <button type="button" class="btn-delete inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 hover:text-red-700 hover:border-red-500 rounded-lg shadow-sm font-medium text-sm transition-all active:scale-90">
                    <i data-feather="trash-2" class="w-4 h-4"></i> Hapus
                  </button>
                </form>
                <?php else: ?>
                  <div class="w-[88px]"></div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="bg-gray-50 border-t border-gray-200 px-6 py-4 flex items-center justify-between">
       <span class="text-sm text-gray-600 font-medium italic">
          Total <?= $total_rows ?> pengguna sistem
       </span>
       <div class="flex gap-2">
          <a href="?page=<?= max(1, $page - 1) ?>" class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-sm font-bold text-gray-700 hover:bg-gray-100 <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">Prev</a>
          <a href="?page=<?= min($totalPages, $page + 1) ?>" class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-sm font-bold text-gray-700 hover:bg-gray-100 <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">Next</a>
       </div>
    </div>
  </div>
</main>

<div id="userModal" class="hidden fixed inset-0 z-[9999] justify-center items-center p-4 transition-opacity duration-300 opacity-0" style="background-color: rgba(0,0,0,0.3); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-200 transform transition-transform duration-300 scale-90">
    <div class="bg-green-600 px-6 py-4 border-b border-green-700">
      <h3 class="text-lg font-bold text-white flex items-center gap-2" id="modalTitle">
        <i data-feather="user" class="text-white"></i> Tambah Pengguna
      </h3>
    </div>
    <form action="../proses/proses_pengaturan_pengguna.php" method="POST">
      <div class="px-6 py-5 space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="formAction" value="tambah">
        <input type="hidden" name="id" id="userId">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
              <label class="block text-sm font-bold text-gray-700 mb-1.5">Nama Lengkap</label>
              <input type="text" name="nama_lengkap" id="namaInput" class="block w-full px-4 py-2.5 border border-gray-200 shadow-sm rounded-lg focus:border-green-600 outline-none transition-all" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1.5">Username</label>
              <input type="text" name="username" id="usernameInput" class="block w-full px-4 py-2.5 border border-gray-200 shadow-sm rounded-lg focus:border-green-600 outline-none transition-all" required>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1.5" id="passwordLabel">Password</label>
              <input type="password" name="password" id="passwordInput" class="block w-full px-4 py-2.5 border border-gray-200 shadow-sm rounded-lg focus:border-green-600 outline-none transition-all">
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1.5">Role</label>
              <select name="role" id="roleSelect" class="block w-full px-3 py-2.5 border border-gray-200 shadow-sm rounded-lg focus:border-green-600 outline-none bg-white font-medium transition-all">
                <option value="operator">Operator</option>
                <option value="admin">Administrator</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1.5">Status</label>
              <select name="status" id="statusSelect" class="block w-full px-3 py-2.5 border border-gray-200 shadow-sm rounded-lg focus:border-green-600 outline-none bg-white font-medium transition-all">
                <option value="Aktif">Aktif</option>
                <option value="Nonaktif">Nonaktif</option>
              </select>
            </div>
        </div>
      </div>
      <div class="bg-gray-100 px-6 py-4 flex items-center justify-end gap-3 border-t border-gray-200">
        <button type="button" id="closeModal" class="px-5 py-2.5 text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors shadow-sm">Batal</button>
        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-green-600 rounded-lg hover:bg-green-700 transition-all shadow-md">
          <i data-feather="save" class="w-4 h-4"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../../assets/js/feather.min.js"></script>
<script src="../../assets/js/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    feather.replace();

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        Toast.fire({ icon: 'success', title: urlParams.get('success') });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    if (urlParams.has('error')) {
        Toast.fire({ icon: 'error', title: urlParams.get('error') });
        window.history.replaceState({}, document.title, window.location.pathname);
    }


    const modal = document.getElementById('userModal');
    const modalContent = modal.querySelector('div');

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modalContent.classList.remove('scale-90');
        }, 10);
    }

    function hideModal() {
        modal.classList.add('opacity-0');
        modalContent.classList.add('scale-90');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    document.getElementById('addUserBtn').onclick = () => {
        document.getElementById('modalTitle').innerHTML = '<i data-feather="user-plus"></i> Tambah Pengguna';
        document.getElementById('formAction').value = "tambah";
        document.getElementById('userId').value = "";
        document.getElementById('namaInput').value = "";
        document.getElementById('usernameInput').value = "";
        document.getElementById('passwordInput').required = true;
        document.getElementById('passwordLabel').innerHTML = 'Password <span class="text-red-500">*</span>';
        openModal();
        feather.replace();
    };

    document.getElementById('closeModal').onclick = hideModal;

    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.onclick = () => {
            document.getElementById('modalTitle').innerHTML = '<i data-feather="edit"></i> Edit Pengguna';
            document.getElementById('formAction').value = "edit";
            document.getElementById('userId').value = btn.dataset.id;
            document.getElementById('namaInput').value = btn.dataset.nama;
            document.getElementById('usernameInput').value = btn.dataset.username;
            document.getElementById('passwordInput').required = false;
            document.getElementById('passwordLabel').innerHTML = 'Password <span class="text-gray-400 text-xs">(Biarkan kosong jika tidak diganti)</span>';
            document.getElementById('roleSelect').value = btn.dataset.role;
            document.getElementById('statusSelect').value = btn.dataset.status;
            openModal();
            feather.replace();
        };
    });

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.onclick = function() {
            const form = this.closest('form');
            Swal.fire({
                title: 'Hapus Pengguna?',
                text: "Data yang dihapus tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#dc2626',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        };
    });
});
</script>
</body>
</html>