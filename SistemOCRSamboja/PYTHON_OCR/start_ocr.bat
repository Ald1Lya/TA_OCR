@echo off
cd /d "%~dp0"
python app.py > ocr_log.txt 2>&1
