import cv2
import os
import re
from .optimizer import optimize_image
from .preprocessing import preprocess_for_ocr
from .ocr_engine import get_text_from_image
from .filters import filter_and_cleanse_nik

def draw_and_save_bounding_box(image_path, img, ocr_results, target_nik):
    """
    Menggambar kotak bounding box.
    Teks biasa = Hijau tipis + Skor OCR.
    Target NIK = Merah tebal agar user tahu dari mana asal skor akurasi.
    """
    annotated_img = img.copy() 
    
    for item in ocr_results:
        if len(item) == 3:
            bbox, text, score = item
            # Koordinat kotak
            pt1 = (int(bbox[0][0]), int(bbox[0][1])) 
            pt2 = (int(bbox[2][0]), int(bbox[2][1])) 
            
            # Bersihkan teks untuk pencocokan target
            clean_text = re.sub(r'[^A-Z0-9]', '', text.upper())
            
            # Cek apakah ini target NIK yang berhasil difilter
            is_target = False
            if target_nik and target_nik in clean_text:
                is_target = True
                
            if is_target:
                # HIGHLIGHT TARGET NIK (Kotak Merah Tebal)
                cv2.rectangle(annotated_img, pt1, pt2, (0, 0, 255), 3)
                label = f"TARGET NIK (OCR Score: {score:.2f})"
                # Font sedikit lebih besar untuk target
                cv2.putText(annotated_img, label, (pt1[0], pt1[1] - 12), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)
            else:
                # TEKS BIASA / NIK MENTAH (Kotak Hijau Tipis)
                cv2.rectangle(annotated_img, pt1, pt2, (0, 255, 0), 1)
                # Tampilkan apa adanya yang dibaca mesin + skornya
                label_biasa = f"{text} ({score:.2f})"
                cv2.putText(annotated_img, label_biasa, (pt1[0], pt1[1] - 5), cv2.FONT_HERSHEY_SIMPLEX, 0.4, (0, 200, 0), 1)

    # Simpan ke folder temp_uploads
    temp_dir = os.path.dirname(image_path)
    base_name = os.path.basename(image_path)
    annotated_filename = "annotated_" + base_name
    annotated_temp_path = os.path.join(temp_dir, annotated_filename)
    
    try:
        # Encode gambar ke memori dulu
        is_success, im_buf = cv2.imencode(".jpg", annotated_img)
        if is_success:
            # Tulis ke file fisik dan langsung TUTUP
            with open(annotated_temp_path, "wb") as f_out:
                f_out.write(im_buf)
    except Exception as e:
        print(f"Gagal simpan visualisasi: {e}")
        
    return annotated_filename

# --- METODE MAYOR (PIPELINE UTAMA) ---
def text_recognition_pipeline(image_path):
    
    # 1. TAHAP OPTIMASI
    optimized_img, error = optimize_image(image_path)
    if error:
        return {"nik": None, "score": 0.0, "status": f"Optimize Error: {error}"}
        
    # 2. TAHAP PREPROCESSING
    preprocessed_img = preprocess_for_ocr(optimized_img)
    
    # 3. TAHAP OCR ENGINE
    ocr_results = get_text_from_image(preprocessed_img)
    
    # 4. TAHAP FILTER & VALIDASI (Cari NIK-nya TERLEBIH DAHULU!)
    result_tuple = filter_and_cleanse_nik(ocr_results)
    
    if len(result_tuple) == 3:
        nik, final_score, reason = result_tuple
    else:
        nik, final_score = result_tuple
        reason = "Legacy check"

    # --- TAHAP VISUALISASI DENGAN TARGET ---
    draw_and_save_bounding_box(image_path, optimized_img, ocr_results, nik)
    if nik:
        return {"nik": nik, "score": float(final_score), "status": "success", "notes": reason}
    else:
        return {"nik": None, "score": 0.0, "status": f"failed: {reason}"}