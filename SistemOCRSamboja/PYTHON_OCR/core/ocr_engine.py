import easyocr
import warnings

warnings.filterwarnings("ignore", category=UserWarning)

"""
Inisialisasi Model EasyOCR (Pola Singleton).
Model bahasa 'id' (Indonesia) dimuat sekali saat impor modul untuk meminimalkan latensi.
Hal ini mencegah overhead pemuatan ulang model deep learning ke memori untuk setiap permintaan OCR.
"""
try:
    reader = easyocr.Reader(['id'])
except Exception as e:
    reader = None
    print(f"Fatal error: Gagal load EasyOCR model. {str(e)}")


def get_text_from_image(image):
    """
    Menjalankan EasyOCR pada gambar yang sudah diproses.

    Args:
        image: Citra numpy array hasil preprocessing.

    Returns:
        list: Daftar hasil OCR dalam format [(bbox, text, score), ...].
    """
    if reader is None:
        raise Exception("EasyOCR reader tidak terinisialisasi.")

    return reader.readtext(image, detail=1)
