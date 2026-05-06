# Smart Trade Robot - Mobile Trading System

A complete mobile trading robot system that connects to a web-based copy trading platform and executes trades on MetaTrader 4/5 platforms.

## System Overview

This system consists of four main components:

1. **Mobile App** (React Native) - Runs on Android/iOS, receives signals, shows trade status
2. **Backend API** (PHP) - Handles license validation and signal retrieval
3. **Expert Advisor** (MQL4/MQL5) - Executes trades on MT4/MT5 platforms
4. **Signal File System** - Cross-platform communication mechanism

## Features

- Real-time signal delivery from admin dashboard
- License key authentication
- Automatic trade execution on MT4/MT5
- Cross-broker compatibility
- Background operation
- Trade status monitoring
- Support for all MT4/MT5 brokers

## Architecture

```
Mobile App (React Native) 
        ↓ (WebSocket/API)
Backend API (PHP/MySQL)
        ↓ (File System)
Expert Advisor (MT4/MT5)
        ↓
Broker Server
```

## Prerequisites

### Mobile App
- Node.js (v14+)
- React Native CLI
- Android Studio / Xcode
- @react-native-async-storage/async-storage
- react-native-websocket

### Backend
- PHP (v7.4+)
- MySQL (v5.7+)
- Web server (Apache/Nginx)

### Expert Advisor
- MetaTrader 4 or MetaTrader 5 platform
- MQL4/MQL5 compiler

## Installation & Setup

### 1. Database Setup

Create the database and tables:

```sql
CREATE DATABASE trading_platform;
USE trading_platform;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    mentor_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE licence_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(100) NOT NULL UNIQUE,
    user_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE trade_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT,
    pair VARCHAR(10) NOT NULL,
    direction ENUM('BUY', 'SELL') NOT NULL,
    tp DECIMAL(10,5) NOT NULL,
    sl DECIMAL(10,5) NOT NULL,
    lot_size DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES users(id)
);

-- Insert sample data
INSERT INTO users (username, email, password, mentor_id) VALUES 
('admin', 'admin@example.com', 'hashed_password_here', 1),
('trader1', 'trader1@example.com', 'hashed_password_here', 2);

INSERT INTO licence_keys (key, user_id, is_active, expires_at) VALUES 
('LICENSE-KEY-12345-ABCDE', 1, TRUE, DATE_ADD(NOW(), INTERVAL 1 YEAR)),
('LICENSE-KEY-67890-FGHIJ', 2, TRUE, DATE_ADD(NOW(), INTERVAL 1 YEAR));
```

### 2. Backend Setup

1. Copy the `backend` folder to your web server
2. Update database credentials in `backend/api/index.php`:
   ```php
   $host = 'localhost';
   $dbname = 'trading_platform';
   $username = 'root';
   $password = '';
   ```
3. Ensure the API is accessible at `http://your-domain.com/backend/api/`

### 3. Mobile App Setup

1. Install dependencies:
   ```bash
   cd mobile-app
   npm install
   ```
2. Install required pods (iOS):
   ```bash
   cd ios && pod install
   ```
3. Configure API endpoint in mobile app services:
   - Edit `mobile-app/src/services/SignalService.js` to point to your backend
   - Edit `mobile-app/src/services/AuthService.js` to point to your backend
4. Run the app:
   ```bash
   # Android
   npx react-native run-android
   
   # iOS
   npx react-native run-ios
   ```

### 4. Expert Advisor Setup

1. Copy the appropriate EA file to your MT4/MT5 platform:
   - For MT5: Copy `SmartTradeRobotEA.mq5` to `MQL5/Experts/`
   - For MT4: Copy `SmartTradeRobotEA.mq4` to `MQL4/Experts/`
2. Compile the EA in MetaEditor
3. Attach the EA to a chart:
   - Set your license key in the inputs
   - Ensure "Allow live trading" is checked
   - Ensure "Allow DLL imports" is checked (if using file monitoring)
4. The EA will monitor `Signals.txt` in the platform's MQL4/Files or MQL5/Files directory

### 5. Signal Delivery System

The system uses a file-based approach for cross-platform compatibility:

1. Mobile app writes signals to a shared location (in production, this would be a secure server endpoint)
2. EA reads signals from `Signals.txt` file
3. Format: `TIMESTAMP|PAIR|DIRECTION|LOT|TP|SL`
   Example: `2026.04.29 10:30:00|EURUSD|BUY|0.1|1.0850|1.0750`

## Configuration

### Mobile App
- License key storage: Securely stored using AsyncStorage
- Signal polling interval: 10 seconds (configurable in SignalService.js)
- Trade simulation: For demonstration only - replace with actual EA communication

### Backend
- License validation: Checks against `licence_keys` table
- Signal retrieval: Gets signals from last hour for user's mentor
- CORS: Configured to allow all origins (restrict in production)

### Expert Advisor
- License key: Set in EA inputs
- Lot size: Default 0.1 (configurable)
- Magic number: 12345 (configurable)
- Slippage: 3 points (configurable)
- Signal file: `Signals.txt` (configurable)
- Check interval: 5 seconds (configurable)

## How It Works

1. **User Authentication**:
   - User enters license key in mobile app
   - App validates key with backend API
   - On success, app stores token and starts signal listener

2. **Signal Reception**:
   - Mobile app receives signals via WebSocket/polling
   - Signals are stored locally and displayed in UI
   - App forwards signals to EA via file system

3. **Trade Execution**:
   - EA monitors `Signals.txt` for new signals
   - When new signal detected, EA validates license
   - EA executes trade on MT4/MT5 platform
   - EA tracks trade status (optional enhancement)

4. **Trade Monitoring**:
   - Mobile app shows active signals and trade history
   - In production, EA would send trade updates back to mobile app

## Security Considerations

1. **License Protection**:
   - Use hardware-bound licenses in production
   - Implement proper cryptographic validation
   - Use HTTPS for all API communications

2. **Signal Security**:
   - Signals should be encrypted in transit
   - Use authenticated endpoints
   - Implement rate limiting

3. **Trade Security**:
   - EAs should validate signal integrity
   - Implement maximum lot size limits
   - Add confirmation prompts for large trades

## Troubleshooting

### Mobile App Issues
- **"Unable to resolve module"**: Run `npm install` again
- **"Failed to install pod"**: Run `pod install --repo-update` in ios directory
- **App not connecting to backend**: Verify API endpoint and CORS settings

### Backend Issues
- **Database connection failed**: Check MySQL credentials and server status
- **API returning 404**: Verify URL path and server configuration
- **License validation failing**: Check license key format and database records

### EA Issues
- **EA not trading**: Check license key format and validation function
- **Orders failing**: Check broker connection and trading permissions
- **Signal file not found**: Verify file path and permissions
- **EA not detecting signals**: Check file format and timestamp parsing

## Production Recommendations

1. **Replace simulated components**:
   - Replace signal simulation with real WebSocket connection
   - Replace trade simulation with actual EA communication (file system, local server, or deep links)
   - Implement proper license validation with encryption

2. **Enhance security**:
   - Use HTTPS everywhere
   - Implement JWT or secure session management
   - Add input validation and sanitization

3. **Improve reliability**:
   - Add heartbeat/connection monitoring
   - Implement signal acknowledgment system
   - Add trade confirmation from EA to mobile app

4. **Monitoring and logging**:
   - Add comprehensive logging in all components
   - Implement error reporting and alerts
   - Add performance monitoring

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please contact: support@smarttraderobot.example.com