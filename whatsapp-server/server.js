/**
 * TPJD WhatsApp Lokal Sunucu
 * ==============================
 * whatsapp-web.js + Express API
 * localhost:3456 üzerinden çalışır
 * 
 * Endpointler:
 *   GET  /          → API bilgisi
 *   GET  /status    → Bağlantı durumu
 *   GET  /qr        → QR kodu (base64 PNG)
 *   POST /send      → Mesaj gönder {number, message}
 *   POST /send-bulk → Toplu mesaj {messages: [{number, message}]}
 */

const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const qrcodeTerminal = require('qrcode-terminal');

const app = express();
app.use(express.json());

// ═══ CORS — tüm isteklere izin ═══
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type');
    if (req.method === 'OPTIONS') return res.sendStatus(200);
    next();
});

// ═══ Durum Değişkenleri ═══
let connectionStatus = 'initializing'; // initializing | qr_waiting | connected | disconnected
let currentQR = null;
let clientReady = false;
let clientInfo = null;

// ═══ WhatsApp Client ═══
console.log('');
console.log('╔═══════════════════════════════════════════════╗');
console.log('║     TPJD WhatsApp Lokal Sunucu                ║');
console.log('║     http://localhost:3456                      ║');
console.log('║     Bu pencereyi KAPATMAYIN!                   ║');
console.log('╚═══════════════════════════════════════════════╝');
console.log('');
console.log('[*] WhatsApp client baslatiliyor...');

const client = new Client({
    authStrategy: new LocalAuth({
        dataPath: './.wwebjs_auth'
    }),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--disable-gpu'
        ]
    }
});

// QR Kodu geldiğinde
client.on('qr', async (qr) => {
    connectionStatus = 'qr_waiting';
    currentQR = qr;

    console.log('');
    console.log('========================================');
    console.log('  QR KODU ASAGIDA - TELEFONLA OKUTUN');
    console.log('  WhatsApp > Bagli Cihazlar > Cihaz Bagla');
    console.log('========================================');
    console.log('');

    qrcodeTerminal.generate(qr, { small: true });

    console.log('');
    console.log('[*] QR kodu TPJD panelinden de gorulebilir');
    console.log('    Ayarlar > WhatsApp > QR Kodu Goster');
    console.log('');
});

// Bağlandığında
client.on('ready', () => {
    connectionStatus = 'connected';
    clientReady = true;
    currentQR = null;
    clientInfo = client.info;

    console.log('');
    console.log('==========================================');
    console.log('  WHATSAPP BAGLANDI!');
    console.log(`  Numara: ${client.info?.wid?.user || 'Bilinmiyor'}`);
    console.log(`  Platform: ${client.info?.platform || 'Bilinmiyor'}`);
    console.log('==========================================');
    console.log('');
    console.log('[OK] Mesaj gondermeye hazir.');
    console.log('[*] TPJD panelinden mesaj gonderebilirsiniz.');
    console.log('');
});

// Bağlantı koptuğunda
client.on('disconnected', (reason) => {
    connectionStatus = 'disconnected';
    clientReady = false;
    clientInfo = null;
    console.log(`[!] WhatsApp baglantisi koptu: ${reason}`);
    console.log('[*] Yeniden baglanmak icin programi tekrar baslatin.');
});

// Kimlik doğrulama başarılı
client.on('authenticated', () => {
    console.log('[OK] WhatsApp oturumu dogrulandi (kayitli session kullaniliyor)');
});

// Kimlik doğrulama hatası
client.on('auth_failure', (msg) => {
    connectionStatus = 'disconnected';
    console.log(`[HATA] WhatsApp kimlik dogrulama hatasi: ${msg}`);
    console.log('[*] .wwebjs_auth klasorunu silip tekrar deneyin.');
});

// ═══════════════════════════════════════
// API ENDPOINT'LERİ
// ═══════════════════════════════════════

// Ana sayfa
app.get('/', (req, res) => {
    res.json({
        name: 'TPJD WhatsApp Lokal Sunucu',
        version: '1.0.0',
        status: connectionStatus,
        endpoints: {
            'GET /status': 'Baglanti durumu',
            'GET /qr': 'QR kodu (base64 PNG)',
            'POST /send': 'Mesaj gonder {number, message}',
            'POST /send-bulk': 'Toplu mesaj {messages: [{number, message}]}'
        }
    });
});

// Durum kontrolü
app.get('/status', (req, res) => {
    res.json({
        status: connectionStatus,
        authenticated: clientReady,
        ready: clientReady,
        info: clientInfo ? {
            pushname: clientInfo.pushname || null,
            phone: clientInfo.wid?.user || null,
            platform: clientInfo.platform || null
        } : null,
        timestamp: new Date().toISOString()
    });
});

