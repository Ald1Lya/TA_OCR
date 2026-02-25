<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Jakarta');

// 1. Keamanan: Cek apakah user sudah login dan benar-benar admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// 2. Keamanan: Pastikan request yang masuk adalah metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/manajemen_operator.php');
    exit;
}

$action = $_POST['action'] ?? null;


if ($action === 'tambah') {
    $username = trim($_POST['username']);
    $nama     = trim($_POST['nama_lengkap']);
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'operator';
    $status   = $_POST['status'] ?? 'Aktif';

 
    if (!$username || !$password) {
        header('Location: ../admin/manajemen_operator.php?error=Username dan password wajib diisi');
        exit;
    }


    $stmt = mysqli_prepare($db, "SELECT id FROM staf_kecamatan WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        header('Location: ../admin/manajemen_operator.php?error=Username sudah digunakan');
        exit;
    }
    mysqli_stmt_close($stmt);

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO staf_kecamatan 
            (username, password_hash, nama_lengkap, role, status, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "sssss", $username, $hashed, $nama, $role, $status);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: ../admin/manajemen_operator.php?success=Pengguna berhasil ditambahkan');
    } else {
        header('Location: ../admin/manajemen_operator.php?error=Gagal menambahkan pengguna');
    }

    mysqli_stmt_close($stmt);
    exit;
}




if ($action === 'edit') {
    $id       = $_POST['id'];
    $username = trim($_POST['username']);
    $nama     = trim($_POST['nama_lengkap']);
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'operator';
    $status   = $_POST['status'] ?? 'Aktif';

    if ($password) {
        // Jika password diisi, update beserta password baru
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE staf_kecamatan 
                SET username=?, nama_lengkap=?, password_hash=?, role=?, status=?
                WHERE id=?";
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, "sssssi", $username, $nama, $hashed, $role, $status, $id);
    } else {
        // Jika password kosong, update data selain password
        $sql = "UPDATE staf_kecamatan 
                SET username=?, nama_lengkap=?, role=?, status=?
                WHERE id=?";
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $username, $nama, $role, $status, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
         
            if ($id == $_SESSION['user_id']) {
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



if (isset($_POST['hapus_id'])) {
    $id = $_POST['hapus_id'];

    // Validasi: Jangan biarkan admin menghapus dirinya sendiri
    if ($id == $_SESSION['user_id']) {
        header('Location: ../admin/manajemen_operator.php?error=Tidak bisa menghapus akun Anda sendiri');
        exit;
    }

    $stmt = mysqli_prepare($db, "DELETE FROM staf_kecamatan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: ../admin/manajemen_operator.php?success=Pengguna berhasil dihapus');
    } else {
        header('Location: ../admin/manajemen_operator.php?error=Gagal menghapus pengguna');
    }

    mysqli_stmt_close($stmt);
    exit;
}

// Kembalikan ke halaman utama jika tidak ada perintah yang jelas
header('Location: ..admin/manajemen_operator.php');
exit;
?>