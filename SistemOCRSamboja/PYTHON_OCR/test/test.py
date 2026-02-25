# --- Import library ---
import easyocr
import cv2
from matplotlib import pyplot as plt
import re

# --- Inisialisasi Reader EasyOCR ---
# Bahasa Indonesia ('id') dan Inggris ('en') biar lebih lengkap
reader = easyocr.Reader(['id', 'en'])

# --- Path gambar KTP ---
# ✅ Cara 1: Raw string (paling aman)
image_path = r'./SistemOCRSamboja/PYTHON_OCR/test/anomali/data2.jpeg'


# --- Jalankan OCR ---
results = reader.readtext(image_path)

# --- Tampilkan semua hasil bacaan ---
print("=== HASIL OCR RAW ===")
for (bbox, text, prob) in results:
    print(f"[{prob:.2f}] {text}")

# --- Tampilkan hasil OCR dengan bounding box di gambar ---
img = cv2.imread(image_path)
for (bbox, text, prob) in results:
    top_left = tuple(map(int, bbox[0]))
    bottom_right = tuple(map(int, bbox[2]))
    cv2.rectangle(img, top_left, bottom_right, (0, 255, 0), 2)
    cv2.putText(img, text, (top_left[0], top_left[1] - 10),
                cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)

plt.imshow(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
plt.axis('off')
plt.show()

# --- PARSING OTOMATIS DASAR ---
# Gabungkan semua teks jadi satu string besar
all_text = ' '.join([text for (_, text, _) in results]).upper()

data_ktp = {}

# Cari NIK (biasanya 16 digit)
nik_match = re.search(r'\b\d{16}\b', all_text)
if nik_match:
    data_ktp['NIK'] = nik_match.group()

# Cari Nama (setelah kata NAMA)
nama_match = re.search(r'NAMA[:\s]+([A-Z\s]+)', all_text)
if nama_match:
    data_ktp['Nama'] = nama_match.group(1).strip()

# Cari Alamat (setelah kata ALAMAT)
alamat_match = re.search(r'ALAMAT[:\s]+([A-Z0-9\s,.]+?)(RT|RW|KEL|DESA|KELURAHAN|KEC|KABUPATEN|PROVINSI)', all_text)
if alamat_match:
    data_ktp['Alamat'] = alamat_match.group(1).strip()

print("\n=== DATA KTP TERBACA ===")
for key, value in data_ktp.items():
    print(f"{key}: {value}")
