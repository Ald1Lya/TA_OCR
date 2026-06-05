import cv2
import numpy as np


def preprocess_for_ocr(image):
    """
    Menerapkan pipeline prapemrosesan citra untuk mengoptimalkan citra KTP sebelum ekstraksi OCR.
    
    Tahapan Pemrosesan:
      1. Konversi Grayscale: Menyederhanakan data warna untuk deteksi kontur.
      2. Adaptive Resizing: Membatasi lebar maksimum menjadi 1200px untuk mengurangi beban komputasi.
      3. Fast Non-Local Means Denoising: Menghilangkan noise tanpa merusak ketajaman teks.
      4. CLAHE (Contrast Limited Adaptive Histogram Equalization): Meningkatkan kontras lokal secara dinamis.
      
    Argumen:
        image (numpy.ndarray): Input citra mentah dalam format BGR.
        
    Kembalian:
        numpy.ndarray: Matriks citra grayscale yang telah dioptimalkan.
    """
    # Ubah menjadi grayscale 1-channel jika input adalah BGR 3-channel
    if len(image.shape) == 3:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image

    # Turunkan resolusi dimensi jika terlalu membebani komputasi (Lebar > 1200px)
    height, width = gray.shape
    if width > 1200:
        scale = 1200 / width
        gray  = cv2.resize(gray, (int(width * scale), int(height * scale)), interpolation=cv2.INTER_AREA)

    # Terapkan Denoising: Parameter moderat (h=10) untuk mempertahankan batas teks
    denoised = cv2.fastNlMeansDenoising(
        gray,
        None,
        h=10,
        templateWindowSize=5,
        searchWindowSize=11
    )

    # Terapkan CLAHE: clipLimit membatasi amplifikasi noise, tileGridSize memberikan granularitas lokal
    clahe    = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8, 8))
    enhanced = clahe.apply(denoised)

    # ========================================================================
    # TOMBOL RAHASIA SIDANG (Hapus tanda pagar '#' buat ngeluarin gambar fisik)
    # ========================================================================
    import os
    import time
    os.makedirs("hasil_prepro_debug", exist_ok=True)
    
    # Bikin nama file unik pakai waktu biar kalau upload 2 KTP ga saling timpa
    nama_file_unik = str(int(time.time()))
    cv2.imwrite(f"hasil_prepro_debug/bukti_prepro_{nama_file_unik}.jpg", enhanced)
    # ========================================================================

    return enhanced
