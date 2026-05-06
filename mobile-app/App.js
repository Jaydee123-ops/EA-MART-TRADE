import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  StyleSheet,
  Text,
  View,
  Button,
  FlatList,
  ActivityIndicator,
  Image,
  Animated,
  TouchableOpacity,
  TextInput,
} from 'react-native';
import { AuthService } from './services/AuthService';
import { SignalService } from './services/SignalService';
import { TradeService } from './services/TradeService';

const App = () => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [signals, setSignals] = useState([]);
  const [trades, setTrades] = useState([]);
  const [showWelcome, setShowWelcome] = useState(true);
  const [licenseKey, setLicenseKey] = useState('');
  const fadeAnim = new Animated.Value(0);

  useEffect(() => {
    const initializeApp = async () => {
      try {
        // Check for saved license key
        const licenseKey = await AuthService.getLicenseKey();
        if (licenseKey) {
          const userData = await AuthService.validateLicense(licenseKey);
          setUser(userData);

          // Start listening for signals
          SignalService.startSignalListener((signal) => {
            setSignals(prev => [signal, ...prev]);
            // Execute trade automatically
            TradeService.executeTrade(signal);
          });
        }
      } catch (error) {
        console.error('Initialization error:', error);
      } finally {
        setLoading(false);
      }
    };

    initializeApp();
  }, []);

  // Welcome screen animation
  useEffect(() => {
    if (showWelcome) {
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 1000,
        useNativeDriver: true,
      }).start();
    }
  }, [showWelcome]);

  const handleEnterApp = () => {
    Animated.timing(fadeAnim, {
      toValue: 0,
      duration: 500,
      useNativeDriver: true,
    }).start(() => setShowWelcome(false));
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <ActivityIndicator size="large" color="#dc2626" />
        <Text style={styles.loadingText}>Loading EA Smart Trade...</Text>
      </SafeAreaView>
    );
  }

  // Welcome Screen
  if (showWelcome) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.welcomeContainer}>
          {/* Animated Background Elements */}
          <View style={styles.animatedBackground}>
            <Animated.View
              style={[
                styles.floatingCircle,
                { opacity: fadeAnim }
              ]}
            />
            <Animated.View
              style={[
                styles.floatingCircle2,
                { opacity: fadeAnim }
              ]}
            />
          </View>

          {/* Logo & Branding */}
          <Animated.View
            style={[
              styles.logoContainer,
              { opacity: fadeAnim }
            ]}
          >
            <View style={styles.logoIcon}>
              <Text style={styles.logoText}>📈</Text>
            </View>
            <Text style={styles.brandName}>EA SMART TRADE</Text>
            <Text style={styles.brandTagline}>Mobile Trading Robot</Text>
            <View style={styles.divider} />
            <Text style={styles.welcomeText}>Welcome to the Future of Copy Trading</Text>
          </Animated.View>

          {/* Features List */}
          <Animated.View
            style={[
              styles.featuresContainer,
              { opacity: fadeAnim }
            ]}
          >
            <FeatureItem icon="⚡" text="Real-time Signal Delivery" />
            <FeatureItem icon="🤖" text="Auto Trade Execution" />
            <FeatureItem icon="🔐" text="Secure License Protection" />
            <FeatureItem icon="🌐" text="Works on All Brokers" />
          </Animated.View>

          {/* CTA Button */}
          <Animated.View
            style={[
              styles.ctaContainer,
              { opacity: fadeAnim }
            ]}
          >
            <TouchableOpacity style={styles.enterButton} onPress={handleEnterApp}>
              <Text style={styles.enterButtonText}>🔑 CONNECT LICENSE</Text>
            </TouchableOpacity>
          </Animated.View>

          <Text style={styles.version}>v2.0 - Aggressive Edition</Text>
        </View>
      </SafeAreaView>
    );
  }

  // License Entry Screen (if not logged in)
  if (!user) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loginContainer}>
          <Text style={styles.loginTitle}>📱 TRADING ROBOT</Text>
          <Text style={styles.loginSubtitle}>Enter your license key to connect</Text>

          <View style={styles.inputWrapper}>
            <Text style={styles.inputLabel}>LICENSE KEY</Text>
            <TextInput
              style={styles.licenseInput}
              placeholder="EAV-XXXX-XXX-XXXX"
              placeholderTextColor="#666"
              autoCapitalize="characters"
              onChangeText={(text) => setLicenseKey(text)}
              value={licenseKey}
            />
          </View>

          <TouchableOpacity
            style={styles.connectButton}
            onPress={async () => {
              if (licenseKey && licenseKey.startsWith('EAV-')) {
                try {
                  const userData = await AuthService.validateLicense(licenseKey);
                  setUser(userData);
                  await AuthService.saveLicenseKey(licenseKey);
                  SignalService.startSignalListener((signal) => {
                    setSignals(prev => [signal, ...prev]);
                    TradeService.executeTrade(signal);
                  });
                } catch (error) {
                  alert('Invalid license key');
                }
              } else {
                alert('Please enter a valid license key (starts with EAV-)');
              }
            }}
          >
            <Text style={styles.connectButtonText}>CONNECT NOW</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.demoButton}>
            <Text style={styles.demoButtonText}>Try Demo Mode</Text>
          </TouchableOpacity>

          <Text style={styles.loginFooter}>Powered by EA Smart Trade Platform</Text>
        </View>
      </SafeAreaView>
    );
  }

  // Main Dashboard
  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <Text style={styles.headerTitle}>📱 TRADING ROBOT</Text>
          <TouchableOpacity onPress={() => setShowWelcome(true)}>
            <Text style={styles.logoutBtn}>⚠️</Text>
          </TouchableOpacity>
        </View>
        <View style={styles.statusBar}>
          <View style={styles.statusIndicator} />
          <Text style={styles.statusText}>● Connected as @{user.username}</Text>
          <Text style={styles.timeText}>{new Date().toLocaleTimeString()}</Text>
        </View>
      </View>

      {/* Content */}
      <View style={styles.content}>
        {/* Active Signals Section */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionIcon}>⚡</Text>
            <Text style={styles.sectionTitle}>ACTIVE SIGNALS</Text>
            <View style={styles.liveBadge}>
              <Text style={styles.liveBadgeText}>LIVE</Text>
            </View>
          </View>

          {signals.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyIcon}>📡</Text>
              <Text style={styles.emptyText}>Waiting for signals...</Text>
              <Text style={styles.emptySubtext}>Signals from your admin will appear here</Text>
            </View>
          ) : (
            <FlatList
              data={signals}
              keyExtractor={(item) => item.id.toString()}
              scrollEnabled={false}
              renderItem={({ item }) => (
                <View style={styles.signalCard}>
                  <View style={styles.signalHeader}>
                    <Text style={styles.signalPair}>{item.pair}</Text>
                    <View style={[
                      styles.directionBadge,
                      item.direction === 'BUY' ? styles.buyBadge : styles.sellBadge
                    ]}>
                      <Text style={[
                        styles.directionText,
                        item.direction === 'BUY' ? styles.buyText : styles.sellText
                      ]}>{item.direction}</Text>
                    </View>
                  </View>
                  <View style={styles.signalDetails}>
                    <View style={styles.detailItem}>
                      <Text style={styles.detailLabel}>TP</Text>
                      <Text style={styles.detailValue}>{item.tp}</Text>
                    </View>
                    <View style={styles.detailItem}>
                      <Text style={styles.detailLabel}>SL</Text>
                      <Text style={styles.detailValue}>{item.sl}</Text>
                    </View>
                    <View style={styles.detailItem}>
                      <Text style={styles.detailLabel}>LOT</Text>
                      <Text style={styles.detailValue}>{item.lot}</Text>
                    </View>
                  </View>
                  <Text style={styles.signalTime}>
                    {new Date(item.timestamp).toLocaleTimeString()}
                  </Text>
                </View>
              )}
            />
          )}
        </View>

        {/* Recent Trades Section */}
        <View style={[styles.section, styles.lastSection]}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionIcon}>💰</Text>
            <Text style={styles.sectionTitle}>RECENT TRADES</Text>
          </View>

          {trades.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyIcon}>💼</Text>
              <Text style={styles.emptyText}>No trades yet</Text>
              <Text style={styles.emptySubtext}>Executed trades will appear here</Text>
            </View>
          ) : (
            <FlatList
              data={trades}
              keyExtractor={(item) => item.id.toString()}
              scrollEnabled={false}
              renderItem={({ item }) => (
                <View style={styles.tradeCard}>
                  <View style={styles.tradeHeader}>
                    <Text style={styles.tradePair}>{item.pair} {item.direction}</Text>
                    <View style={[
                      styles.profitBadge,
                      item.profit >= 0 ? styles.profitPositive : styles.profitNegative
                    ]}>
                      <Text style={[
                        styles.profitText,
                        item.profit >= 0 ? styles.profitPositiveText : styles.profitNegativeText
                      ]}>
                        {item.profit >= 0 ? '+' : ''}${item.profit.toFixed(2)}
                      </Text>
                    </View>
                  </View>
                  <Text style={styles.tradeTime}>
                    {item.status === 'open' ? 'Open' : 'Closed'} • {new Date(item.timestamp).toLocaleTimeString()}
                  </Text>
                </View>
              )}
            />
          )}
        </View>
      </View>

      {/* Bottom Navigation */}
      <View style={styles.bottomNav}>
        <TouchableOpacity style={styles.navItemActive}>
          <Text style={styles.navIcon}>⚡</Text>
          <Text style={styles.navTextActive}>Signals</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.navItem}>
          <Text style={styles.navIcon}>📊</Text>
          <Text style={styles.navText}> Stats</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.navItem}>
          <Text style={styles.navIcon}>⚙️</Text>
          <Text style={styles.navText}>Settings</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
};

