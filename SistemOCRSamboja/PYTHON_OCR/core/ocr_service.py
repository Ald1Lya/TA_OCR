import cv2
import os
import re

from .optimizer import optimize_image
from .preprocessing import preprocess_for_ocr
from .ocr_engine import get_text_from_image
from .filters import filter_and_cleanse_nik


def draw_and_save_bounding_box(image_path: str, img, ocr_results: list, target_nik: str) -> str:
    """
    Menggambar bounding box pada gambar hasil OCR dan menyimpannya sebagai file annotated.

    NIK target ditandai dengan kotak merah tebal, teks lain dengan kotak hijau tipis.
    File disimpan di direktori yang sama dengan gambar input dengan prefix 'annotated_'.

    Returns:
        str: Nama file annotated yang disimpan.
    """
    annotated_img = img.copy()

    for item in ocr_results:
        if len(item) != 3:
            continue

        bbox, text, score = item
        pt1 = (int(bbox[0][0]), int(bbox[0][1]))
        pt2 = (int(bbox[2][0]), int(bbox[2][1]))

        clean_text = re.sub(r'[^A-Z0-9]', '', text.upper())
        is_target  = bool(target_nik and target_nik in clean_text)

        if is_target:
            cv2.rectangle(annotated_img, pt1, pt2, (0, 0, 255), 3)
            cv2.putText(annotated_img, f"TARGET NIK (Score: {score:.2f})",
                        (pt1[0], pt1[1] - 12), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)
        else:
            cv2.rectangle(annotated_img, pt1, pt2, (0, 255, 0), 1)
            cv2.putText(annotated_img, f"{text} ({score:.2f})",
                        (pt1[0], pt1[1] - 5), cv2.FONT_HERSHEY_SIMPLEX, 0.4, (0, 200, 0), 1)

    temp_dir           = os.path.dirname(image_path)
    base_name          = os.path.basename(image_path)
    annotated_filename = "annotated_" + base_name
    annotated_path     = os.path.join(temp_dir, annotated_filename)

    try:
        is_success, im_buf = cv2.imencode(".jpg", annotated_img)
        if is_success:
            with open(annotated_path, "wb") as f_out:
                f_out.write(im_buf)
    except Exception as e:
        print(f"Gagal simpan visualisasi bounding box: {e}")

    return annotated_filename


def text_recognition_pipeline(image_path: str) -> dict:
    """
    Pipeline utama OCR: dari path gambar hingga menghasilkan NIK dan skor kepercayaan.

    Tahapan:
      1. optimize_image   — baca gambar ke memori secara aman.
      2. preprocess_for_ocr — grayscale, resize, denoise, CLAHE.
      3. get_text_from_image — jalankan EasyOCR, dapatkan bounding box.
      4. filter_and_cleanse_nik — ekstrak NIK terbaik dan hitung skor.
      5. draw_and_save_bounding_box — simpan visualisasi hasil deteksi.

    Returns:
        dict: {"nik", "score", "status", "notes", "raw_text"}
    """
    optimized_img, error = optimize_image(image_path)
    if error:
        return {"nik": None, "score": 0.0, "status": f"Optimize Error: {error}"}

    preprocessed_img = preprocess_for_ocr(optimized_img)
    ocr_results      = get_text_from_image(preprocessed_img)
    result_tuple     = filter_and_cleanse_nik(ocr_results)

    if len(result_tuple) == 4:
        nik, final_score, reason, raw_text = result_tuple
    else:
        nik, final_score, reason = result_tuple
        raw_text = ""

    draw_and_save_bounding_box(image_path, optimized_img, ocr_results, nik)

    # Bebaskan memori gambar besar setelah pipeline selesai
    if 'optimized_img' in locals():
        del optimized_img
    if 'preprocessed_img' in locals():
        del preprocessed_img

    if nik:
        return {"nik": nik, "score": float(final_score), "status": "success", "notes": reason, "raw_text": raw_text}
    else:
        return {"nik": None, "score": 0.0, "status": f"failed: {reason}", "raw_text": raw_text}
