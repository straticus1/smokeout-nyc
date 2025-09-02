#!/bin/bash

# SmokeoutNYC v2.0 Development Server Script
# Starts both frontend and backend development servers

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[DEV]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [[ ! -f "package.json" ]] || [[ ! -f "composer.json" ]]; then
    print_error "This script must be run from the SmokeoutNYC root directory"
    exit 1
fi

# Check if setup has been run
if [[ ! -f ".env" ]]; then
    print_warning "Environment file not found. Running setup first..."
    if [[ -x "./setup.sh" ]]; then
        ./setup.sh
    else
        print_error "Setup script not found or not executable. Please run setup first."
        exit 1
    fi
fi

print_status "Starting SmokeoutNYC v2.0 Development Environment"
echo ""

# Function to cleanup background processes
cleanup() {
    print_status "Stopping development servers..."
    jobs -p | xargs -r kill
    exit 0
}

# Trap Ctrl+C
trap cleanup SIGINT

# Check if dependencies are installed
if [[ ! -d "node_modules" ]]; then
    print_status "Installing Node.js dependencies..."
    npm install
fi

if [[ ! -d "vendor" ]]; then
    print_status "Installing PHP dependencies..."
    composer install --optimize-autoloader
fi

if [[ ! -d "client/node_modules" ]]; then
    print_status "Installing client dependencies..."
    cd client && npm install && cd ..
fi

# Start PHP built-in server for API endpoints
print_status "Starting PHP development server on port 8000..."
php -S localhost:8000 -t . > logs/php-server.log 2>&1 &
PHP_PID=$!

# Wait a moment for PHP server to start
sleep 2

# Check if PHP server started successfully
if ! curl -s http://localhost:8000 > /dev/null; then
    print_error "PHP server failed to start"
    kill $PHP_PID 2>/dev/null || true
    exit 1
fi

print_status "PHP API server running at http://localhost:8000"

# Start Node.js backend server
print_status "Starting Node.js backend server on port 3001..."
if [[ -f "src/server/index.ts" ]]; then
    npm run server:dev > logs/node-server.log 2>&1 &
    NODE_PID=$!
    sleep 2
    print_status "Node.js server running at http://localhost:3001"
else
    print_warning "Node.js server not found, skipping..."
fi

# Start React development server
print_status "Starting React development server on port 3000..."
cd client
npm start > ../logs/react-server.log 2>&1 &
REACT_PID=$!
cd ..

# Wait for React server to start
sleep 5

print_status "React client running at http://localhost:3000"
echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Development Environment Ready! 🚀${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "📱 Application URLs:"
echo "   Frontend (React): http://localhost:3000"
echo "   PHP API: http://localhost:8000"
if [[ -n "$NODE_PID" ]]; then
    echo "   Node.js API: http://localhost:3001"
fi
echo ""
echo "📋 Available endpoints:"
echo "   📊 API Status: http://localhost:8000/api/health.php"
echo "   🏪 Stores: http://localhost:8000/api/stores.php"
echo "   🎮 Game: http://localhost:8000/api/game.php"
echo "   🤖 AI Risk: http://localhost:8000/api/ai_risk_meter.php"
echo "   💰 Donations: http://localhost:8000/api/donations.php"
echo ""
echo "📁 Log files:"
echo "   PHP: logs/php-server.log"
echo "   React: logs/react-server.log"
if [[ -n "$NODE_PID" ]]; then
    echo "   Node.js: logs/node-server.log"
fi
echo ""
echo "Press Ctrl+C to stop all servers"
echo ""

# Keep script running and show logs
while true; do
    sleep 1
    # Check if processes are still running
    if ! kill -0 $PHP_PID 2>/dev/null; then
        print_error "PHP server stopped unexpectedly"
        break
    fi
    
    if [[ -n "$NODE_PID" ]] && ! kill -0 $NODE_PID 2>/dev/null; then
        print_warning "Node.js server stopped"
        NODE_PID=""
    fi
    
    if ! kill -0 $REACT_PID 2>/dev/null; then
        print_warning "React server stopped"
        break
    fi
done

# Cleanup
cleanup
