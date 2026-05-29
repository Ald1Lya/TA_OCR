import re

# [FIX OPTIMASI]: Kamusnya ditaruh di LUAR looping biar laptop lu enteng!
KAMUS_REPLACE = str.maketrans({
    "O": "0", "o": "0", "D": "0", "d": "0", "U": "0", "u": "0", "Q": "0", "q": "0", "C": "0", "c": "0",
    "I": "1", "i": "1", "L": "1", "l": "1", "|": "1", "]": "1", "[": "1", "!": "1", "T": "1", "t": "1",
    "B": "8", "&": "8",
    "b": "6", "G": "6", "g": "6", 
    "S": "5", "s": "5", "$": "5",
    "Z": "2", "z": "2",
    "A": "4", "a": "4",
    "J": "7", "j": "7", "?": "7",
    "E": "3", "e": "3",
    "P": "9", "p": "9"
})

def count_ktp_keywords(ocr_results):
    ktp_keywords = [
        "PROVINSI", "KABUPATEN", "KOTA", "NIK",
        "NAM", "LAHIR", "ALAMAT", "AGAMA", "DARAH",
        "KAWIN", "PEKERJAAN", "WARGA", "BERLAKU",
        "KELURAHAN", "DESA", "RT", "RW", "GOL"
    ]

    found = 0
    for item in ocr_results:
        if not item or len(item) < 2:
            continue
        text = item[1].upper()
        if any(k in text for k in ktp_keywords):
            found += 1
    return found

def is_valid_nik_pattern(nik_string):
    if not nik_string or len(nik_string) != 16 or not nik_string.isdigit():
        return False
    try:
        dd = int(nik_string[6:8])
        mm = int(nik_string[8:10])
        valid_day = (1 <= dd <= 31) or (41 <= dd <= 71)
        valid_month = (1 <= mm <= 12)
        return valid_day and valid_month
    except:
        return False

def filter_and_cleanse_nik(ocr_results):
    keyword_count = count_ktp_keywords(ocr_results)
    nik_regex_perfect = re.compile(r'\d{16}')

    best_nik = None
    best_score = -1.0
    full_text_combined = ""
    total_score = 0.0
    score_count = 0
    raw_details = []
    best_raw_score = 0.0 

    for item in ocr_results:
        if not isinstance(item, (list, tuple)) or len(item) < 3:
            continue

        bbox, text, score = item
        if not text:
            continue

        try:
            score_val = float(score)
            raw_details.append({"text": text, "score": score_val})
            total_score += score_val
            score_count += 1
        except:
            score_val = 0.0

        # Simpan buat fallback (dibesarin semua gapapa buat digabung)
        full_text_combined += text + " " 

        # --- FIX: Bersihin kata pakai teks asli (bukan yang udah di-upper)
        clean_text = text
        for word in ["NIK", "PROVINSI", "KOTA", "KABUPATEN", "KAB", "ISLAM", "nik", "provinsi", "kota", "kabupaten", "kab", "islam"]:
            clean_text = clean_text.replace(word, "")

        # --- FIX: Panggil kamus global
        clean = (
            clean_text.replace(" ", "")
            .replace(":", "")
            .replace("-", "")
            .replace(".", "")
            .translate(KAMUS_REPLACE)
        )

        match = nik_regex_perfect.search(clean)

        if match:
            boosted_score = min(score_val + 0.15, 0.97)
            if boosted_score > best_score:
                best_score = boosted_score
                best_nik = match.group(0)
                best_raw_score = score_val

    if not best_nik:
        clean_full = (
            full_text_combined
            .replace(" ", "")
            .replace(":", "")
            .replace("-", "")
            .replace(".", "")
            .translate(KAMUS_REPLACE)
        )

        fallback = nik_regex_perfect.search(clean_full)

        if fallback:
            avg_score = total_score / score_count if score_count > 0 else 0.0
            boosted_score = min(avg_score + 0.15, 0.97)
            best_nik = fallback.group(0)
            best_score = boosted_score
            best_raw_score = avg_score

    debug_data = {
        "semua_bounding_box": raw_details,
        "skor_mentah_easyocr": best_raw_score,
        "skor_hybrid_awal": best_score,
        "keyword_ditemukan": keyword_count,
        "genap_16_digit": best_nik is not None
    }

    if best_nik:
        if keyword_count >= 1:
            return (best_nik, best_score, "Success", debug_data)
        else:
            if is_valid_nik_pattern(best_nik):
                return (best_nik, best_score, "Success (Blurry KTP - Valid Date Pattern)", debug_data)
            else:
                return (None, 0.0, "Document rejected: Not a KTP (Invalid NIK Date Pattern)", debug_data)
    else:
        return (None, 0.0, "Document rejected: 16 Digit NIK not found", debug_data)