# 🚀 MOBILE TRADING ROBOT - QUICK SETUP GUIDE

## System Architecture
```
Admin Dashboard (Browser)
        ↓ Sends signal via webhook
Backend Webhook (PHP)
        ↓ Writes to file + DB
Signal Dispatcher (PHP daemon)
        ↓ Pushes to WebSocket
WebSocket Server (Port 8080)
        ↓ Real-time push
Mobile App (React Native)
        ↓ Reads Signals.txt
MT4/MT5 EA (Expert Advisor)
        ↓ Executes on broker
```

---

## 📦 Files Created

```
mobile-app/              React Native mobile app (Android & iOS)
backend/
├── api/
│   └── index.php        Unified API (license validation + signal fetching)
├── webhook.php          Receives signals from admin dashboard
├── get_signals.php      Mobile app polling endpoint
├── signal_dispatcher.php Continuously pushes signals to WebSocket
├── websocket_server.php Real-time WebSocket server (port 8080)
└── config/db_connect.php Platform DB connection
ea/
├── SmartTradeRobotEA.mq4   MT4 Expert Advisor
├── SmartTradeRobotEA.mq5   MT5 Expert Advisor
└── Signals.txt             (auto-created by dispatcher)
README.md                  Full documentation
SETUP_GUIDE.md            This file
```

---

## ⚡ Quick Start (5 Steps)

### STEP 1: Database Setup
Your platform already has `ea_smart_trade` database. Create signals table:

```sql
CREATE TABLE IF NOT EXISTS signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT NOT NULL,
    pair VARCHAR(20) NOT NULL,
    direction ENUM('BUY', 'SELL') NOT NULL,
    tp DECIMAL(10,5) NOT NULL,
    sl DECIMAL(10,5) NOT NULL,
    lot DECIMAL(10,3) NOT NULL,
    status ENUM('PENDING', 'FILLED', 'STOPPED', 'CANCELLED') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mentor (mentor_id),
    INDEX idx_status (status)
);
```

### STEP 2: Update Configuration
Edit `backend/config/db_connect.php` with your database credentials:
```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'ea_smart_trade');
```

Edit `mobile-app/src/services/AuthService.js`:
```javascript
const API_BASE_URL = 'http://YOUR_SERVER_IP/ea smart trade/backend';
```

Edit `mobile-app/src/services/SignalService.js`:
```javascript
const WS_BASE_URL = 'ws://YOUR_SERVER_IP:8080';
```

### STEP 3: Start Backend Services
Open terminal and run:

**Terminal 1 - WebSocket Server** (keeps running):
```bash
cd backend
php websocket_server.php
```
✅ Should show: "🚀 Signal WebSocket Server started on port 8080"

**Terminal 2 - Signal Dispatcher** (keeps running):
```bash
cd backend
php signal_dispatcher.php
```
✅ Should show: "🚀 Signal Dispatcher Started"

**Terminal 3 - Apache/Nginx** (already running for your platform)

### STEP 4: Build Mobile App
```bash
cd mobile-app

# Install dependencies
npm install

# For Android
npx react-native run-android

# For iOS (Mac only)
cd ios && pod install && cd ..
npx react-native run-ios
```

### STEP 5: Install EA on MT4/MT5
1. Open MetaTrader 4 or 5
2. Press F4 to open MetaEditor
3. File → New → Expert Advisor
4. Copy entire code from `ea/SmartTradeRobotEA.mq4` (MT4) or `.mq5` (MT5)
5. Compile (F7)
6. Drag EA onto any chart
7. In EA inputs, set:
   - `Signal File Path`: `[Your MT4/5 Data Folder]/MQL5/Files/Signals.txt`
   - `License Key`: Your admin license key (EAV-XXXX-XXX-XXXX)
   - `Check Interval`: 3 seconds

---

## 🧪 Testing

### Test 1: Send a Signal from Admin Dashboard
1. Login to admin dashboard: `index dashboard.html`
2. Go to "Copy Trade" tab
3. Select pair: EURUSD
4. Direction: BUY
5. TP: 1.0850, SL: 1.0800, Lot: 0.5
6. Click "SEND SIGNAL"

