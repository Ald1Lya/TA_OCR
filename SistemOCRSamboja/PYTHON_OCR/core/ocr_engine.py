import easyocr
import warnings

warnings.filterwarnings("ignore", category=UserWarning)

# Model EasyOCR dimuat sekali saat modul pertama kali diimpor (singleton).
# Bahasa 'id' (Indonesia) dipilih karena KTP menggunakan teks Bahasa Indonesia.
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
