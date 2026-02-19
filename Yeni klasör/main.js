const { app, BrowserWindow, Menu, dialog, shell, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');

// Yedek klasörü oluştur
const backupDir = path.join(__dirname, 'save');
if (!fs.existsSync(backupDir)) {
  fs.mkdirSync(backupDir, { recursive: true });
}

// Yedek alma IPC handler
ipcMain.handle('save-backup', async (event, data, filename) => {
  try {
    const backupPath = path.join(backupDir, filename);
    fs.writeFileSync(backupPath, data);
    return { success: true, path: backupPath };
  } catch (error) {
    return { success: false, error: error.message };
  }
});

// Yedekleri listeleme
ipcMain.handle('list-backups', async () => {
  try {
    const files = fs.readdirSync(backupDir);
    const backups = files.filter(file => file.endsWith('.json') || file.endsWith('.xlsx'));
    return { success: true, backups };
  } catch (error) {
    return { success: false, error: error.message };
  }
});
// Ana pencere
let mainWindow;

function createWindow() {
  // Pencere oluştur
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1200,
    minHeight: 700,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      enableRemoteModule: false,
      webSecurity: false
    },
    icon: path.join(__dirname, 'build/icon.ico'),
    title: 'TPJD Üyelik Yönetim Sistemi v1.0.0',
    show: false
  });

  // HTML dosyasını yükle
  mainWindow.loadFile('TPJD v0.08.html');

  // Menüyü özelleştir
  const template = [
    {
      label: 'Dosya',
      submenu: [
        {
          label: 'Yedek Al',
          click: async () => {
            const { shell } = require('electron');
            shell.openExternal('https://github.com');
          }
        },
        { type: 'separator' },
        {
          label: 'Çıkış',
          accelerator: 'Ctrl+Q',
          click: () => {
            app.quit();
          }
        }
      ]
    },
    {
      label: 'Görünüm',
      submenu: [
        { role: 'reload', label: 'Yenile' },
        { role: 'forceReload', label: 'Zorla Yenile' },
        { role: 'toggleDevTools', label: 'Geliştirici Araçları' },
        { type: 'separator' },
        { role: 'resetZoom', label: 'Yakınlaştırmayı Sıfırla' },
        { role: 'zoomIn', label: 'Yakınlaştır' },
        { role: 'zoomOut', label: 'Uzaklaştır' },
        { type: 'separator' },
        { role: 'togglefullscreen', label: 'Tam Ekran' }
      ]
    },
    {
      label: 'Yardım',
      submenu: [
        {
          label: 'Hakkında',
          click: () => {
            dialog.showMessageBox(mainWindow, {
              type: 'info',
              title: 'TPJD Üyelik Sistemi',
              message: 'TPJD Üyelik Yönetim Sistemi',
              detail: `Versiyon: 1.0.0\n\nTürkiye Petrol Jeologları Derneği\nÜyelik Yönetim Sistemi\n\nBu uygulama Electron ile geliştirilmiştir.`
            });
          }
        }
      ]
    }
  ];

  const menu = Menu.buildFromTemplate(template);
  Menu.setApplicationMenu(menu);

  // Pencere hazır olduğunda göster
  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    
    // Geliştirme modunda developer tools aç
    if (process.env.NODE_ENV === 'development') {
      mainWindow.webContents.openDevTools();
    }
  });

  // Pencere kapatıldığında
  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

// Uygulama hazır olduğunda
app.whenReady().then(createWindow);

// Tüm pencereler kapatıldığında (macOS dışında)
app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

// Uygulama etkinleştirildiğinde (macOS)
app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});

// Çoklu örnek engelleme
app.requestSingleInstanceLock();
app.on('second-instance', () => {
  if (mainWindow) {
    if (mainWindow.isMinimized()) mainWindow.restore();
    mainWindow.focus();
  }
});