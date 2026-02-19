@echo off
chcp 65001 >nul
echo ========================================
echo    TPJD Üyelik Sistemi Kurulumu
echo ========================================
echo.

echo 1. Node.js kontrol ediliyor...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo HATA: Node.js kurulu değil!
    echo Lütfen https://nodejs.org adresinden Node.js indirin
    echo ve kurulumu tamamladıktan sonra tekrar deneyin.
    pause
    exit /b 1
)

echo 2. Bağımlılıklar yükleniyor...
call npm install

if %errorlevel% neq 0 (
    echo HATA: Bağımlılıklar yüklenirken hata oluştu!
    pause
    exit /b 1
)

echo 3. Uygulama derleniyor...
call npm run build-win

if %errorlevel% neq 0 (
    echo HATA: Derleme sırasında hata oluştu!
    pause
    exit /b 1
)

echo.
echo ========================================
echo    KURULUM BASARILI!
echo ========================================
echo.
echo Uygulama 'dist' klasorunde olusturuldu.
echo TPJD-Uyelik-Sistemi Setup.exe dosyasini calistirarak kurabilirsiniz.
echo.

pause