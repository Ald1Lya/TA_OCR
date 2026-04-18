import re

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

    # 1. Regex untuk NIK Sempurna (16 digit) dan NIK Cacat (14-15 digit)
    nik_regex_perfect = re.compile(r'\d{16}')
    nik_regex_partial = re.compile(r'\d{14,15}') # Toleransi hilang 1-2 angka

    best_nik = None
    best_score = -1.0
    is_nik_perfect = False # Penanda apakah NIK genap 16 atau kurang
    
    full_text_combined = ""
    total_score = 0.0
    score_count = 0

    for item in ocr_results:
        if not isinstance(item, (list, tuple)) or len(item) < 3:
            continue

        bbox, text, score = item
        if not text:
            continue

        raw = text.upper()
        full_text_combined += raw + " "

        for word in ["NIK", "PROVINSI", "KOTA", "KABUPATEN", "KAB", "ISLAM"]:
            raw = raw.replace(word, "")

        clean = (
            raw.replace(" ", "")
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

        # Cari yang 16 digit dulu
        match = nik_regex_perfect.search(clean)
        perfect_flag = True
        
        # Kalau gagal, cari yang 14-15 digit
        if not match:
            match = nik_regex_partial.search(clean)
            perfect_flag = False

        try:
            score_val = float(score)
        except:
            score_val = 0.0

        if match:
            hybrid_score = min((score_val * 0.7) + 0.3, 0.97)
            if hybrid_score > best_score:
                best_score = hybrid_score
                best_nik = match.group(0)
                is_nik_perfect = perfect_flag

        total_score += score_val
        score_count += 1

    # --- STRATEGI CADANGAN (MERGED TEXT) ---
    if not best_nik:
        clean_full = (
            full_text_combined
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
        
        # Cari 16 digit di gabungan teks
        fallback = nik_regex_perfect.search(clean_full)
        perfect_flag = True
        
        # Kalau gagal, cari 14-15 digit
        if not fallback:
            fallback = nik_regex_partial.search(clean_full)
            perfect_flag = False

        if fallback:
            avg_score = total_score / score_count if score_count > 0 else 0.0
            final_score = min((avg_score * 0.6) + 0.4, 0.95)
            best_nik = fallback.group(0)
            best_score = final_score
            is_nik_perfect = perfect_flag

    # --- (GATEKEEPER Final) ---
    if best_nik:
        if is_nik_perfect:
            # SKENARIO A: Punya 16 Digit Penuh
            if keyword_count >= 1:
                return (best_nik, best_score, "Success")
            else:
                if is_valid_nik_pattern(best_nik):
                    penalized_score = min(best_score, 0.5) 
                    return (best_nik, penalized_score, "Success (Blurry KTP - Valid Date Pattern)")
                else:
                    return (None, 0.0, "Document rejected: Not a KTP (Invalid NIK Date Pattern)")
        else:
            # SKENARIO B: Digit Kurang (Cuma 14 atau 15 angka)
            if keyword_count >= 1:
                # skornya dipaksa hancur maks 40 biar operator sadar ada angka yang hilang.
                penalized_score = min(best_score, 0.4)
                return (best_nik, penalized_score, f"Success (Incomplete NIK: {len(best_nik)} digits)")
            else:
                # ZONK Gak ada teks KTP, dan angkanya pun ga genap 16. 
                return (None, 0.0, "Document rejected: No KTP keywords and NIK is incomplete")
    else:
        return (None, 0.0, "NIK not found")