import cv2
import numpy as np


def preprocess_for_ocr(image):
    """
    Mempersiapkan citra KTP sebelum diproses mesin OCR.

    Tahapan:
      1. Konversi ke grayscale — menyederhanakan data warna.
      2. Resize adaptif — membatasi lebar maksimal 1200px untuk efisiensi.
      3. Denoising (Fast Non-Local Means) — mengurangi noise tanpa merusak teks.
      4. CLAHE — meningkatkan kontras secara lokal dan adaptif.
    """
    # Konversi ke grayscale jika gambar masih berwarna
    if len(image.shape) == 3:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image

    # Resize jika lebar melebihi 1200px untuk mengurangi beban komputasi
    height, width = gray.shape
    if width > 1200:
        scale = 1200 / width
        gray  = cv2.resize(gray, (int(width * scale), int(height * scale)), interpolation=cv2.INTER_AREA)

    # Denoising: h=10 adalah kekuatan penghilangan noise (moderat agar teks tidak ikut terhapus)
    denoised = cv2.fastNlMeansDenoising(
        gray,
        None,
        h=10,
        templateWindowSize=5,
        searchWindowSize=11
    )

    # CLAHE: clipLimit=1.5 mencegah over-amplifikasi noise, tileGridSize=(8,8) untuk granularitas lokal
    clahe    = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8, 8))
    enhanced = clahe.apply(denoised)

    return enhanced
