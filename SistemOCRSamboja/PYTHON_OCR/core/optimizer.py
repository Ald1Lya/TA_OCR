import cv2
import numpy as np


def optimize_image(image_path: str):
    """
    Membaca file gambar ke memori (RAM-based loading) sebelum di-decode oleh OpenCV.
    Pendekatan ini menghindari potensi file-lock pada sistem operasi Windows.

    Returns:
        tuple: (img, None) jika berhasil, atau (None, pesan_error) jika gagal.
    """
    try:
        with open(image_path, 'rb') as f:
            file_bytes = np.asarray(bytearray(f.read()), dtype=np.uint8)

        img = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)

        if img is None:
            raise ValueError("Gambar gagal dibaca atau format tidak didukung.")

        return img, None

    except Exception as e:
        return None, str(e)
