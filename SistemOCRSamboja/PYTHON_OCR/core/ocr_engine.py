import easyocr
import warnings


warnings.filterwarnings("ignore", category=UserWarning) 
try:
    reader = easyocr.Reader(['id']) 
except Exception as e:
    reader = None
    print(f"Fatal error: Gagal load EasyOCR model. {str(e)}")

def get_text_from_image(image):
    """
    Menjalankan komponen EasyOCR pada gambar yang sudah diproses.
    """
    if reader is None:
        raise Exception("EasyOCR reader tidak terinisialisasi.")
        
  
    results = reader.readtext(image, detail=1)
    return results