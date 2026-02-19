<?php die('⛔ Setup devre dışı. Doğrudan erişim engellendi. Bu dosya referans için saklanmaktadır.'); ?>
<?php
// Database Setup Script — config.php'den bilgileri alır
header('Content-Type: text/html; charset=utf-8');

// config.php'deki DB bilgilerini kullan (ama header'ları override et)
$servername = "localhost";

// ── Hostinger için bilgiler (config.php ile aynı olmalı) ──
// Bu bilgileri hosting panelinden aldığınız değerlerle doldurun
$configFile = __DIR__ . '/api/../config.php';
if (file_exists(__DIR__ . '/config.php')) {
    // config.php'den oku ama çalıştırma (header çakışması olmasın)
    $configContent = file_get_contents(__DIR__ . '/config.php');
    preg_match('/\$username\s*=\s*"([^"]+)"/', $configContent, $m1);
    preg_match('/\$password\s*=\s*"([^"]+)"/', $configContent, $m2);
    preg_match('/\$dbname\s*=\s*"([^"]+)"/', $configContent, $m3);
    $username = $m1[1] ?? 'root';
    $password = $m2[1] ?? '';
    $dbname = $m3[1] ?? 'tpjd';
} else {
    $username = "root";
    $password = "";
    $dbname = "tpjd";
}

echo "<h2>🔧 TPJD Veritabanı Kurulumu</h2>";
echo "<p>DB: <b>$dbname</b> | User: <b>$username</b> | Host: <b>$servername</b></p>";

// Create connection (directly to database since Hostinger already created it)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("❌ Bağlantı hatası: " . $conn->connect_error);
}

echo "✅ Veritabanı bağlantısı başarılı.<br>";

// Create tables
$tables = [
    // Members table
    "CREATE TABLE IF NOT EXISTS members (
        id VARCHAR(50) PRIMARY KEY,
        memberNo VARCHAR(20) UNIQUE NOT NULL,
        firstName VARCHAR(100) NOT NULL,
        lastName VARCHAR(100) NOT NULL,
        tcNo VARCHAR(11) UNIQUE NOT NULL,
        gender VARCHAR(20),
        birthDate DATE,
        bloodType VARCHAR(10),
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20) NOT NULL,
        phoneHome VARCHAR(20),
        maritalStatus VARCHAR(20),
        city VARCHAR(50),
        district VARCHAR(50),
        neighborhood VARCHAR(50),
        address TEXT,
        fatherName VARCHAR(100),
        motherName VARCHAR(100),
        graduation VARCHAR(200),
        graduationYear INT,
        employmentStatus VARCHAR(50),
        workplace VARCHAR(200),
        position VARCHAR(100),
        birthCity VARCHAR(50),
        birthDistrict VARCHAR(50),
        volumeNo VARCHAR(50),
        familyNo VARCHAR(50),
        lineNo VARCHAR(50),
        notes TEXT,
        membershipType VARCHAR(50) NOT NULL,
        aidatAktif BOOLEAN DEFAULT TRUE,
        membershipStatus VARCHAR(20) DEFAULT 'aktif',
        exitDate DATE,
        exitReasonType VARCHAR(100),
        exitReason TEXT,
        registrationDate DATE,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_memberNo (memberNo),
        INDEX idx_email (email),
        INDEX idx_tcNo (tcNo),
        INDEX idx_membershipStatus (membershipStatus),
        INDEX idx_aidatAktif (aidatAktif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Payments table
    "CREATE TABLE IF NOT EXISTS payments (
        id VARCHAR(50) PRIMARY KEY,
        memberId VARCHAR(50) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        type VARCHAR(50) NOT NULL,
        year INT NOT NULL,
        date DATE NOT NULL,
        receiptNo VARCHAR(50),
        description TEXT,
        status VARCHAR(20) NOT NULL,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (memberId) REFERENCES members(id) ON DELETE CASCADE,
        INDEX idx_memberId (memberId),
        INDEX idx_year (year),
        INDEX idx_type (type),
        INDEX idx_status (status),
        INDEX idx_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Notifications table
    "CREATE TABLE IF NOT EXISTS notifications (
        id VARCHAR(50) PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        recipients JSON,
        priority VARCHAR(20) DEFAULT 'normal',
        sentAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sentAt (sentAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Settings table
    "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value JSON,
        updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Agent Logs table (v0.09)
    "CREATE TABLE IF NOT EXISTS agent_logs (
        id VARCHAR(50) PRIMARY KEY,
        agent_name VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        channel VARCHAR(20) DEFAULT '',
        recipient_id VARCHAR(50) DEFAULT '',
        recipient_name VARCHAR(200) DEFAULT '',
        details TEXT,
        status VARCHAR(20) DEFAULT 'success',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_agent_name (agent_name),
        INDEX idx_recipient_id (recipient_id),
        INDEX idx_created_at (created_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ Tablo başarıyla oluşturuldu.<br>";
    } else {
        echo "❌ Tablo oluşturma hatası: " . $conn->error . "<br>";
    }
}

// Insert default settings
$settings_sql = "INSERT INTO settings (setting_key, setting_value) VALUES 
('aidat_settings', '{\"Asil Üye\": 100, \"Asil Onursal\": 90, \"Öğrenci Üye\": 0, \"Fahri Üye\": 0, \"Fahri Onursal\": 0}'),
('admin_username', '\"admin\"'),
('admin_password', '\"admin123\"')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

if ($conn->query($settings_sql) === TRUE) {
    echo "✅ Varsayılan ayarlar başarıyla eklendi.<br>";
} else {
    echo "❌ Ayarlar eklenirken hata: " . $conn->error . "<br>";
}

$conn->close();
echo "<br><strong>✅ Veritabanı kurulumu tamamlandı!</strong>";
?>