import re

# --- FUNGSI VALIDATOR ---
def validate_is_ktp(ocr_results, min_keywords=2):
    ktp_keywords = [
        "PROVINSI", "KABUPATEN", "KOTA", "NIK", "NAM", "LAHIR", 
        "ALAMAT", "AGAMA", "KAWIN", "PEKERJAAN", "WARGA", "BERLAKU"
    ]
    found_count = 0
    for item in ocr_results:
        if not item or len(item) < 2:
            continue
        text = item[1].upper()
        for keyword in ktp_keywords:
            if keyword in text:
                found_count += 1
                break 
    if found_count >= min_keywords:
        return True
    return False

# --- FUNGSI FILTER & CLEANSE ---
def filter_and_cleanse_nik(ocr_results):
    if not validate_is_ktp(ocr_results):
        return (None, 0.0, "Document rejected: Not a KTP")

    nik_regex = re.compile(r'\d{16}')

    all_numbers_found = ""
    accumulated_score = 0.0
    score_count = 0
    best_candidate = None
    best_score = -1.0

    for item in ocr_results:
        if not isinstance(item, (list, tuple)) or len(item) < 3:
            continue
        
        bbox, text, score = item
        if not text or not isinstance(text, str):
            continue
        
        # 1. Ambil teks dan jadikan huruf besar
        raw_text = text.upper()
        
        # 2. TAHAP BARU: Buang kata pengganggu SEBELUM translasi!
        # Ini mencegah huruf 'I' di 'NIK' berubah jadi angka '1'
        words_to_remove = ["NIK", "PROVINSI", "KOTA", "KABUPATEN", "KAB", "ISLAM"]
        for word in words_to_remove:
            raw_text = raw_text.replace(word, "")
        
        # 3. Cleanup Agresif
        clean = (
            raw_text
                .replace(" ", "")
                .replace(":", "") 
                .replace("-", "")
                .replace(".", "")
                .translate(str.maketrans({
                    "O": "0", "D": "0", "U": "0", "Q": "0", "C": "0",
                    "I": "1", "L": "1", "|": "1", "]": "1", "!": "1", "T": "1",
                    "B": "8", "&": "8",
                    "S": "5", "$": "5",
                    "G": "6",
                    "Z": "2",
                    "A": "4",
                    "J": "7", "?": "7",
                    "E": "3" 
                }))
        )

        # 4. Cari NIK
        match = nik_regex.search(clean)
        if match:
            nik_found = match.group(0)
            try:
                base_score = float(score)
            except:
                base_score = 0.0
            
            hybrid_score = (base_score * 0.7) + 0.3 
            hybrid_score = round(min(hybrid_score, 0.97), 4)

            if hybrid_score > best_score:
                best_score = hybrid_score
                best_candidate = nik_found
        
        # Logika Sapu Jagat
        digits_only = "".join(filter(str.isdigit, clean))
        if len(digits_only) > 0: 
            all_numbers_found += digits_only
            try:
                accumulated_score += float(score)
                score_count += 1
            except:
                pass

    # --- EKSEKUSI STRATEGI CADANGAN ---
    if not best_candidate:
        match_fallback = nik_regex.search(all_numbers_found)
        if match_fallback:
            avg_visual_score = accumulated_score / score_count if score_count > 0 else 0.0
            final_fallback_score = (avg_visual_score * 0.6) + 0.4
            final_fallback_score = round(min(final_fallback_score, 0.95), 4)
            return (match_fallback.group(0), final_fallback_score, "Success (Fallback)")

    return (best_candidate, best_score, "Success") if best_candidate else (None, 0.0, "NIK not found")