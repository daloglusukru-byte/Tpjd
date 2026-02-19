@echo off
chcp 65001 >nul
title TPJD - WhatsApp Sunucu
echo ╔═══════════════════════════════════════════════╗
echo ║     TPJD - WhatsApp Lokal Sunucu             ║
echo ║     http://localhost:3456                     ║
echo ║     Bu pencereyi KAPATMAYIN!                  ║
echo ╚═══════════════════════════════════════════════╝
echo.

cd /d "%~dp0whatsapp-server"

:: Node modülleri yüklü mü kontrol et
if not exist "node_modules" (
    echo [*] Ilk calistirma - bagimliliklar yukleniyor...
    echo [*] Bu islem bir kez yapilacak, lutfen bekleyin...
    echo.
    call npm install
    echo.
    echo [OK] Bagimliliklar yuklendi!
    echo.
)

:: Sunucuyu başlat
echo [*] WhatsApp sunucusu baslatiliyor...
echo [*] QR kodu asagida gorunecek - telefonla okutun
echo.
node server.js

:: Hata durumunda
if errorlevel 1 (
    echo.
    echo [HATA] Sunucu baslatma hatasi!
    echo [*] Node.js yuklu oldugundan emin olun: node --version
    echo [*] Sorun devam ederse: cd whatsapp-server ^&^& npm install
)

pause
