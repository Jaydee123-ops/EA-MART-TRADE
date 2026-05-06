//+------------------------------------------------------------------+
//                                          SmartTradeRobotEA.mq4
//                        Copyright 2026, MetaQuotes Software Corp.
//                                             https://www.mql5.com
//+------------------------------------------------------------------+
#property copyright "Copyright 2026, MetaQuotes Software Corp."
#property link      "https://www.mql5.com"
#property version   "1.00"
#property strict

// Input parameters
extern string   LicenseKey        = "";          // License key for authentication
extern double   LotSize           = 0.1;        // Default lot size
extern int      MagicNumber       = 12345;      // EA Magic number
extern int      Slippage          = 3;          // Max slippage in points
extern bool     UseSignalFile     = true;       // Use signal file for trade signals
extern string   SignalFilePath    = "Signals.txt"; // Signal file name
extern int      SignalCheckInterval = 5;       // Seconds between signal checks

// Global variables
datetime       lastSignalTime    = 0;
string         licenseValid      = "false";

//+------------------------------------------------------------------+
//| Expert initialization function                                   |
//+------------------------------------------------------------------+
int init()
{
   // Initialize license validation
   licenseValid = ValidateLicense(LicenseKey);
   
   if(licenseValid != "true")
   {
      Print("Invalid license key. EA will not trade.");
      return(INIT_FAILED);
   }
   
   Print("Smart Trade Robot EA initialized successfully with license: ", LicenseKey);
   
   // Create signal file if it doesn't exist
   if(UseSignalFile)
   {
      int fileHandle = FileOpen(SignalFilePath, FILE_WRITE|FILE_CSV|FILE_ANSI);
      if(fileHandle != INVALID_HANDLE)
      {
         FileClose(fileHandle);
      }
   }
   
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert deinitialization function                                 |
//+------------------------------------------------------------------+
int deinit()
{
   Print("Smart Trade Robot EA deinitialized.");
   return(0);
}

//+------------------------------------------------------------------+
//| Expert start function                                            |
//+------------------------------------------------------------------+
int start()
{
   // Check if we should process signals based on interval
   static datetime lastCheck = 0;
   if(TimeCurrent() - lastCheck < SignalCheckInterval)
      return(0);
      
   lastCheck = TimeCurrent();
   
   // Check for new signals
   if(UseSignalFile && licenseValid == "true")
   {
      CheckForSignals();
   }
   
   return(0);
}

//+------------------------------------------------------------------+
//| Validate license key                                             |
//+------------------------------------------------------------------+
string ValidateLicense(string key)
{
   // In a real implementation, this would connect to a license server
   // For this example, we'll do a simple validation
   if(StringLen(key) < 10)
      return "false";
      
   // Simple checksum validation (in reality, use proper crypto)
   int sum = 0;
   for(int i=0; i<StringLen(key); i++)
      sum += StringGetCharacter(key, i);
      
   return (sum % 100 == 0) ? "true" : "false";
}

//+------------------------------------------------------------------+
//| Check for new signals in signal file                             |
//+------------------------------------------------------------------+
void CheckForSignals()
{
   if(!FileIsExist(SignalFilePath))
      return;
      
   int fileHandle = FileOpen(SignalFilePath, FILE_READ|FILE_CSV|FILE_ANSI);
   if(fileHandle == INVALID_HANDLE)
      return;
   
   string line[];
   int    total = FileReadArray(fileHandle, line);
   FileClose(fileHandle);
   
   if(total <= 0)
      return;
   
   // Process each signal line (most recent last)
   for(int i=0; i<total; i++)
   {
      string signalLine = line[i];
      if(StringLen(signalLine) < 5) continue;
      
      // Parse signal format: TIMESTAMP|PAIR|DIRECTION|LOT|TP|SL
      // Example: 2026.04.29 10:30:00|EURUSD|BUY|0.1|1.0850|1.0750
      string fields[];
      int    count = StringSplit(signalLine, '|', fields);
      
      if(count < 6) continue;
      
      datetime signalTime = (datetime)StringToInteger(fields[0]);
      if(signalTime <= lastSignalTime) continue; // Skip old signals
      
      string symbol    = fields[1];
      string direction = fields[2];
      double lots      = (double)StringToDouble(fields[3]);
      double tp        = (double)StringToDouble(fields[4]);
      double sl        = (double)StringToDouble(fields[5]);
      
      // Validate symbol
      if(!SymbolSelect(symbol, true))
      {
         Print("Failed to select symbol: ", symbol);
         continue;
      }
      
      // Execute trade based on direction
      if(direction == "BUY" || direction == "buy")
      {
         ExecuteTrade(symbol, OP_BUY, lots, tp, sl);
      }
      else if(direction == "SELL" || direction == "sell")
      {
         ExecuteTrade(symbol, OP_SELL, lots, tp, sl);
      }
      
      lastSignalTime = signalTime;
   }
   
   // Clear processed signals (keep only unprocessed ones)
   // For simplicity, we'll clear the file after processing
   // In production, you might want a more sophisticated approach
   ClearSignalFile();
}

//+------------------------------------------------------------------+
//| Execute trade                                                    |
//+------------------------------------------------------------------+
void ExecuteTrade(string symbol, int cmd, double lots, double tp, double sl)
{
   // Check if we can trade
   if(!IsTradeAllowed())
   {
      Print("Trading is not allowed");
      return;
   }
   
   // Calculate prices
   double price = (cmd == OP_BUY) ? Ask : Bid;
   
   // Prepare trade request
   int ticket = 0;
   
   if(cmd == OP_BUY)
   {
      ticket = OrderSend(symbol, OP_BUY, lots, price, Slippage, tp, sl, "SmartTradeRobot", MagicNumber, 0, clrGreen);
   }
   else
   {
      ticket = OrderSend(symbol, OP_SELL, lots, price, Slippage, tp, sl, "SmartTradeRobot", MagicNumber, 0, clrRed);
   }
   
   // Check result
   if(ticket < 0)
   {
      Print("OrderSend failed. Error: ", GetLastError());
      return;
   }
   
   Print("Trade executed successfully. Ticket: ", ticket);
}

//+------------------------------------------------------------------+
//| Clear signal file                                                |
//+------------------------------------------------------------------+
void ClearSignalFile()
{
   int fileHandle = FileOpen(SignalFilePath, FILE_WRITE|FILE_CSV|FILE_ANSI);
   if(fileHandle != INVALID_HANDLE)
   {
      FileClose(fileHandle);
   }
}

//+------------------------------------------------------------------+
//| Check if file exists                                             |
//+------------------------------------------------------------------+
bool FileIsExist(string filename)
{
   int handle = FileOpen(filename, FILE_READ|FILE_CSV|FILE_ANSI);
   if(handle != INVALID_HANDLE)
   {
      FileClose(handle);
      return(true);
   }
   return(false);
}