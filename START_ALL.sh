#!/bin/bash

echo "========================================"
echo "  SMART TRADE MOBILE ROBOT - LAUNCHER"
echo "========================================"
echo ""

cd "$(dirname "$0")/backend"

echo "[1/2] Starting WebSocket Server..."
php websocket_server.php &
WS_PID=$!
sleep 2

echo "[2/2] Starting Signal Dispatcher..."
php signal_dispatcher.php &
SD_PID=$!
sleep 2

echo ""
echo "========================================"
echo "  All services started!"
echo "========================================"
echo ""
echo "Services running:"
echo "  - WebSocket Server:   ws://YOUR_IP:8080"
echo "  - Signal Dispatcher:  monitoring database"
echo "  - API:                http://YOUR_IP/ea smart trade/backend/"
echo ""
echo "Next steps:"
echo "  1. Build mobile app: cd mobile-app && npm install && npx react-native run-android"
echo "  2. Install EA on MT4/MT5 from ea/SmartTradeRobotEA.mq4"
echo "  3. Configure mobile app with your server IP"
echo ""
echo "Press Ctrl+C to stop all services"
echo ""

# Wait for user to stop
trap "kill $WS_PID $SD_PID 2>/dev/null; exit" INT
wait
