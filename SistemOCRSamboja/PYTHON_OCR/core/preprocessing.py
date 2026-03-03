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

 
    denoised = cv2.fastNlMeansDenoising(gray, None, h=10, templateWindowSize=7, searchWindowSize=21)


    clahe = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8, 8))
    enhanced = clahe.apply(denoised)


    return enhanced