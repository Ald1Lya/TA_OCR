from flask import Flask, request, jsonify
import os
import torch
import logging
import gc 
from logging.handlers import RotatingFileHandler
from werkzeug.utils import secure_filename
try:
    from waitress import serve 
    HAS_WAITRESS = True
except ImportError:
    HAS_WAITRESS = False

from core.ocr_service import text_recognition_pipeline

app = Flask(__name__)

# Konfigurasi Log Rotasi Aman (Anti Crash Windows Lock)
try:
    log_formatter = logging.Formatter('%(asctime)s - %(message)s')
    log_handler = RotatingFileHandler('ocr_log.txt', maxBytes=2*1024*1024, backupCount=1)
    log_handler.setFormatter(log_formatter)
    log_handler.setLevel(logging.INFO)
    app.logger.addHandler(log_handler)
    app.logger.setLevel(logging.INFO)
except Exception:
    pass

app.config['UPLOAD_FOLDER'] = os.path.abspath(os.path.join(os.path.dirname(__file__), 'temp_uploads'))
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

@app.route('/ocr', methods=['POST'])
def process_ocr():
    app.logger.info("=== Memulai Penerimaan Request OCR ===")
    
    file = None
    temp_image_path = None
    
    # PERBAIKAN: KEMBALI KE 'file' SESUAI KODE GITHUB ASLI LU
    if 'file' not in request.files:
        app.logger.error("Ditolak: Kunci 'file' tidak ditemukan.")
        return jsonify({"status": "error", "message": "Missing 'file' key"}), 400

    file = request.files['file']
    if file.filename == '':
        app.logger.error("Ditolak: Nama file kosong.")
        return jsonify({"status": "error", "message": "No selected file"}), 400

    filename         = secure_filename(file.filename)
    temp_image_path  = os.path.abspath(os.path.join(app.config['UPLOAD_FOLDER'], filename))

    try:
        file.save(temp_image_path)
        app.logger.info(f"Memproses gambar: {filename}")

        result = text_recognition_pipeline(temp_image_path)
        
        app.logger.info(f"[BERHASIL] Ekstraksi {filename} selesai.")
        return jsonify(result)

    except Exception as e:
        app.logger.error(f"[!] Terjadi exception: {str(e)}")
        return jsonify({"status": "error", "message": str(e)}), 500

    finally:
        # PEMBERSIHAN MEMORI (TAPI JANGAN HAPUS ANNOTATED)
        
        # 1. Hapus gambar asli saja demi privasi
        if temp_image_path and os.path.exists(temp_image_path):
            try:
                os.remove(temp_image_path)
            except OSError:
                pass

        # 2. Paksa buang memori dari CPU dan PyTorch
        if torch.cuda.is_available():
            torch.cuda.empty_cache()
            
        if file:
            del file 
        gc.collect() 

if __name__ == '__main__':
    if HAS_WAITRESS:
        app.logger.info("Membangun Waitress WSGI Server di port 5000...")
        serve(app, host='0.0.0.0', port=5000, threads=1)
    else:
        app.logger.warning("Waitress tidak ditemukan! Menjalankan Flask Built-in.")
        app.run(host='0.0.0.0', port=5000, threaded=False, debug=False)