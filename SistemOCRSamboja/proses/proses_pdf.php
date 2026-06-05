<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- KOP SURAT ---
$logo_path = realpath(__DIR__ . '/../../assetimage/logo.png');
if ($logo_path && file_exists($logo_path)) {
    $pdf->Image($logo_path, 15, 10, 20);
}

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 6, 'PEMERINTAH KABUPATEN KUTAI KARTANEGARA', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 8, 'KECAMATAN SAMBOJA KUALA', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Jalan Poros Balikpapan - Handil, Kecamatan Samboja Kuala, Kode Pos 75271', 0, 1, 'C');

// Garis Kop Surat
$pdf->SetLineWidth(0.8);
$pdf->Line(10, 33, 287, 33);
$pdf->SetLineWidth(0.3);
$pdf->Line(10, 34.5, 287, 34.5);
$pdf->Ln(10);
// -----------------

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'LAPORAN RIWAYAT EKSTRAKSI NIK (SISTEM OCR)', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d F Y, H:i') . ' WITA', 0, 1, 'C');
$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 240, 255); // Warna biru muda elegan
$pdf->SetDrawColor(150, 150, 150);

// Lebar kolom dalam mm (landscape A4)
$w      = [10, 35, 80, 35, 35, 20, 60];
$header = ['No', 'Waktu', 'Nama File', 'NIK', 'Status', 'Skor', 'Operator'];

for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 10, $header[$i], 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 9);

$query  = "SELECT lo.waktu_upload, lo.nama_file_asli, COALESCE(lo.nik_final, lo.nik_terdeteksi) AS nik, lo.status_proses, lo.skor_kepercayaan, sk.nama_lengkap FROM log_ocr lo LEFT JOIN staf_kecamatan sk ON lo.id_staf = sk.id ORDER BY lo.waktu_upload DESC";
$result = mysqli_query($db, $query);
$no     = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $nama_file = $row['nama_file_asli'];
    if (strlen($nama_file) > 35) {
        $nama_file = substr($nama_file, 0, 32) . '...';
    }

    $status   = ucwords(str_replace('_', ' ', $row['status_proses']));
    $nik      = !empty($row['nik']) ? $row['nik'] : '-';
    $operator = $row['nama_lengkap'] ?? 'Sistem';
    if (strlen($operator) > 25) {
        $operator = substr($operator, 0, 22) . '...';
    }

    $pdf->Cell($w[0], 8, $no++, 1, 0, 'C');
    $pdf->Cell($w[1], 8, date('d/m/y H:i', strtotime($row['waktu_upload'])), 1, 0, 'C');
    $pdf->Cell($w[2], 8, ' ' . $nama_file, 1, 0, 'L');
    $pdf->Cell($w[3], 8, $nik, 1, 0, 'C');
    $pdf->Cell($w[4], 8, $status, 1, 0, 'C');
    $pdf->Cell($w[5], 8, number_format((float) $row['skor_kepercayaan'] * 100, 0) . '%', 1, 0, 'C');
    $pdf->Cell($w[6], 8, ' ' . $operator, 1, 0, 'L');
    $pdf->Ln();
}

$pdf->Output('D', 'Laporan_Riwayat_OCR.pdf');
mysqli_close($db);
exit;
?>
