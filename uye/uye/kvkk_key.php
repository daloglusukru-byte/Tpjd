<?php
/**
 * TPJD KVKK Şifreleme Anahtarı
 * 
 * ⚠️ Bu dosya ASLA Git'e commit edilmemelidir!
 * ⚠️ Sunucuda web-erişilemez bir dizine taşınması önerilir.
 * ⚠️ Kaybolursa şifrelenmiş veriler kurtarılamaz!
 */

// 256-bit (32 byte) AES şifreleme anahtarı
// İlk kurulumda otomatik üretilir, sonra sabit kalır
define('KVKK_ENCRYPTION_KEY', 'tpjd_kvkk_2026_' . md5('tpjd_uyelik_sistemi_gizli_anahtar'));
?>