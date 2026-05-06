import AsyncStorage from '@react-native-async-storage/async-storage';
import { WebSocket } from 'react-native-websocket';
import { AuthService } from './AuthService';

// CHANGE THIS TO YOUR ACTUAL SERVER IP
const WS_BASE_URL = 'ws://YOUR_SERVER_IP:8080'; // WebSocket server
const API_BASE_URL = 'http://YOUR_SERVER_IP/ea smart trade/backend'; // HTTP fallback

export class SignalService {
  static ws = null;
  static listener = null;
  static mentorId = null;
  static reconnectAttempts = 0;
  static maxReconnectAttempts = 5;
  static pollingInterval = null;

  static async startSignalListener(callback) {
    this.listener = callback;
    
    // Try WebSocket first
    await this.connectWebSocket();
    
    // Also poll for signals periodically as backup
    this.startPolling();
  }

  static async connectWebSocket() {
    try {
      const licenseKey = await AuthService.getLicenseKey();
      if (!licenseKey) {
        console.error('No license key found');
        return;
      }

      const wsUrl = `${WS_BASE_URL}?licenseKey=${licenseKey}`;
      
      this.ws = new WebSocket(wsUrl);

      this.ws.onOpen = () => {
        console.log('WebSocket connected ✓');
        this.reconnectAttempts = 0;
      };

      this.ws.onMessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleMessage(data);
        } catch (error) {
          console.error('Failed to parse WebSocket message:', error);
        }
      };

      this.ws.onError = (error) => {
        console.error('WebSocket error:', error);
      };

