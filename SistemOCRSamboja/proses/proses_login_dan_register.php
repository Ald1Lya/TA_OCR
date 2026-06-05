<?php
session_start();
require_once 'config.php';
require_once 'csrf.php';

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../index.php');
    exit;
}

$action = $_POST['action'];

// Proses registrasi admin pertama
if ($action === 'register') {
    csrf_verify();

    $username     = trim(is_string($_POST['username'] ?? '') ? $_POST['username'] : '');
    $password     = is_string($_POST['password'] ?? '') ? $_POST['password'] : '';
    $nama_lengkap = trim(is_string($_POST['nama_lengkap'] ?? '') ? $_POST['nama_lengkap'] : '');
    $role         = 'admin';
    $status       = 'Aktif';

    if ($username === '' || $password === '' || $nama_lengkap === '') {
        header('Location: ../register.php?error=Semua field tidak boleh kosong');
        exit;
    }

    // Pastikan belum ada admin sebelumnya
    $stmt = mysqli_prepare($db, "SELECT id FROM staf_kecamatan WHERE role = 'admin' LIMIT 1");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        header('Location: ../index.php');
        exit;
    }
    mysqli_stmt_close($stmt);

    // Cek duplikat username
    $stmt = mysqli_prepare($db, "SELECT id FROM staf_kecamatan WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        header('Location: ../register.php?error=Username sudah digunakan');
        exit;
    }
    mysqli_stmt_close($stmt);

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($db, "INSERT INTO staf_kecamatan (username, password_hash, nama_lengkap, role, status) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sssss', $username, $hashed, $nama_lengkap, $role, $status);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: ../index.php?register_success=1');
    } else {
        header('Location: ../register.php?error=Gagal membuat akun');
    }
    mysqli_stmt_close($stmt);
    exit;
}

// Proses login
if ($action === 'login') {
    csrf_verify();

    $username = trim(is_string($_POST['username'] ?? '') ? $_POST['username'] : '');
    $password = is_string($_POST['password'] ?? '') ? $_POST['password'] : '';
    $role     = trim(is_string($_POST['role'] ?? '') ? $_POST['role'] : '');

    $stmt = mysqli_prepare($db, "SELECT * FROM staf_kecamatan WHERE username = ? AND role = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $username, $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password_hash'])) {

            if (strtolower(trim($user['status'])) !== 'aktif') {
                header('Location: ../index.php?error=Akun Anda telah dinonaktifkan');
                exit;
            }

            // Regenerasi session ID untuk mencegah session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];

            $now  = date('Y-m-d H:i:s');
            $stmt = mysqli_prepare($db, "UPDATE staf_kecamatan SET last_login = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $now, $user['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($user['role'] === 'admin') {
                header('Location: ../admin/dashboard_admin.php');
            } else {
                header('Location: ../dashboard.php');
            }
            exit;
        }
    }

    mysqli_stmt_close($stmt);
    header('Location: ../index.php?error=1');
    exit;
}

mysqli_close($db);
header('Location: ../index.php');
exit;
?>
