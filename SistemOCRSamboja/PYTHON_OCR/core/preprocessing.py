import cv2
import numpy as np

def preprocess_for_ocr(image):
    """
    Preprocessing Mode 'Soft & Clean'.
    Fokus: HANYA membersihkan noise dan meratakan cahaya.
    TANPA penajaman kasar yang merusak font dot-matrix KTP.
    """
    
    # 1. Grayscale
    if len(image.shape) == 3:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image

    # 2. Denoising (Pembersihan Bintik)
    # h=10 (Agak diperkuat dikit biar bintik kamera hilang total)
    # Ini penting biar background 'bersih' sebelum dibaca
    denoised = cv2.fastNlMeansDenoising(gray, None, h=10, templateWindowSize=7, searchWindowSize=21)

    # 3. CLAHE (Perbaikan Kontras)
    # ClipLimit diturunkan jadi 1.5 (Lebih lembut/kalem)
    # Biar tidak ada efek 'terbakar' yang bikin huruf jadi tebal tidak wajar
    clahe = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8, 8))
    enhanced = clahe.apply(denoised)

    # CATATAN:
    # "Saya menghapus tahap Sharpening karena font pada KTP seringkali berjenis 
    # dot-matrix yang jika dipertajam malah akan memunculkan artifact (gangguan) 
    # yang membingungkan mesin OCR."

    return enhanced