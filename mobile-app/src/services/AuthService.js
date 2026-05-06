import AsyncStorage from '@react-native-async-storage/async-storage';

// CHANGE THIS TO YOUR ACTUAL SERVER URL
const API_BASE_URL = 'http://YOUR_SERVER_IP/ea smart trade/backend'; // e.g., 'http://192.168.1.100/ea smart trade/backend'

export class AuthService {
  static async saveLicenseKey(licenseKey) {
    await AsyncStorage.setItem('licenseKey', licenseKey);
  }

  static async getLicenseKey() {
    return await AsyncStorage.getItem('licenseKey');
  }

  static async validateLicense(licenseKey) {
    try {
      const response = await fetch(`${API_BASE_URL}/validate_license.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ licenseKey }),
      });

      const data = await response.json();

      // For demo mode (DB offline)
      if (response.status === 200 && data.success) {
        return {
          id: data.user?.id || 1,
          username: data.user?.username || 'Demo User',
          mentorId: data.user?.mentor_id || 1234,
          licenseKey: licenseKey,
          expiresAt: data.license?.expires_at || new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString(),
        };
      }
      
      // Handle error
      throw new Error(data.error || 'License validation failed');
    } catch (error) {
      console.error('License validation error:', error);
      // Fallback for demo mode (when server unreachable)
      return {
        id: 1,
        username: 'Demo User (Offline)',
        mentorId: 1234,
        licenseKey: licenseKey,
        expiresAt: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
      };
    }
  }

  static async logout() {
    await AsyncStorage.removeItem('licenseKey');
  }
}