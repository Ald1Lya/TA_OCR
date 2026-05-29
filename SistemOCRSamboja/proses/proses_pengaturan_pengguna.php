<?php
session_start();
require_once 'config.php';
require_once 'csrf.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/manajemen_operator.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';

// Tambah pengguna baru
if ($action === 'tambah') {
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'operator';
    $status   = $_POST['status'] ?? 'Aktif';

    if ($username === '' || $password === '') {
        header('Location: ../admin/manajemen_operator.php?error=Username dan password wajib diisi');
        exit;
    }

    $stmt = mysqli_prepare($db, "SELECT id FROM staf_kecamatan WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        header('Location: ../admin/manajemen_operator.php?error=Username sudah digunakan');
        exit;
    }
    mysqli_stmt_close($stmt);

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($db, "INSERT INTO staf_kecamatan (username, password_hash, nama_lengkap, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'sssss', $username, $hash, $nama, $role, $status);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: ../admin/manajemen_operator.php?success=Pengguna berhasil ditambahkan');
    } else {
        header('Location: ../admin/manajemen_operator.php?error=Gagal menambahkan pengguna');
    }

    mysqli_stmt_close($stmt);
    exit;
}

// Edit data pengguna
if ($action === 'edit') {
    $id       = (int) ($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'operator';
    $status   = $_POST['status'] ?? 'Aktif';

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($db, "UPDATE staf_kecamatan SET username=?, nama_lengkap=?, password_hash=?, role=?, status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssssi', $username, $nama, $hash, $role, $status, $id);
    } else {
        $stmt = mysqli_prepare($db, "UPDATE staf_kecamatan SET username=?, nama_lengkap=?, role=?, status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssssi', $username, $nama, $role, $status, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        // Sinkronkan session jika admin mengedit akunnya sendiri
        if ($id === (int) $_SESSION['user_id']) {
            $_SESSION['username']     = $username;
            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['role']         = $role;
        }
        header('Location: ../admin/manajemen_operator.php?success=Pengguna berhasil diperbarui');
    } else {
        header('Location: ../admin/manajemen_operator.php?error=Gagal memperbarui pengguna');
    }

    mysqli_stmt_close($stmt);
    exit;
}

// Hapus pengguna
if (isset($_POST['hapus_id'])) {
    $id = (int) $_POST['hapus_id'];

    if ($id === (int) $_SESSION['user_id']) {
        header('Location: ../admin/manajemen_operator.php?error=Tidak bisa menghapus akun sendiri');
        exit;
    }

    $stmt = mysqli_prepare($db, "DELETE FROM staf_kecamatan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: ../admin/manajemen_operator.php?success=Pengguna berhasil dihapus');
    } else {
        header('Location: ../admin/manajemen_operator.php?error=Gagal menghapus pengguna');
    }

    mysqli_stmt_close($stmt);
    exit;
}

header('Location: ../admin/manajemen_operator.php');
exit;
?>
