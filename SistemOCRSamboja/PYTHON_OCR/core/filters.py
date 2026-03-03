import re

def validate_is_ktp(ocr_results, min_keywords=2):
    # Cek cepat apakah dokumen ini kemungkinan KTP
    ktp_keywords = [
        "PROVINSI", "KABUPATEN", "KOTA", "NIK",
        "NAM", "LAHIR", "ALAMAT", "AGAMA",
        "KAWIN", "PEKERJAAN", "WARGA", "BERLAKU"
    ]

    found = 0
    for item in ocr_results:
        if not item or len(item) < 2:
            continue

        text = item[1].upper()
        if any(k in text for k in ktp_keywords):
            found += 1

    return found >= min_keywords


def filter_and_cleanse_nik(ocr_results):
    if not validate_is_ktp(ocr_results):
        return (None, 0.0, "Not a KTP document")

    nik_regex = re.compile(r'\d{16}')

    best_nik = None
    best_score = -1.0

    collected_digits = ""
    total_score = 0.0
    score_count = 0

    for item in ocr_results:
        if not isinstance(item, (list, tuple)) or len(item) < 3:
            continue

        bbox, text, score = item
        if not text:
            continue

        raw = text.upper()

        # Buang kata yang sering ganggu deteksi angka
        for word in ["NIK", "PROVINSI", "KOTA", "KABUPATEN", "KAB", "ISLAM"]:
            raw = raw.replace(word, "")

        clean = (
            raw.replace(" ", "")
               .replace(":", "")
               .replace("-", "")
               .replace(".", "")
               .translate(str.maketrans({
                    "O": "0", "D": "0", "U": "0", "Q": "0",
                    "I": "1", "L": "1", "|": "1",
                    "B": "8",
                    "S": "5",
                    "G": "6",
                    "Z": "2",
                    "A": "4",
                    "E": "3"
               }))
        )

        match = nik_regex.search(clean)
        try:
            score_val = float(score)
        except:
            score_val = 0.0

        if match:
            hybrid_score = min((score_val * 0.7) + 0.3, 0.97)
            if hybrid_score > best_score:
                best_score = hybrid_score
                best_nik = match.group(0)

        digits = "".join(filter(str.isdigit, clean))
        if digits:
            collected_digits += digits
            total_score += score_val
            score_count += 1

    # Fallback kalau NIK kepisah-pisah
    if not best_nik:
        fallback = nik_regex.search(collected_digits)
        if fallback:
            avg_score = total_score / score_count if score_count else 0.0
            final_score = min((avg_score * 0.6) + 0.4, 0.95)
            return (fallback.group(0), final_score, "Fallback result")

    return (best_nik, best_score, "Success") if best_nik else (None, 0.0, "NIK not found")