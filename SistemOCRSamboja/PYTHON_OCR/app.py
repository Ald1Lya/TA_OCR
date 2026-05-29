from flask import Flask, request, jsonify
import os
import torch
from werkzeug.utils import secure_filename

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

    filename         = secure_filename(file.filename)
    temp_image_path  = os.path.abspath(os.path.join(app.config['UPLOAD_FOLDER'], filename))

    try:
        file.save(temp_image_path)

        result = text_recognition_pipeline(temp_image_path)

        if torch.cuda.is_available():
            torch.cuda.empty_cache()

        del file

        if os.path.exists(temp_image_path):
            try:
                os.remove(temp_image_path)
            except OSError:
                pass

        return jsonify(result)

    except Exception as e:
        if os.path.exists(temp_image_path):
            try:
                os.remove(temp_image_path)
            except OSError:
                pass

        if torch.cuda.is_available():
            torch.cuda.empty_cache()

        return jsonify({"status": "error", "message": str(e)}), 500


if __name__ == '__main__':
    app.run(debug=False, port=5000, threaded=True)
