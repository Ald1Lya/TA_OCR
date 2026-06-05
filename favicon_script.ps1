# ========================================================
# AUTO INJECT PROFESSIONAL FAVICON & META TAGS
# ========================================================

# 1. Setup Tag untuk Root (Operator)
$iconTagsOperator = @"
<head>
    <!-- App Icons & Meta Theme -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assetimage/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../assetimage/favicon-16x16.png" />
    <link rel="apple-touch-icon" sizes="180x180" href="../assetimage/apple-touch-icon.png" />
    <meta name="theme-color" content="#16a34a" />
"@

# 2. Setup Tag untuk Admin (Path Mundur 2x)
$iconTagsAdmin = @"
<head>
    <!-- App Icons & Meta Theme -->
    <link rel="icon" type="image/png" sizes="32x32" href="../../assetimage/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assetimage/favicon-16x16.png" />
    <link rel="apple-touch-icon" sizes="180x180" href="../../assetimage/apple-touch-icon.png" />
    <meta name="theme-color" content="#16a34a" />
"@

# Eksekusi Folder Operator (*.php)
$files1 = Get-ChildItem -Path "c:\laragon\www\TugasAkhirCapstone\SistemOCRSamboja\*.php"
foreach ($file in $files1) {
    $content = Get-Content -Raw $file.FullName
    if ($content -match '<head>') {
        # Hindari injeksi ganda kalau skrip jalan 2 kali
        if (-not ($content -match 'theme-color')) {
            $content = $content -replace '<head>', $iconTagsOperator
            Set-Content -NoNewline -Path $file.FullName -Value $content
            Write-Host "Injected Operator: $($file.Name)" -ForegroundColor Green
        }
    }
}

# Eksekusi Folder Admin (admin\*.php)
$files2 = Get-ChildItem -Path "c:\laragon\www\TugasAkhirCapstone\SistemOCRSamboja\admin\*.php"
foreach ($file in $files2) {
    $content = Get-Content -Raw $file.FullName
    if ($content -match '<head>') {
        # Hindari injeksi ganda
        if (-not ($content -match 'theme-color')) {
            $content = $content -replace '<head>', $iconTagsAdmin
            Set-Content -NoNewline -Path $file.FullName -Value $content
            Write-Host "Injected Admin: $($file.Name)" -ForegroundColor Cyan
        }
    }
}

Write-Host "DONE! Semua file udah pakai icon profesional!" -ForegroundColor Yellow