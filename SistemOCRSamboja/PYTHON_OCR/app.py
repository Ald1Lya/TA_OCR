from flask import Flask, request, jsonify
import os
from werkzeug.utils import secure_filename

# Impor Metode Mayor (Pipeline) Anda dari core
from core.ocr_service import text_recognition_pipeline

app = Flask(__name__)
# Folder untuk menyimpan file sementara
UPLOAD_FOLDER = 'temp_uploads' 
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

# Pastikan folder temp_uploads ada
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
        temp_image_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
        
        try:
            # 1. Simpan file sementara
            file.save(temp_image_path)
            
            # 2. Jalankan Metode Mayor (Pipeline)
            result = text_recognition_pipeline(temp_image_path)
            
            # 3. Hapus file sementara
            os.remove(temp_image_path)
            
            # 4. Kembalikan hasil JSON
            return jsonify(result)
            
        except Exception as e:
            # Hapus file jika terjadi error
            if os.path.exists(temp_image_path):
                os.remove(temp_image_path)
            return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True, port=5000)