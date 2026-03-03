from flask import Flask, request, jsonify
import os
import gc  # <--- [PENTING 1] Impor Garbage Collector
from werkzeug.utils import secure_filename

# Impor Metode Mayor (Pipeline)
from core.ocr_service import text_recognition_pipeline

app = Flask(__name__)
UPLOAD_FOLDER = 'temp_uploads' 
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

os.makedirs(UPLOAD_FOLDER, exist_ok=True)

@app.route('/ocr', methods=['POST'])
def handle_ocr_request():
    if 'file' not in request.files:
        return jsonify({"status": "error", "message": "No file part"}), 400
        
    file = request.files['file']
    
    if file.filename == '':
        return jsonify({"status": "error", "message": "No selected file"}), 400

    if file:
        filename = secure_filename(file.filename)
        # Gunakan absolute path biar aman
        temp_image_path = os.path.abspath(os.path.join(app.config['UPLOAD_FOLDER'], filename))
        
        try:
            # 1. Simpan file sementara
            file.save(temp_image_path)
            
            # 2. Jalankan Metode Mayor (Pipeline)
            result = text_recognition_pipeline(temp_image_path)
            
            # --- [PENTING 2] JURUS MELEPAS KUNCI FILE ---
            # Kita harus hapus objek 'file' dari memori Flask
            # dan paksa Python bersih-bersih (Garbage Collection)
            # supaya Windows mau ngelepas file lock-nya.
            del file
            gc.collect() 
            
            # 3. Hapus file sementara (Input Mentah)
            # Sekarang os.remove gak bakal ditolak sama Windows
            if os.path.exists(temp_image_path):
                os.remove(temp_image_path)
            
            # 4. Kembalikan hasil JSON
            return jsonify(result)
            
        except Exception as e:
            # Cleanup darurat kalau error
            gc.collect() 
            if os.path.exists(temp_image_path):
                try:
                    os.remove(temp_image_path)
                except:
                    pass # Kalau masih gagal, biarin PHP yang urus nanti
            return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == '__main__':
    # Threaded=False membantu mencegah locking di Windows
    app.run(debug=True, port=5000, threaded=False)