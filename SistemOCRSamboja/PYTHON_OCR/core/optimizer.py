import cv2
import os

def optimize_image(image_path):
    """
    Metode Minor #1 (Si Tukang Pintu).
    Diperbarui: Resize berdasarkan DIMENSI, bukan cuma file size.
    Ini bakal ngebut banget buat foto HP/iPhone.
    """
    try:
        img = cv2.imread(image_path)
        if img is None:
            raise ValueError("Gagal membaca gambar.")
            
        # Target maksimal dimensi. 1000px udah sangat cukup dan cepat buat EasyOCR
        max_dim = 1000  
        h, w = img.shape[:2]
        
        # Kalau tinggi atau lebarnya lebih dari 1000px, paksa resize!
        if h > max_dim or w > max_dim:
            scale = max_dim / max(h, w)
            img = cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)

        return img, None

    except Exception as e:
        return None, str(e)