✅ Expected:
- Mobile app receives signal instantly (green notification)
- Signal appears in "Active Signals" list
- EA on MT4/MT5 places trade automatically
- Trade shows in "Recent Trades" with simulated profit

### Test 2: Verify Platform Isolation
1. Sign out and sign in with a different admin account
2. Check that only that admin's signals appear
3. Signals are filtered by `mentor_id`

---

## 🔧 Troubleshooting

**Mobile app won't connect to WebSocket:**
1. Check phone and server on same WiFi/network
2. Update `WS_BASE_URL` in SignalService.js with your server's IP address
3. Ensure port 8080 is open (firewall: `sudo ufw allow 8080`)
4. Test: `telnet YOUR_SERVER_IP 8080` from phone browser (use IP:port)

**WebSocket server won't start:**
- Port 8080 might be in use. Change in `websocket_server.php`: `$this->port = 8081;`

**Signals not appearing on mobile:**
1. Check webhook is called: `tail -f backend/signals_cache.json`
2. Check dispatcher is running: `ps aux | grep signal_dispatcher`
3. Verify `API_BASE_URL` in app points to correct server URL

**EA not executing trades:**
1. Check `Signals.txt` file is being created in MT4/5 Files folder
2. Verify license key matches admin's key
3. Check Experts tab in MT4/5 for EA errors
4. Ensure "Allow DLL imports" and "Allow automated trading" are enabled

**Mobile app stuck on loading:**
- Make sure you entered a valid license key format: `EAV-XXXX-XXX-XXXX`
- For demo, any key with that format works

---

## 📱 Customizing Mobile App

**Change app colors:** Edit `mobile-app/App.js` styles object

**Change polling interval:** Edit `SignalService.js` line with `setInterval(..., 3000)` → change to 5000 for 5 sec

**Add push notifications:** Install `@react-native-firebase/messaging` and add FCM

**Build release APK:**
```bash
cd mobile-app
cd android && ./gradlew assembleRelease
```

---

## 🔐 Security (Production)

1. Change `WEBHOOK_KEY` in `backend/webhook.php` to a random string
2. Use HTTPS for all API endpoints
3. Validate all inputs on server side
4. Implement rate limiting on webhook endpoint
5. Add IP whitelisting for admin dashboard → webhook calls
6. Use encrypted signal format (AES) for EA communication

---

## 📊 Monitoring

**Logs:**
- WebSocket server: stdout of `php websocket_server.php`
- Dispatcher: stdout of `php signal_dispatcher.php`
- Mobile app: `adb logcat | grep SignalService` (Android)

**Check active connections:**
```bash
netstat -an | grep :8080
```

**Verify signals in DB:**
```sql
SELECT * FROM signals ORDER BY created_at DESC LIMIT 10;
```

---

## 🎯 Full System Flow

```
1. Admin Dashboard → "Send Signal"
        ↓ AJAX POST
2. backend/webhook.php
        ↓ Validates API key + mentor_id
        ↓ Inserts into MySQL signals table
        ↓ Writes to signals_cache.json
3. backend/signal_dispatcher.php
        ↓ Detects new signal within 1 sec
        ↓ Writes to Signals.txt (for EA)
        ↓ Writes to websocket_push.json
4. backend/websocket_server.php
        ↓ Reads websocket_push.json
        ↓ Pushes JSON to all WebSocket clients
5. Mobile App (React Native)
        ↓ Receives via WebSocket
        ↓ Displays notification + adds to list
        ↓ Calls TradeService.executeTrade()
6. MT4/MT5 EA (SmartTradeRobotEA)
        ↓ Reads Signals.txt every 3 seconds
        ↓ Validates license key in signal
        ↓ Places trade on broker account
        ↓ Updates local status file
```

**Total latency: ~1-3 seconds** from admin click → trade executed on broker

---

**SUPPORT:** Check README.md for detailed documentation