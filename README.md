# Sistem OCR KTP Digital — Kecamatan Samboja Kuala

Sistem berbasis web untuk mengekstrak Nomor Induk Kependudukan (NIK) dari foto KTP secara otomatis menggunakan teknologi OCR (Optical Character Recognition). Dibangun untuk dijalankan di server lokal (offline) pada satu lokasi.

---

## Daftar Isi

- [Arsitektur Sistem](#arsitektur-sistem)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Struktur Direktori](#struktur-direktori)
- [Instalasi](#instalasi)
- [Konfigurasi Database](#konfigurasi-database)
- [Menjalankan Sistem](#menjalankan-sistem)
- [Alur Penggunaan](#alur-penggunaan)
- [Peran Pengguna](#peran-pengguna)
- [Keamanan](#keamanan)
- [Troubleshooting](#troubleshooting)

---

## Arsitektur Sistem

Sistem terdiri dari dua layer yang berjalan bersamaan di satu mesin:

```
Browser (Operator/Admin)
        │
        ▼
┌───────────────────┐
│   PHP + Laragon   │  ← Web server (Apache + MySQL)
│   (Port 80)       │
└────────┬──────────┘
         │ HTTP cURL (localhost)
         ▼
┌───────────────────┐
│  Python Flask     │  ← Mesin OCR (EasyOCR + OpenCV)
│  (Port 5000)      │
└───────────────────┘
```

**Alur data OCR:**
1. Operator upload foto KTP → PHP simpan ke `public/images/`
2. PHP kirim gambar ke Flask via cURL (`POST /ocr`)
3. Flask jalankan pipeline: optimize → preprocess → EasyOCR → filter NIK
4. Flask kembalikan JSON `{nik, score, raw_text}`
5. PHP simpan hasil ke database, operator review dan finalisasi

---

## Teknologi yang Digunakan

| Layer | Teknologi |
|---|---|
| Web Server | Laragon (Apache 2.4 + PHP 8.x) |
| Database | MySQL 8.x via MySQLi |
| Frontend | Tailwind CSS v4, Chart.js, Cropper.js, Feather Icons, SweetAlert2 |
| PDF Export | FPDF (via Composer) |
| OCR Engine | Python 3.12, Flask, EasyOCR, OpenCV, PyTorch |
| GPU (opsional) | CUDA (jika tersedia, digunakan otomatis oleh PyTorch) |

---

## Persyaratan Sistem

- **OS:** Windows 10/11
- **Laragon:** versi 6.x ke atas (Apache + PHP 8.x + MySQL)
- **Python:** 3.10 – 3.12
- **RAM:** minimal 8 GB (16 GB direkomendasikan jika menggunakan GPU)
- **Disk:** minimal 5 GB (untuk model EasyOCR)

---

## Struktur Direktori

```
TugasAkhirCapstone/
├── assets/
│   ├── css/
│   │   └── style.css              # Output Tailwind CSS (sudah dikompilasi)
│   └── js/
│       ├── chart.min.js
│       ├── cropper.min.js
│       ├── feather.min.js
│       └── sweetalert2.all.min.js
├── assetimage/
│   └── logo.png
├── vendor/                        # Dependensi PHP (Composer)
├── composer.json
├── package.json
│
└── SistemOCRSamboja/
    ├── index.php                  # Halaman login
    ├── register.php               # Registrasi admin pertama
    ├── dashboard.php              # Dashboard operator
    ├── upload.php                 # Upload & crop KTP
    ├── prosesocr.php              # Halaman progress OCR
    ├── hasilocr.php               # Hasil OCR setelah upload
    ├── hasilriwayat.php           # Detail hasil dari riwayat
    ├── koreksi.php                # Form koreksi NIK manual
    ├── riwayat.php                # Riwayat scan operator
    │
    ├── admin/
    │   ├── dashboard_admin.php
    │   ├── manajemen_operator.php
    │   ├── riwayat_keseluruhan.php
    │   ├── kontrol_sistem.php     # Start/stop server OCR
    │   └── includes/
    │       └── navbar_admin.php
    │
    ├── includes/
    │   └── navbar.php
    │
    ├── proses/                    # Backend handler (tidak diakses langsung)
    │   ├── config.php             # Koneksi database
    │   ├── csrf.php               # Helper CSRF token
    │   ├── proses_login_dan_register.php
    │   ├── proses_logout.php
    │   ├── proses_upload.php      # Upload + trigger OCR + polling status
    │   ├── proses_simpan.php      # Finalisasi data OCR
    │   ├── proses_koreksi.php     # Simpan koreksi NIK manual
    │   ├── proses_hapus_data.php  # Hapus record + file gambar
    │   ├── proses_pengaturan_pengguna.php  # CRUD user (admin)
    │   ├── proses_excel.php       # Export CSV
    │   └── proses_pdf.php         # Export PDF
    │
    ├── public/
    │   └── images/                # Gambar KTP yang diupload
    │
    └── PYTHON_OCR/
        ├── app.py                 # Flask API server
        ├── start_ocr.bat          # Script untuk menjalankan Flask
        ├── ocr_log.txt            # Log output Flask
        ├── ocr_server.pid         # PID proses Flask (dibuat otomatis)
        ├── temp_uploads/          # File sementara + gambar annotated
        └── core/
            ├── optimizer.py       # Baca gambar ke memori (RAM-based)
            ├── preprocessing.py   # Grayscale, resize, denoise, CLAHE
            ├── ocr_engine.py      # Wrapper EasyOCR
            ├── filters.py         # Ekstraksi & validasi NIK
            └── ocr_service.py     # Pipeline utama OCR
```

---

## Instalasi

### 1. Clone / Salin Proyek

Letakkan folder `TugasAkhirCapstone` di dalam direktori web Laragon:

```
C:\laragon\www\TugasAkhirCapstone\
```

### 2. Install Dependensi PHP

Buka terminal di folder root proyek, jalankan:

```bash
composer install
```

### 3. Install Dependensi Python

Buka terminal di folder `PYTHON_OCR`:

```bash
cd SistemOCRSamboja\PYTHON_OCR
pip install flask easyocr opencv-python torch torchvision werkzeug
```

> Jika komputer memiliki GPU NVIDIA, install versi CUDA dari PyTorch:
> ```bash
> pip install torch torchvision --index-url https://download.pytorch.org/whl/cu118
> ```

### 4. Build Tailwind CSS (jika perlu mengubah style)

```bash
npm install
npx tailwindcss -i ./input.css -o ./assets/css/style.css --watch
```

> Jika tidak mengubah tampilan, file `assets/css/style.css` sudah tersedia dan tidak perlu di-build ulang.

---

## Konfigurasi Database

### 1. Buat Database

Buka phpMyAdmin (`http://localhost/phpmyadmin`) atau MySQL CLI, lalu jalankan:

```sql
CREATE DATABASE ocrsambojakuala CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import Skema Tabel

Jalankan SQL berikut untuk membuat tabel yang dibutuhkan:

```sql
USE ocrsambojakuala;

CREATE TABLE staf_kecamatan (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama_lengkap  VARCHAR(150),
    role          ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
    status        ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    last_login    DATETIME,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE log_ocr (
    log_id           INT AUTO_INCREMENT PRIMARY KEY,
    id_staf          INT NOT NULL,
    nama_file_asli   VARCHAR(255),
    nama_file_sistem VARCHAR(255),
    nik_terdeteksi   VARCHAR(20),
    nik_final        VARCHAR(20),
    skor_kepercayaan FLOAT DEFAULT 0,
    status_proses    VARCHAR(50) DEFAULT 'pending',
    raw_text         LONGTEXT,
    catatan_koreksi  TEXT,
    waktu_upload     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_staf) REFERENCES staf_kecamatan(id) ON DELETE CASCADE
);
```

### 3. Konfigurasi Koneksi

Edit file `SistemOCRSamboja/proses/config.php` sesuai pengaturan database lokal:

```php
$db_host = 'localhost';
$db_port = '3306';
$db_name = 'ocrsambojakuala';
$db_user = 'root';
$db_pass = '';          // Isi password jika MySQL dikonfigurasi dengan password
```

---

## Menjalankan Sistem

### Langkah 1 — Jalankan Laragon

Buka Laragon dan klik **Start All** untuk menjalankan Apache dan MySQL.

Akses sistem di browser: `http://localhost/TugasAkhirCapstone/SistemOCRSamboja/`

### Langkah 2 — Buat Akun Admin Pertama

Saat pertama kali membuka sistem, klik **"Buat Akun Admin?"** di halaman login untuk mendaftarkan akun admin. Tombol ini otomatis hilang setelah admin pertama terdaftar.

### Langkah 3 — Nyalakan Server OCR

Login sebagai **Admin**, buka menu **Kontrol Sistem**, lalu klik **NYALAKAN SISTEM**.

Server Flask akan berjalan di background. Tunggu hingga status berubah menjadi **ONLINE** (biasanya 3–5 detik).

> Server OCR harus aktif sebelum operator dapat memproses KTP.

### Langkah 4 — Operator Mulai Bekerja

Login sebagai **Operator**, buka menu **Upload KTP**, pilih foto KTP, crop jika perlu, lalu klik **Proses File**.

---

## Alur Penggunaan

```
Login Operator
     │
     ▼
Upload & Crop Foto KTP
     │
     ▼
Sistem Proses OCR Otomatis (±5–15 detik)
     │
     ├─── NIK Terdeteksi ──► Review Hasil ──► Simpan Final
     │
     └─── NIK Tidak Terdeteksi / Salah ──► Koreksi Manual ──► Simpan
     │
     ▼
Data Tersimpan di Riwayat
     │
     ▼
Export PDF / Excel (opsional)
```

---

## Peran Pengguna

### Admin
- Menyalakan dan mematikan server OCR
- Mengelola akun operator (tambah, edit, nonaktifkan, hapus)
- Melihat riwayat scan seluruh operator
- Melihat statistik sistem

### Operator
- Upload dan memproses foto KTP
- Mereview dan mengoreksi hasil OCR
- Melihat riwayat scan pribadi
- Export data ke PDF dan Excel

---

## Keamanan

Sistem ini dirancang untuk jaringan lokal (LAN/offline). Beberapa lapisan keamanan yang diterapkan:

| Mekanisme | Implementasi |
|---|---|
| CSRF Protection | Token acak 32-byte di setiap form POST, divalidasi server-side |
| Password Hashing | `password_hash()` dengan algoritma bcrypt (PASSWORD_DEFAULT) |
| SQL Injection | Seluruh query menggunakan prepared statement (`mysqli_prepare`) |
| XSS Prevention | Output di-escape dengan `htmlspecialchars()` |
| Session Fixation | `session_regenerate_id(true)` dipanggil setelah login berhasil |
| File Upload | Validasi MIME type aktual di server (bukan hanya ekstensi) |
| Role Guard | Setiap halaman admin memverifikasi `$_SESSION['role'] === 'admin'` |
| Status Akun | Status `aktif/nonaktif` diverifikasi ulang di setiap request |
| PID-based Kill | Server OCR dihentikan berdasarkan PID, bukan `taskkill /IM python.exe` |

---

## Troubleshooting

### Server OCR tidak mau menyala
- Pastikan Python terinstall dan path-nya benar di `start_ocr.bat`
- Cek `PYTHON_OCR/ocr_log.txt` untuk melihat pesan error
- Pastikan port 5000 tidak digunakan aplikasi lain: `netstat -ano | findstr :5000`

### OCR selalu gagal / NIK tidak terdeteksi
- Pastikan foto KTP cukup terang dan tidak buram
- Gunakan fitur crop untuk memotong area KTP saja
- Cek apakah server OCR berstatus ONLINE di panel Kontrol Sistem

### Halaman error "Koneksi database gagal"
- Pastikan MySQL di Laragon sudah berjalan
- Verifikasi nama database, username, dan password di `proses/config.php`

### Gambar annotated tidak muncul di hasil OCR
- Pastikan folder `PYTHON_OCR/temp_uploads/` ada dan bisa ditulis
- Cek apakah proses OCR benar-benar selesai (status `pending_review` atau `failed`)

### Export PDF kosong atau error
- Pastikan `composer install` sudah dijalankan dan folder `vendor/` ada
- Cek apakah library FPDF tersedia di `vendor/setasign/fpdf/`

### Tombol "Keluar" tidak berfungsi
- Pastikan session PHP aktif (tidak expired)
- Cek apakah ada error PHP di log Laragon (`C:\laragon\logs\`)

---

## Catatan Pengembangan

- Seluruh aset JS dan CSS bersifat **lokal** — sistem tidak membutuhkan koneksi internet sama sekali
- Model EasyOCR diunduh otomatis saat pertama kali dijalankan dan di-cache di `~/.EasyOCR/`
- File gambar KTP disimpan permanen di `public/images/` hingga operator menghapusnya
- File annotated (visualisasi bounding box) disimpan sementara di `PYTHON_OCR/temp_uploads/` dan dihapus bersama record saat operator menghapus data
