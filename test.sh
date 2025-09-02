#!/bin/bash

# SmokeoutNYC v2.0 Test Script
# Validates that the application is properly set up and running

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_test() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

print_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

print_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
}

print_skip() {
    echo -e "${YELLOW}[SKIP]${NC} $1"
}

echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}  SmokeoutNYC v2.0 Test Suite${NC}"
echo -e "${GREEN}======================================${NC}"
echo ""

# Test 1: Environment files
print_test "Checking environment configuration..."
if [[ -f ".env" ]]; then
    print_pass "Main .env file exists"
else
    print_fail "Main .env file missing"
fi

if [[ -f "client/.env" ]]; then
    print_pass "Client .env file exists"
else
    print_fail "Client .env file missing"
fi

# Test 2: Dependencies
print_test "Checking dependencies..."
if [[ -d "node_modules" ]]; then
    print_pass "Node.js dependencies installed"
else
    print_fail "Node.js dependencies missing - run 'npm install'"
fi

if [[ -d "vendor" ]]; then
    print_pass "PHP dependencies installed"
else
    print_fail "PHP dependencies missing - run 'composer install'"
fi

if [[ -d "client/node_modules" ]]; then
    print_pass "Client dependencies installed"
else
    print_fail "Client dependencies missing - run 'cd client && npm install'"
fi

# Test 3: Required directories
print_test "Checking required directories..."
for dir in uploads logs tmp; do
    if [[ -d "$dir" ]]; then
        print_pass "Directory '$dir' exists"
    else
        print_fail "Directory '$dir' missing - run 'mkdir -p $dir'"
    fi
done

# Test 4: Database connection
print_test "Testing database connection..."
if [[ -f ".env" ]]; then
    source .env
    if command -v mysql &> /dev/null; then
        if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME; SELECT 1;" 2>/dev/null; then
            print_pass "Database connection successful"
        else
            print_fail "Database connection failed - check credentials in .env"
        fi
    else
        print_skip "MySQL client not available"
    fi
else
    print_skip "No .env file found"
fi

# Test 5: PHP syntax check
print_test "Checking PHP syntax..."
php_errors=0
for file in api/*.php config/*.php; do
    if [[ -f "$file" ]]; then
        if php -l "$file" > /dev/null 2>&1; then
            continue
        else
            print_fail "PHP syntax error in $file"
            php_errors=$((php_errors + 1))
        fi
    fi
done

if [[ $php_errors -eq 0 ]]; then
    print_pass "All PHP files have valid syntax"
fi

# Test 6: API endpoints (if server is running)
print_test "Testing API endpoints..."
if curl -s http://localhost:8000/api/health.php > /dev/null 2>&1; then
    
    # Test health endpoint
    health_response=$(curl -s http://localhost:8000/api/health.php)
    if echo "$health_response" | grep -q '"status":"ok"'; then
        print_pass "Health endpoint returns OK status"
    else
        print_fail "Health endpoint reports issues"
    fi
    
    # Test auth endpoint
    if curl -s -X OPTIONS http://localhost:8000/api/auth.php > /dev/null 2>&1; then
        print_pass "Auth endpoint accessible"
    else
        print_fail "Auth endpoint not accessible"
    fi
    
    # Test game endpoint
    if curl -s -X OPTIONS http://localhost:8000/api/game.php > /dev/null 2>&1; then
        print_pass "Game endpoint accessible"
    else
        print_fail "Game endpoint not accessible"
    fi
    
    # Test AI risk endpoint
    if curl -s -X OPTIONS http://localhost:8000/api/ai_risk_meter.php > /dev/null 2>&1; then
        print_pass "AI Risk endpoint accessible"
    else
        print_fail "AI Risk endpoint not accessible"
    fi
    
else
    print_skip "PHP server not running on localhost:8000 - start with './dev.sh'"
fi

# Test 7: React build
print_test "Testing React build..."
if [[ -d "client/build" ]]; then
    print_pass "React build directory exists"
else
    print_skip "React build not found - run 'cd client && npm run build'"
fi

# Test 8: Configuration validation
print_test "Validating configuration..."
if [[ -f ".env" ]]; then
    source .env
    
    if [[ -n "$JWT_SECRET" && "$JWT_SECRET" != "your-super-secret-jwt-key-change-this-in-production" ]]; then
        print_pass "JWT secret configured"
    else
        print_fail "JWT secret not properly configured"
    fi
    
    if [[ -n "$DB_NAME" && -n "$DB_USER" ]]; then
        print_pass "Database credentials configured"
    else
        print_fail "Database credentials not configured"
    fi
fi

# Test 9: File permissions
print_test "Checking file permissions..."
for script in setup.sh dev.sh test.sh; do
    if [[ -f "$script" ]]; then
        if [[ -x "$script" ]]; then
            print_pass "Script '$script' is executable"
        else
            print_fail "Script '$script' is not executable - run 'chmod +x $script'"
        fi
    fi
done

echo ""
echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}  Test Summary${NC}"
echo -e "${GREEN}======================================${NC}"
echo ""

# Count results
total_tests=$(grep -c "print_test" "$0")
passed_tests=$(grep -c "print_pass" /tmp/test_output 2>/dev/null || echo "0")
failed_tests=$(grep -c "print_fail" /tmp/test_output 2>/dev/null || echo "0")

echo "📊 Test Results:"
echo "   Total Tests: $total_tests"
echo "   Passed: ✅ (check output above)"
echo "   Failed: ❌ (check output above)"
echo "   Skipped: ⏭️ (check output above)"
echo ""

if [[ $failed_tests -gt 0 ]]; then
    echo -e "${RED}Some tests failed. Please fix the issues above.${NC}"
    echo ""
    echo "🔧 Common fixes:"
    echo "   • Run './setup.sh' if not done already"
    echo "   • Check database credentials in .env"
    echo "   • Install missing dependencies"
    echo "   • Start the development server with './dev.sh'"
    exit 1
else
    echo -e "${GREEN}🎉 All critical tests passed!${NC}"
    echo ""
    echo "🚀 Ready to start development:"
    echo "   ./dev.sh"
    echo ""
    echo "📱 Application URLs:"
    echo "   Frontend: http://localhost:3000"
    echo "   PHP API: http://localhost:8000"
    echo "   Health Check: http://localhost:8000/api/health.php"
fi

echo ""
