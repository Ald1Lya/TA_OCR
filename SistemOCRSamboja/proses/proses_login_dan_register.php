<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ==========================================
    // PROSES REGISTER (Khusus Admin Pertama)
    // ==========================================
    if ($action === 'register') {
        $username      = trim($_POST['username']);
        $password      = $_POST['password'];
        $nama_lengkap  = trim($_POST['nama_lengkap']);
        $role          = 'admin';
        $status        = 'Aktif'; // Default aktif

        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            header('Location: ../register.php?error=Semua field tidak boleh kosong');
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql_check = "SELECT id FROM staf_kecamatan WHERE username = ?";
        $stmt = mysqli_prepare($db, $sql_check);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            header('Location: ../register.php?error=Username sudah digunakan');
            exit;
        }
        mysqli_stmt_close($stmt);

        $sql_insert = "INSERT INTO staf_kecamatan (username, password_hash, nama_lengkap, role, status) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($db, $sql_insert);

        if (!$stmt_insert) {
            header('Location: ../register.php?error=Gagal menyiapkan query');
            exit;
        }

        mysqli_stmt_bind_param($stmt_insert, "sssss", $username, $hashed_password, $nama_lengkap, $role, $status);

        if (mysqli_stmt_execute($stmt_insert)) {
            header('Location: ../index.php?register_success=1');
        } else {
            header('Location: ../register.php?error=' . mysqli_error($db));
        }
        mysqli_stmt_close($stmt_insert);
        exit;
    }

    // ==========================================
    // PROSES LOGIN
    // ==========================================
    if ($action === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role     = trim($_POST['role']);

        // Cari user berdasarkan username dan rolenya
        $sql_login = "SELECT * FROM staf_kecamatan WHERE username = ? AND role = ?";
        $stmt = mysqli_prepare($db, $sql_login);

        if (!$stmt) {
            header('Location: ../index.php?error=QueryError');
            exit;
        }

        mysqli_stmt_bind_param($stmt, "ss", $username, $role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            // Verifikasi Password
            if (password_verify($password, $user['password_hash'])) {
                
                // BUG FIX: CEK APAKAH AKUN NONAKTIF?
                if (strtolower(trim($user['status'])) !== 'aktif') {
                    header('Location: ../index.php?error=Akun Anda telah dinonaktifkan!');
                    exit;
                }

                // Jika lolos semua, set Session
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role']         = $user['role'];

                // Update Waktu Terakhir Login
                $now = date('Y-m-d H:i:s');
                $sql_update = "UPDATE staf_kecamatan SET last_login = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($db, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "si", $now, $user['id']);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);

                // LOGIKA PENGALIHAN BERDASARKAN ROLE
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard_admin.php');
                } else {
                    header('Location: ../dashboard.php');
                }
                exit;
            }
        }

        mysqli_stmt_close($stmt);
        // Jika username/password salah, atau role salah pilih di dropdown
        header('Location: ../index.php?error=1');
        exit;
    }
}

mysqli_close($db);
header('Location: ../index.php');
exit;
?>