from flask import Flask, request, jsonify
import os
import torch  # [FIX 1]: WAJIB IMPORT TORCH DI SINI
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
        temp_image_path = os.path.abspath(os.path.join(app.config['UPLOAD_FOLDER'], filename))
        
        try:
            # 1. Simpan file sementara
            file.save(temp_image_path)
            
            # 2. Jalankan Metode Mayor (Pipeline)
            result = text_recognition_pipeline(temp_image_path)
            
            # [FIX 2]: KURAS VRAM GPU SETELAH SELESAI MASAK!
            if torch.cuda.is_available():
                torch.cuda.empty_cache() 
            
            # Hapus objek 'file' dari memori Flask
            del file
            
            # 3. Hapus file sementara (Input Mentah) secara aman
            if os.path.exists(temp_image_path):
                try:
                    os.remove(temp_image_path)
                except:
                    pass 
            
            # 4. Kembalikan hasil JSON
            return jsonify(result)
            
        except Exception as e:
            if os.path.exists(temp_image_path):
                try:
                    os.remove(temp_image_path)
                except:
                    pass 
            
            # Kuras juga kalau kebetulan error di tengah jalan
            if torch.cuda.is_available():
                torch.cuda.empty_cache()
                
            return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True, port=5000, threaded=False)