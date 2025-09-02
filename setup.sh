#!/bin/bash

# SmokeoutNYC v2.0 Setup Script
# This script sets up the complete development environment

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logo and header
echo -e "${BLUE}"
echo "   _____ _    _ ______  _  _____ ____  _    _ _______ _   _  __     __ _____ "
echo "  / ____| |  | |  __  | |/ / __|/ __ \| |  | |_   _| \ | | \\ \\   / /|  ___|"
echo " | (___ | |__| | |  | |   /| |__ | |  | | |  | | | |  |  \\| |  \\ \\_/ / | |__ "
echo "  \\___ \\|  __  | |  | |  < |  __|| |  | | |  | | | |  | . \` |   \\   /  |  __| "
echo "  ____) | |  | | |__| | |\\ \\ |___| |__| | |__| | | |  | |\\  |    | |   | |___"
echo " |_____/|_|  |_|______/|_| \\_\\____\\____/ \\____/  |_|  |_| \\_|    |_|   |_____|"
echo ""
echo -e "${NC}${GREEN}SmokeoutNYC v2.0 - Cannabis Industry Platform Setup${NC}"
echo -e "${YELLOW}================================================================${NC}"
echo ""

# Function to print status messages
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if script is run from correct directory
if [[ ! -f "package.json" ]] || [[ ! -f "composer.json" ]]; then
    print_error "This script must be run from the SmokeoutNYC root directory"
    exit 1
fi

# Check system requirements
print_step "1. Checking system requirements..."

# Check for Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js 18+ first."
    echo "Visit: https://nodejs.org/"
    exit 1
fi

NODE_VERSION=$(node --version | cut -d'.' -f1 | sed 's/v//')
if [ "$NODE_VERSION" -lt 16 ]; then
    print_error "Node.js version 16+ is required. Current version: $(node --version)"
    exit 1
fi
print_status "Node.js $(node --version) ✓"

# Check for npm
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed."
    exit 1
fi
print_status "npm $(npm --version) ✓"

# Check for PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed. Please install PHP 8.0+ first."
    exit 1
fi

