import cv2
import numpy as np  # Pastikan install: pip install numpy
import os

def optimize_image(image_path):
    """
    Tukang Pintu.
    Membaca file sebagai bytes ke RAM dulu, baru didecode.
    Ini menjamin file fisik TIDAK DIKUNCI oleh OpenCV.
    """
    try:
        
        # BACA DATA KE MEMORI LALU TUTUP FILE
        with open(image_path, 'rb') as f:
            file_bytes = np.asarray(bytearray(f.read()), dtype=np.uint8)
            
        # DECODE DARI MEMORI (File fisik sudah bebas)
        img = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)

        if img is None:
            raise ValueError("Gagal membaca gambar.")
            
        # Logika Resize Tetap Sama
        max_dim = 1000  
        h, w = img.shape[:2]
        
        if h > max_dim or w > max_dim:
            scale = max_dim / max(h, w)
            img = cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)

        return img, None

    except Exception as e:
        return None, str(e)