// QR kodu (Base64 PNG)
app.get('/qr', async (req, res) => {
    if (connectionStatus === 'connected' || clientReady) {
        return res.json({
            status: 'connected',
            authenticated: true,
            message: 'Zaten bagli, QR gerekmiyor',
            phone: clientInfo?.wid?.user || null
        });
    }

    if (!currentQR) {
        // QR yok ve bağlı değil — client'ı yeniden başlatmayı dene
        if (connectionStatus === 'disconnected' || connectionStatus === 'initializing') {
            try {
                console.log('[*] QR istendi ama yok — client yeniden baslatiliyor...');
                connectionStatus = 'initializing';
                client.initialize().catch(err => {
                    console.log(`[HATA] Yeniden baslatilamadi: ${err.message}`);
                });
            } catch (e) {
                console.log('[HATA] Client restart hatasi:', e.message);
            }
        }
        return res.json({
            status: connectionStatus,
            authenticated: false,
            message: 'QR kodu olusturuluyor, 5 saniye sonra tekrar deneyin...'
        });
    }

    try {
        const qrBase64 = await qrcode.toDataURL(currentQR, { width: 300 });
        res.json({
            status: 'qr_waiting',
            authenticated: false,
            qr: qrBase64,
            message: 'QR kodu telefonla okutun'
        });
    } catch (err) {
        res.status(500).json({ error: 'QR kodu olusturulamadi' });
    }
});

// Tekli mesaj gönder
app.post('/send', async (req, res) => {
    const { number, message } = req.body;

    if (!number || !message) {
        return res.status(400).json({
            success: false,
            error: 'number ve message alanlari gerekli'
        });
    }

    if (!clientReady) {
        return res.status(503).json({
            success: false,
            error: 'WhatsApp bagli degil',
            status: connectionStatus
        });
    }

    try {
        // Numara formatla: 905xxxxxxxxx -> 905xxxxxxxxx@c.us
        let cleanNumber = number.replace(/[^0-9]/g, '');

        // Türkiye numarası düzeltme
        if (cleanNumber.startsWith('0')) {
            cleanNumber = '90' + cleanNumber.substring(1);
        } else if (!cleanNumber.startsWith('90') && cleanNumber.length === 10) {
            cleanNumber = '90' + cleanNumber;
        }

        const chatId = cleanNumber + '@c.us';

        // Numara kayıtlı mı kontrol et
        const isRegistered = await client.isRegisteredUser(chatId);
        if (!isRegistered) {
            return res.json({
                success: false,
                error: `${cleanNumber} numarasi WhatsApp kullanmiyor`,
                number: cleanNumber
            });
        }

        // Mesaj gönder
        const result = await client.sendMessage(chatId, message);

        console.log(`[OK] Mesaj gonderildi: ${cleanNumber} (${message.substring(0, 50)}...)`);

        res.json({
            success: true,
            message: 'Mesaj gonderildi',
            number: cleanNumber,
            messageId: result.id?._serialized || null,
            timestamp: result.timestamp || null
        });

    } catch (err) {
        console.log(`[HATA] Mesaj gonderilemedi: ${err.message}`);
        res.status(500).json({
            success: false,
            error: `Mesaj gonderilemedi: ${err.message}`
        });
    }
});

// Toplu mesaj gönder
app.post('/send-bulk', async (req, res) => {
    const { messages } = req.body;

    if (!Array.isArray(messages) || messages.length === 0) {
        return res.status(400).json({
            success: false,
            error: 'messages dizisi gerekli [{number, message}]'
        });
    }

    if (!clientReady) {
        return res.status(503).json({
            success: false,
            error: 'WhatsApp bagli degil',
            status: connectionStatus
        });
    }

    const results = [];
    let successCount = 0;
    let errorCount = 0;

    for (const msg of messages) {
        try {
            let cleanNumber = (msg.number || '').replace(/[^0-9]/g, '');
            if (cleanNumber.startsWith('0')) {
                cleanNumber = '90' + cleanNumber.substring(1);
            } else if (!cleanNumber.startsWith('90') && cleanNumber.length === 10) {
                cleanNumber = '90' + cleanNumber;
            }

            const chatId = cleanNumber + '@c.us';
            const isRegistered = await client.isRegisteredUser(chatId);

            if (!isRegistered) {
                results.push({ number: cleanNumber, status: 'error', detail: 'WhatsApp yok' });
                errorCount++;
                continue;
            }

            await client.sendMessage(chatId, msg.message || '');
            results.push({ number: cleanNumber, status: 'success' });
            successCount++;

            console.log(`[OK] Toplu: ${cleanNumber} gonderildi`);

            // Rate limiting - 1 saniye bekle (WhatsApp spam koruması)
            await new Promise(r => setTimeout(r, 1000));

        } catch (err) {
            results.push({ number: msg.number, status: 'error', detail: err.message });
            errorCount++;
        }
    }

    console.log(`[*] Toplu gonderim tamamlandi: ${successCount} basarili, ${errorCount} hata`);

    res.json({
        success: true,
        total: messages.length,
        sent: successCount,
        failed: errorCount,
        results
    });
});

// ═══ Sunucuyu Başlat ═══
const PORT = 3456;
app.listen(PORT, () => {
    console.log(`[OK] API sunucusu calisiyor: http://localhost:${PORT}`);
    console.log('[*] WhatsApp baglantiyi bekliyor...');
    console.log('');

    // WhatsApp client'ı başlat
    client.initialize().catch(err => {
        console.log(`[HATA] WhatsApp baslatilamadi: ${err.message}`);
        console.log('[*] Chromium/Chrome yuklu oldugundan emin olun.');
    });
});