PHP_VERSION=$(php --version | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
if ! php -v | head -n1 | grep -q "PHP 8"; then
    print_error "PHP 8.0+ is required. Current version: $(php --version | head -n1)"
    exit 1
fi
print_status "PHP $(php --version | head -n1 | cut -d' ' -f2) ✓"

# Check for Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install Composer first."
    echo "Visit: https://getcomposer.org/"
    exit 1
fi
print_status "Composer $(composer --version | cut -d' ' -f3) ✓"

# Check for MySQL
if ! command -v mysql &> /dev/null; then
    print_warning "MySQL client not found. Please ensure MySQL/MariaDB is installed and accessible."
fi

print_status "System requirements check completed!"
echo ""

# Environment setup
print_step "2. Setting up environment configuration..."

if [[ ! -f ".env" ]]; then
    print_status "Creating .env file from template..."
    cp .env .env.backup 2>/dev/null || true
    
    # Prompt for database configuration
    echo ""
    echo -e "${YELLOW}Database Configuration${NC}"
    read -p "MySQL Host [localhost]: " DB_HOST
    DB_HOST=${DB_HOST:-localhost}
    
    read -p "Database Name [smokeout_nyc]: " DB_NAME  
    DB_NAME=${DB_NAME:-smokeout_nyc}
    
    read -p "Database User [smokeout_user]: " DB_USER
    DB_USER=${DB_USER:-smokeout_user}
    
    read -s -p "Database Password: " DB_PASS
    echo ""
    
    # Update .env file
    sed -i.bak "s/DB_HOST=localhost/DB_HOST=$DB_HOST/" .env
    sed -i.bak "s/DB_NAME=smokeout_nyc/DB_NAME=$DB_NAME/" .env  
    sed -i.bak "s/DB_USER=smokeout_user/DB_USER=$DB_USER/" .env
    sed -i.bak "s/DB_PASS=smokeout_password/DB_PASS=$DB_PASS/" .env
    
    # Generate JWT secret
    JWT_SECRET=$(openssl rand -base64 32 2>/dev/null || date | md5sum | cut -d' ' -f1)
    sed -i.bak "s/JWT_SECRET=your-super-secret-jwt-key-change-this-in-production/JWT_SECRET=$JWT_SECRET/" .env
    
    print_status "Environment file configured!"
else
    print_status "Environment file already exists."
fi

# Client environment setup
if [[ ! -f "client/.env" ]]; then
    print_status "Creating client environment file..."
    cp client/.env.example client/.env
    print_warning "Please update client/.env with your API keys later."
fi

echo ""

# Install dependencies
print_step "3. Installing dependencies..."

print_status "Installing Node.js dependencies..."
npm install

print_status "Installing PHP dependencies..."
composer install --optimize-autoloader

print_status "Installing client dependencies..."
cd client
npm install
cd ..

print_status "Dependencies installed successfully!"
echo ""

# Database setup
print_step "4. Setting up database..."

# Test database connection
print_status "Testing database connection..."
if ! mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" 2>/dev/null; then
    print_error "Cannot connect to database. Please check your credentials."
    print_warning "You may need to create the database and user manually:"
    echo "  mysql -u root -p"
    echo "  CREATE DATABASE $DB_NAME;"
    echo "  CREATE USER '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';"
    echo "  GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'$DB_HOST';"
    echo "  FLUSH PRIVILEGES;"
    echo ""
    read -p "Press Enter to continue after setting up the database..."
fi

# Create database if it doesn't exist
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>/dev/null || true

# Import database schemas
print_status "Importing database schemas..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/schema.sql
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/missing_tables_schema.sql
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/game_schema.sql
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/auth_schema.sql

print_status "Database setup completed!"
echo ""

# Create required directories
print_step "5. Creating required directories..."

mkdir -p uploads
mkdir -p logs
mkdir -p tmp
mkdir -p public/assets

# Set proper permissions
chmod 755 uploads logs tmp
chmod 644 .env

print_status "Directories created and permissions set!"
echo ""

# Build frontend
print_step "6. Building frontend application..."

cd client
npm run build
cd ..

print_status "Frontend built successfully!"
echo ""

# Final setup tasks
print_step "7. Final setup tasks..."

# Create initial admin user (if none exists)
php -r "
require_once 'config/database.php';
\$db = DB::getInstance();
\$pdo = \$db->getConnection();

\$stmt = \$pdo->prepare('SELECT COUNT(*) FROM users WHERE role = \"admin\"');
\$stmt->execute();
\$adminCount = \$stmt->fetchColumn();

if (\$adminCount == 0) {
    \$username = 'admin';
    \$email = 'admin@smokeout.nyc';
    \$password = password_hash('admin123', PASSWORD_DEFAULT);
    
    \$stmt = \$pdo->prepare('
        INSERT INTO users (username, email, password_hash, role, status, email_verified, created_at)
        VALUES (?, ?, ?, \"admin\", \"active\", 1, NOW())
    ');
    \$stmt->execute([\$username, \$email, \$password]);
    
    echo \"Created admin user: admin / admin123\n\";
}
"

# Create sample data if database is empty
php -r "
require_once 'config/database.php';
\$db = DB::getInstance();
\$pdo = \$db->getConnection();

\$stmt = \$pdo->prepare('SELECT COUNT(*) FROM politicians');
\$stmt->execute();
\$count = \$stmt->fetchColumn();

if (\$count == 0) {
    // Insert sample politicians
    \$politicians = [
        ['Eric Adams', 'eric-adams', 'Mayor', 'Democratic', 'New York', 'NY'],
        ['Alexandria Ocasio-Cortez', 'aoc', 'Representative', 'Democratic', 'Bronx', 'NY'],
        ['Chuck Schumer', 'chuck-schumer', 'Senator', 'Democratic', 'New York', 'NY']
    ];
    
    foreach (\$politicians as \$pol) {
        \$stmt = \$pdo->prepare('
            INSERT INTO politicians (name, slug, position, party, city, state, office_level, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, \"state\", \"active\", NOW())
        ');
        \$stmt->execute(\$pol);
    }
    
    echo \"Created sample politician data\n\";
}
"

print_status "Initial data setup completed!"
echo ""

# Success message
print_step "🎉 Setup completed successfully!"
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  SmokeoutNYC v2.0 is ready to use!${NC}" 
echo -e "${GREEN}========================================${NC}"
echo ""
echo "📋 Next steps:"
echo "1. Update API keys in .env and client/.env"
echo "2. Start the development server:"
echo "   ${BLUE}npm run dev${NC}"
echo ""
echo "🌐 Application URLs:"
echo "   Frontend: http://localhost:3000"
echo "   Backend API: http://localhost:3001"
echo "   PHP Pages: Configure with your web server"
echo ""
echo "👤 Default admin credentials:"
echo "   Username: admin"
echo "   Password: admin123"
echo "   ${RED}⚠️ Change these in production!${NC}"
echo ""
echo "📖 For documentation, visit: README.md"
echo ""
echo -e "${YELLOW}Happy coding! 🚀${NC}"