// Feature Item Component
const FeatureItem = ({ icon, text }) => (
  <View style={styles.featureItem}>
    <Text style={styles.featureIcon}>{icon}</Text>
    <Text style={styles.featureText}>{text}</Text>
  </View>
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0a0a0a',
  },
  // Loading
  loadingText: {
    color: '#dc2626',
    marginTop: 15,
    fontSize: 16,
    fontWeight: '600',
  },
  // Welcome Screen
  welcomeContainer: {
    flex: 1,
    backgroundColor: '#0a0a0a',
    paddingHorizontal: 30,
    paddingTop: 60,
    alignItems: 'center',
    position: 'relative',
  },
  animatedBackground: {
    position: 'absolute',
    width: '100%',
    height: '100%',
  },
  floatingCircle: {
    position: 'absolute',
    width: 200,
    height: 200,
    borderRadius: 100,
    backgroundColor: '#dc2626',
    top: -50,
    right: -50,
  },
  floatingCircle2: {
    position: 'absolute',
    width: 150,
    height: 150,
    borderRadius: 75,
    backgroundColor: '#7f1d1d',
    bottom: 100,
    left: -50,
  },
  logoContainer: {
    alignItems: 'center',
    marginTop: 40,
    zIndex: 1,
  },
  logoIcon: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#dc2626',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 25,
    borderWidth: 3,
    borderColor: '#f87171',
    shadowColor: '#dc2626',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.5,
    shadowRadius: 20,
    elevation: 10,
  },
  logoText: {
    fontSize: 50,
  },
  brandName: {
    color: '#fff',
    fontSize: 28,
    fontWeight: '800',
    letterSpacing: 3,
    textAlign: 'center',
    textShadowColor: '#dc2626',
    textShadowOffset: { width: 0, height: 0 },
    textShadowRadius: 10,
  },
  brandTagline: {
    color: '#f87171',
    fontSize: 14,
    fontWeight: '600',
    letterSpacing: 2,
    marginTop: 5,
  },
  divider: {
    width: 200,
    height: 2,
    backgroundColor: '#dc2626',
    marginVertical: 25,
    borderRadius: 1,
  },
  welcomeText: {
    color: '#e5e7eb',
    fontSize: 16,
    textAlign: 'center',
    lineHeight: 24,
    paddingHorizontal: 20,
  },
  featuresContainer: {
    width: '100%',
    marginTop: 40,
    zIndex: 1,
  },
  featureItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(220, 38, 38, 0.1)',
    padding: 15,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: 'rgba(220, 38, 38, 0.3)',
  },
  featureIcon: {
    fontSize: 24,
    marginRight: 15,
  },
  featureText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '600',
  },
  ctaContainer: {
    marginTop: 40,
    zIndex: 1,
  },
  enterButton: {
    backgroundColor: '#dc2626',
    paddingVertical: 18,
    paddingHorizontal: 50,
    borderRadius: 30,
    borderWidth: 2,
    borderColor: '#f87171',
    shadowColor: '#dc2626',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.6,
    shadowRadius: 15,
    elevation: 8,
  },
  enterButtonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '800',
    letterSpacing: 2,
    textAlign: 'center',
  },
  version: {
    color: '#6b7280',
    fontSize: 12,
    marginTop: 30,
    fontWeight: '500',
  },
  // Login Screen
  loginContainer: {
    flex: 1,
    backgroundColor: '#0a0a0a',
    paddingHorizontal: 30,
    paddingTop: 100,
    alignItems: 'center',
  },
  loginTitle: {
    color: '#fff',
    fontSize: 32,
    fontWeight: '800',
    letterSpacing: 3,
    marginBottom: 10,
    textShadowColor: '#dc2626',
    textShadowOffset: { width: 0, height: 0 },
    textShadowRadius: 10,
  },
  loginSubtitle: {
    color: '#9ca3af',
    fontSize: 16,
    marginBottom: 50,
  },
  inputWrapper: {
    width: '100%',
    marginBottom: 30,
  },
  inputLabel: {
    color: '#dc2626',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 2,
    marginBottom: 8,
  },
  licenseInput: {
    backgroundColor: '#1f1f1f',
    borderWidth: 2,
    borderColor: '#374151',
    borderRadius: 12,
    padding: 18,
    color: '#fff',
    fontSize: 18,
    fontWeight: '700',
    letterSpacing: 3,
    textAlign: 'center',
  },
  connectButton: {
    backgroundColor: '#dc2626',
    paddingVertical: 18,
    paddingHorizontal: 60,
    borderRadius: 30,
    borderWidth: 2,
    borderColor: '#f87171',
    shadowColor: '#dc2626',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.6,
    shadowRadius: 15,
    elevation: 8,
    marginBottom: 15,
  },
  connectButtonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '800',
    letterSpacing: 2,
  },
  demoButton: {
    paddingVertical: 12,
    paddingHorizontal: 30,
  },
  demoButtonText: {
    color: '#6b7280',
    fontSize: 14,
    fontWeight: '600',
  },
  loginFooter: {
    color: '#4b5563',
    fontSize: 12,
    marginTop: 40,
    textAlign: 'center',
  },
  // Dashboard
  header: {
    backgroundColor: '#000',
    paddingTop: 10,
    paddingBottom: 15,
    borderBottomWidth: 3,
    borderBottomColor: '#dc2626',
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    marginBottom: 10,
  },
  headerTitle: {
    color: '#fff',
    fontSize: 20,
    fontWeight: '800',
    letterSpacing: 2,
  },
  logoutBtn: {
    fontSize: 24,
  },
  statusBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    backgroundColor: '#dc2626',
    paddingVertical: 8,
  },
  statusIndicator: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#22c55e',
    shadowColor: '#22c55e',
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.8,
    shadowRadius: 4,
  },
  statusText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
  timeText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '700',
  },
  content: {
    flex: 1,
    backgroundColor: '#111827',
    paddingHorizontal: 15,
    paddingTop: 15,
  },
  section: {
    backgroundColor: '#1f2937',
    borderRadius: 16,
    padding: 15,
    marginBottom: 15,
    borderWidth: 1,
    borderColor: '#374151',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 5,
  },
  lastSection: {
    marginBottom: 100,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 15,
    paddingBottom: 10,
    borderBottomWidth: 2,
    borderBottomColor: '#dc2626',
  },
  sectionIcon: {
    fontSize: 20,
    marginRight: 8,
  },
  sectionTitle: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 1,
    flex: 1,
  },
  liveBadge: {
    backgroundColor: '#dc2626',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#f87171',
  },
  liveBadgeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1,
  },
  signalCard: {
    backgroundColor: '#111827',
    borderRadius: 12,
    padding: 15,
    marginBottom: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#dc2626',
  },
  signalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  signalPair: {
    color: '#fff',
    fontSize: 20,
    fontWeight: '800',
  },
  directionBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    borderWidth: 1,
  },
  buyBadge: {
    backgroundColor: 'rgba(34, 197, 94, 0.2)',
    borderColor: '#22c55e',
  },
  sellBadge: {
    backgroundColor: 'rgba(239, 68, 68, 0.2)',
    borderColor: '#dc2626',
  },
  directionText: {
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1,
  },
  buyText: {
    color: '#22c55e',
  },
  sellText: {
    color: '#dc2626',
  },
  signalDetails: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  detailItem: {
    alignItems: 'center',
    flex: 1,
  },
  detailLabel: {
    color: '#6b7280',
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: 4,
  },
  detailValue: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  signalTime: {
    color: '#9ca3af',
    fontSize: 12,
    textAlign: 'right',
    marginTop: 5,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 40,
  },
  emptyIcon: {
    fontSize: 48,
    marginBottom: 15,
  },
  emptyText: {
    color: '#9ca3af',
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 5,
  },
  emptySubtext: {
    color: '#6b7280',
    fontSize: 13,
  },
  tradeCard: {
    backgroundColor: '#111827',
    borderRadius: 12,
    padding: 15,
    marginBottom: 12,
  },
  tradeHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  tradePair: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '700',
  },
  profitBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  profitPositive: {
    backgroundColor: 'rgba(34, 197, 94, 0.2)',
  },
  profitNegative: {
    backgroundColor: 'rgba(239, 68, 68, 0.2)',
  },
  profitText: {
    fontSize: 14,
    fontWeight: '800',
  },
  profitPositiveText: {
    color: '#22c55e',
  },
  profitNegativeText: {
    color: '#dc2626',
  },
  tradeTime: {
    color: '#6b7280',
    fontSize: 12,
  },
  bottomNav: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: '#000',
    flexDirection: 'row',
    borderTopWidth: 2,
    borderTopColor: '#dc2626',
    paddingVertical: 12,
  },
  navItem: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 8,
  },
  navItemActive: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 8,
    borderTopWidth: 2,
    borderTopColor: '#dc2626',
  },
  navIcon: {
    fontSize: 24,
    marginBottom: 4,
  },
  navText: {
    color: '#6b7280',
    fontSize: 12,
    fontWeight: '600',
  },
  navTextActive: {
    color: '#dc2626',
    fontSize: 12,
    fontWeight: '800',
  },
});

export default App;