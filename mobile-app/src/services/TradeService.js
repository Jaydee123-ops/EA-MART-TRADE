import AsyncStorage from '@react-native-async-storage/async-storage';

export class TradeService {
  static async executeTrade(signal) {
    // In a real implementation, this would:
    // 1. Format the signal for MT4/MT5 EA (via deep link, local server, or message queue)
    // 2. Send the trade command to the MT4/MT5 mobile app or EA
    // 3. Wait for confirmation from the EA
    
    // For now, we'll simulate trade execution and store the trade
    const trade = {
      id: Date.now(),
      pair: signal.pair,
      direction: signal.direction,
      lot: signal.lot,
      tp: signal.tp,
      sl: signal.sl,
      openPrice: signal.direction === 'BUY' ? signal.tp - 0.0005 : signal.tp + 0.0005, // Simulated
      openTime: new Date().toISOString(),
      profit: 0, // Will be updated by profit simulation
      status: 'open'
    };

    // Save trade to local storage (in real app, this would come from MT4/5 EA)
    const trades = await this.getTrades();
    trades.push(trade);
    await AsyncStorage.setItem('trades', JSON.stringify(trades));

    // Start profit simulation (in real app, profit would come from MT4/5)
    this.simulateProfit(trade.id);

    return trade;
  }

  static async getTrades() {
    try {
      const tradesJson = await AsyncStorage.getItem('trades');
      return tradesJson ? JSON.parse(tradesJson) : [];
    } catch (error) {
      console.error('Failed to get trades:', error);
      return [];
    }
  }

  static async updateTrade(tradeId, updates) {
    const trades = await this.getTrades();
    const tradeIndex = trades.findIndex(t => t.id === tradeId);
    if (tradeIndex !== -1) {
      trades[tradeIndex] = { ...trades[tradeIndex], ...updates };
      await AsyncStorage.setItem('trades', JSON.stringify(trades));
      return trades[tradeIndex];
    }
    return null;
  }

  static simulateProfit(tradeId) {
    // Simulate profit changing over time (for demo purposes)
    const interval = setInterval(async () => {
      const trades = await this.getTrades();
      const trade = trades.find(t => t.id === tradeId);
      if (!trade || trade.status !== 'open') {
        clearInterval(interval);
        return;
      }

      // Random profit change between -10 and +10
      const profitChange = (Math.random() * 20 - 10);
      let newProfit = trade.profit + profitChange;
      
      // Close trade if TP or SL is hit (simplified)
      if (newProfit > 50) { // TP hit
        newProfit = 50;
        await this.updateTrade(tradeId, { 
          profit: newProfit, 
          status: 'closed',
          closeTime: new Date().toISOString()
        });
        clearInterval(interval);
      } else if (newProfit < -20) { // SL hit
        newProfit = -20;
        await this.updateTrade(tradeId, { 
          profit: newProfit, 
          status: 'closed',
          closeTime: new Date().toISOString()
        });
        clearInterval(interval);
      } else {
        await this.updateTrade(tradeId, { profit: newProfit });
      }
    }, 3000); // Update every 3 seconds
  }
}