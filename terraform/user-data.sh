#!/bin/bash

# SmokeoutNYC v2.0 - EC2 User Data Script
# This script initializes the EC2 instance with required software and configuration

set -e

# Update system
apt-get update
apt-get upgrade -y

# Install required packages
apt-get install -y \
    nginx \
    mysql-client \
    php8.1 \
    php8.1-fpm \
    php8.1-mysql \
    php8.1-curl \
    php8.1-json \
    php8.1-mbstring \
    php8.1-xml \
    php8.1-zip \
    php8.1-gd \
    composer \
    nodejs \
    npm \
    git \
    unzip \
    awscli \
    supervisor

# Install CloudWatch agent
wget https://s3.amazonaws.com/amazoncloudwatch-agent/ubuntu/amd64/latest/amazon-cloudwatch-agent.deb
dpkg -i -E ./amazon-cloudwatch-agent.deb

# Create application user
useradd -m -s /bin/bash smokeout
usermod -aG www-data smokeout

# Create application directory
mkdir -p /var/www/smokeout-nyc
chown smokeout:www-data /var/www/smokeout-nyc

# Clone application repository
cd /var/www/smokeout-nyc
git clone https://github.com/yourusername/smokeout_nyc.git .
chown -R smokeout:www-data /var/www/smokeout-nyc

# Install PHP dependencies
sudo -u smokeout composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
sudo -u smokeout npm install --production

# Install client dependencies and build
cd client
sudo -u smokeout npm install --production
sudo -u smokeout npm run build
cd ..

# Create environment file
cat > /var/www/smokeout-nyc/.env << EOL
# Database Configuration
DB_HOST=${db_host}
DB_NAME=${db_name}
DB_USER=${db_user}
DB_PASS=${db_password}
DB_PORT=3306

# JWT Configuration
JWT_SECRET=${jwt_secret}
JWT_EXPIRY=3600

# Application Configuration
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

# Session Configuration
SESSION_LIFETIME=3600
SESSION_SECURE=false
SESSION_HTTPONLY=true

# Rate Limiting
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600

# Email Configuration
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=noreply@smokeoutnyc.com

# OAuth Configuration (optional)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=

# Payment Configuration (optional)
STRIPE_PUBLIC_KEY=
STRIPE_SECRET_KEY=
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=

# AWS Configuration
AWS_REGION=us-east-1
AWS_S3_BUCKET=smokeout-nyc-production-uploads
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=

# External APIs
GOOGLE_MAPS_API_KEY=
OPENAI_API_KEY=
NEWS_API_KEY=
EOL

# Create client environment file
cat > /var/www/smokeout-nyc/client/.env << EOL
REACT_APP_API_URL=http://localhost:8000
REACT_APP_NODE_API_URL=http://localhost:3001
REACT_APP_ENV=production
EOL

# Set proper permissions
chown smokeout:www-data /var/www/smokeout-nyc/.env
chown smokeout:www-data /var/www/smokeout-nyc/client/.env
chmod 640 /var/www/smokeout-nyc/.env
chmod 640 /var/www/smokeout-nyc/client/.env

# Create required directories
mkdir -p /var/www/smokeout-nyc/{uploads,logs,tmp}
chown -R smokeout:www-data /var/www/smokeout-nyc/{uploads,logs,tmp}
chmod -R 755 /var/www/smokeout-nyc/{uploads,logs,tmp}

# Configure Nginx
cat > /etc/nginx/sites-available/smokeout-nyc << EOL
server {
    listen 80;
    server_name _;
    root /var/www/smokeout-nyc/client/build;
    index index.html;

    # Serve React app
    location / {
        try_files \$uri \$uri/ /index.html;
    }

    # PHP API endpoints
    location /api/ {
        root /var/www/smokeout-nyc;
        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    # Node.js API proxy
    location /node-api/ {
        proxy_pass http://localhost:3001/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
    }

    # Static file handling
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOL

# Enable the site
ln -sf /etc/nginx/sites-available/smokeout-nyc /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Configure PHP-FPM
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.1/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/' /etc/php/8.1/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 50M/' /etc/php/8.1/fpm/php.ini

# Configure Supervisor for Node.js app
cat > /etc/supervisor/conf.d/smokeout-node.conf << EOL
[program:smokeout-node]
command=/usr/bin/node /var/www/smokeout-nyc/server.js
directory=/var/www/smokeout-nyc
autostart=true
autorestart=true
stderr_logfile=/var/log/smokeout-node.err.log
stdout_logfile=/var/log/smokeout-node.out.log
user=smokeout
environment=NODE_ENV=production
EOL

# Configure CloudWatch agent
cat > /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json << EOL
{
    "logs": {
        "logs_collected": {
            "files": {
                "collect_list": [
                    {
                        "file_path": "/var/www/smokeout-nyc/logs/*.log",
                        "log_group_name": "/aws/ec2/smokeout-nyc",
                        "log_stream_name": "application-logs"
                    },
                    {
                        "file_path": "/var/log/nginx/access.log",
                        "log_group_name": "/aws/ec2/smokeout-nyc",
                        "log_stream_name": "nginx-access"
                    },
                    {
                        "file_path": "/var/log/nginx/error.log",
                        "log_group_name": "/aws/ec2/smokeout-nyc",
                        "log_stream_name": "nginx-error"
                    }
                ]
            }
        }
    },
    "metrics": {
        "namespace": "SmokeoutNYC",
        "metrics_collected": {
            "cpu": {
                "measurement": ["cpu_usage_idle", "cpu_usage_iowait", "cpu_usage_user", "cpu_usage_system"]
            },
            "disk": {
                "measurement": ["used_percent"],
                "metrics_collection_interval": 60,
                "resources": ["*"]
            },
            "mem": {
                "measurement": ["mem_used_percent"]
            }
        }
    }
}
EOL

# Start CloudWatch agent
/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
    -a fetch-config -m ec2 -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json -s

# Restart services
systemctl restart php8.1-fpm
systemctl restart nginx
systemctl restart supervisor
systemctl enable nginx
systemctl enable php8.1-fpm
systemctl enable supervisor

# Run database setup (if first instance)
cd /var/www/smokeout-nyc
sudo -u smokeout php -f database/setup_database.php

echo "SmokeoutNYC application setup completed successfully!"