      this.ws.onClose = () => {
        console.log('WebSocket disconnected');
        this.handleReconnect();
      };

    } catch (error) {
      console.error('WebSocket connection failed, using HTTP polling:', error);
      this.handleReconnect();
    }
  }

  static handleMessage(data) {
    switch (data.type) {
      case 'welcome':
        console.log('✅ Connected to signal server');
        break;
        
      case 'auth_success':
        this.mentorId = data.mentor_id;
        console.log('✅ Authenticated as mentor:', this.mentorId);
        break;
        
      case 'auth_failed':
        console.error('❌ Authentication failed:', data.message);
        break;
        
      case 'new_signal':
        if (this.listener) {
          this.listener(data.signal);
        }
        break;
        
      case 'pong':
        // Heartbeat response
        break;
    }
  }

  static sendMessage(message) {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify(message));
    }
  }

  static setupPing() {
    setInterval(() => {
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.sendMessage({ type: 'ping' });
      }
    }, 30000);
  }

  static handleReconnect() {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
      console.log(`🔄 Reconnecting in ${delay/1000}s (attempt ${this.reconnectAttempts})`);
      
      setTimeout(() => {
        this.connectWebSocket();
      }, delay);
    } else {
      console.error('⚠️ Max WebSocket reconnection attempts. Switching to HTTP polling.');
      this.startPolling();
    }
  }

  static async startPolling() {
    if (this.pollingInterval) return; // Already polling
    
    console.log('📡 Starting HTTP polling for signals...');
    
    // Poll every 3 seconds
    this.pollingInterval = setInterval(async () => {
      try {
        const licenseKey = await AuthService.getLicenseKey();
        if (!licenseKey) return;
        
        const response = await fetch(`${API_BASE_URL}/get_signals.php?licenseKey=${encodeURIComponent(licenseKey)}`);
        const data = await response.json();
        
        if (data.success && data.signals && data.signals.length > 0) {
          // Send only new signals to listener
          const lastProcessedId = await this.getLastProcessedId();
          
          data.signals.forEach(signal => {
            if (signal.id > lastProcessedId) {
              this.listener(signal);
              this.saveLastProcessedId(signal.id);
            }
          });
        }
      } catch (error) {
        console.error('Polling error:', error);
      }
    }, 3000);
  }

  static async getLastProcessedId() {
    const id = await AsyncStorage.getItem('last_signal_id');
    return id ? parseInt(id) : 0;
  }

  static async saveLastProcessedId(id) {
    await AsyncStorage.setItem('last_signal_id', id.toString());
  }

  static stopSignalListener() {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
      this.pollingInterval = null;
    }
    this.listener = null;
  }
}

  static async connect() {
    try {
      const licenseKey = await AuthService.getLicenseKey();
      if (!licenseKey) {
        console.error('No license key found');
        return;
      }

      const wsUrl = `${WS_BASE_URL}?licenseKey=${licenseKey}`;
      
      this.ws = new WebSocket(wsUrl);

      this.ws.onOpen = () => {
        console.log('WebSocket connected');
        this.reconnectAttempts = 0;
        
        // Authenticate after connection
        this.sendMessage({
          type: 'auth',
          licenseKey: licenseKey
        });
      };

      this.ws.onMessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleMessage(data);
        } catch (error) {
          console.error('Failed to parse WebSocket message:', error);
        }
      };

      this.ws.onError = (error) => {
        console.error('WebSocket error:', error);
      };

      this.ws.onClose = () => {
        console.log('WebSocket disconnected');
        this.handleReconnect();
      };

    } catch (error) {
      console.error('Failed to connect WebSocket:', error);
      this.handleReconnect();
    }
  }

  static handleMessage(data) {
    switch (data.type) {
      case 'welcome':
        console.log('Connected to signal server:', data.message);
        break;
        
      case 'auth_success':
        this.mentorId = data.mentor_id;
        console.log('Authenticated as mentor:', this.mentorId);
        break;
        
      case 'auth_failed':
        console.error('Authentication failed:', data.message);
        this.showAuthError();
        break;
        
      case 'new_signal':
        if (this.listener) {
          this.listener(data.signal);
        }
        break;
        
      case 'pong':
        // Heartbeat response
        break;
        
      default:
        console.log('Unknown message type:', data.type);
    }
  }

  static sendMessage(message) {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify(message));
    }
  }

  static setupPing() {
    setInterval(() => {
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.sendMessage({ type: 'ping' });
      }
    }, 30000);
  }

  static handleReconnect() {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
      console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
      
      setTimeout(() => {
        this.connect();
      }, delay);
    } else {
      console.error('Max reconnection attempts reached');
      this.showConnectionError();
    }
  }

  static stopSignalListener() {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    this.listener = null;
  }

  static showAuthError() {
    // Could trigger navigation to login screen
    console.log('Show auth error UI');
  }

  static showConnectionError() {
    // Could show error toast
    console.log('Show connection error UI');
  }
}

  static connect() {
    const licenseKey = await AuthService.getLicenseKey();
    if (!licenseKey) {
      console.error('No license key found');
      return;
    }

    const wsUrl = `${WS_BASE_URL}?licenseKey=${licenseKey}`;
    
    try {
      this.ws = new WebSocket(wsUrl);

      this.ws.onOpen = () => {
        console.log('WebSocket connected');
        this.reconnectAttempts = 0;
        
        // Authenticate after connection
        this.sendMessage({
          type: 'auth',
          licenseKey: licenseKey
        });
      };

      this.ws.onMessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleMessage(data);
        } catch (error) {
          console.error('Failed to parse WebSocket message:', error);
        }
      };

      this.ws.onError = (error) => {
        console.error('WebSocket error:', error);
      };

      this.ws.onClose = () => {
        console.log('WebSocket disconnected');
        this.handleReconnect();
      };

    } catch (error) {
      console.error('Failed to connect WebSocket:', error);
      this.handleReconnect();
    }
  }

  static handleMessage(data) {
    switch (data.type) {
      case 'welcome':
        console.log('Connected to signal server:', data.message);
        break;
        
      case 'auth_success':
        this.mentorId = data.mentor_id;
        console.log('Authenticated as mentor:', this.mentorId);
        break;
        
      case 'auth_failed':
        console.error('Authentication failed:', data.message);
        this.showAuthError();
        break;
        
      case 'new_signal':
        if (this.listener) {
          this.listener(data.signal);
        }
        break;
        
      case 'pong':
        // Heartbeat response
        break;
        
      default:
        console.log('Unknown message type:', data.type);
    }
  }

  static sendMessage(message) {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify(message));
    }
  }

  static setupPing() {
    setInterval(() => {
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.sendMessage({ type: 'ping' });
      }
    }, 30000);
  }

  static handleReconnect() {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
      console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
      
      setTimeout(() => {
        this.connect();
      }, delay);
    } else {
      console.error('Max reconnection attempts reached');
      this.showConnectionError();
    }
  }

  static stopSignalListener() {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    this.listener = null;
  }

  static showAuthError() {
    // Could trigger navigation to login screen
    console.log('Show auth error UI');
  }

  static showConnectionError() {
    // Could show error toast
    console.log('Show connection error UI');
  }
}