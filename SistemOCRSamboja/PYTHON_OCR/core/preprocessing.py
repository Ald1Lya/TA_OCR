import cv2
import numpy as np

def preprocess_for_ocr(image):
    """
    Preprocessing Mode 'Soft & Clean' (Optimized Version).
    Fokus: Menggunakan algoritma andalan lu untuk menjaga dot-matrix,
    tapi dengan 'Diet Parameter' dan 'Auto-Resize' agar prosesnya jauh lebih ngebut.
    """
    
    # 1. Grayscale
    if len(image.shape) == 3:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image


    height, width = gray.shape
    if width > 1200:
        scale = 1200 / width
        gray = cv2.resize(gray, (int(width * scale), int(height * scale)), interpolation=cv2.INTER_AREA)


    denoised = cv2.fastNlMeansDenoising(
        gray, 
        None, 
        h=10, 
        templateWindowSize=5, 
        searchWindowSize=11
    )

    # 3. CLAHE (Sesuai racikan paten lu)
    clahe = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8, 8))
    enhanced = clahe.apply(denoised)

    return enhanced