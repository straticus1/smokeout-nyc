#!/bin/bash

# SmokeoutNYC EC2 Instance User Data Script
# This script runs on each EC2 instance launch to configure the environment

set -e

# Variables from Terraform
DB_HOST="${db_host}"
REDIS_HOST="${redis_host}"
ENVIRONMENT="${environment}"
S3_BUCKET="${s3_bucket}"

# Update system packages
yum update -y

# Install essential packages
yum install -y \
    docker \
    git \
    htop \
    wget \
    curl \
    unzip \
    python3 \
    python3-pip \
    amazon-cloudwatch-agent \
    awscli \
    mysql \
    redis

# Install Docker Compose
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# Start and enable Docker
systemctl start docker
systemctl enable docker
usermod -aG docker ec2-user

# Install Node.js (for React build)
curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
yum install -y nodejs

# Install PHP 8.1 and extensions
amazon-linux-extras enable php8.1
yum install -y \
    php \
    php-cli \
    php-fpm \
    php-mysql \
    php-redis \
    php-json \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    php-gd \
    php-opcache

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Nginx
yum install -y nginx
systemctl enable nginx

# Create application directory
mkdir -p /var/www/smokeout-nyc
chown -R ec2-user:ec2-user /var/www/smokeout-nyc

# Create environment configuration
cat > /var/www/smokeout-nyc/.env << EOF
# Environment Configuration
ENVIRONMENT=${ENVIRONMENT}
APP_NAME="SmokeoutNYC"
APP_ENV=${ENVIRONMENT}
APP_DEBUG=$([ "${ENVIRONMENT}" = "production" ] && echo "false" || echo "true")

# Database Configuration
DB_HOST=${DB_HOST}
DB_PORT=3306
DB_NAME=smokeout_nyc
DB_USERNAME=smokeout_admin
DB_PASSWORD=${db_password}

# Redis Configuration  
REDIS_HOST=${REDIS_HOST}
REDIS_PORT=6379
REDIS_PASSWORD=${redis_auth_token}

# S3 Configuration
S3_BUCKET=${S3_BUCKET}
AWS_REGION=${aws_region}

# JWT Configuration
JWT_SECRET=$(openssl rand -base64 32)

# WebSocket Configuration
WEBSOCKET_PORT=8080
WEBSOCKET_HOST=0.0.0.0

# API Configuration
API_BASE_URL=https://${domain_name}/api
CLIENT_URL=https://${domain_name}

# Gaming Configuration
GAME_TICK_RATE=30
MAX_CONCURRENT_GAMES=1000

# Performance Configuration
CACHE_TTL=3600
SESSION_TTL=86400
API_RATE_LIMIT=1000

# Security Configuration
CORS_ORIGINS="https://${domain_name}"
SECURE_COOKIES=true
HTTPS_ONLY=true
EOF

# Set proper permissions
chown ec2-user:ec2-user /var/www/smokeout-nyc/.env
chmod 600 /var/www/smokeout-nyc/.env

# Configure Nginx
cat > /etc/nginx/conf.d/smokeout-nyc.conf << EOF
# SmokeoutNYC Nginx Configuration

# Rate limiting
limit_req_zone \$binary_remote_addr zone=api:10m rate=10r/s;
limit_req_zone \$binary_remote_addr zone=web:10m rate=50r/s;

# Upstream servers
upstream websocket_backend {
    server 127.0.0.1:8080;
    keepalive 32;
}

upstream php_backend {
    server 127.0.0.1:9000;
    keepalive 32;
}

server {
    listen 80;
    server_name _;
    root /var/www/smokeout-nyc/client/dist;
    index index.html;
    
    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()";
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/javascript;
    
    # Static assets caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # API endpoints
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        
        try_files \$uri \$uri/ @php;
        
        # CORS headers for API
        add_header Access-Control-Allow-Origin "https://${domain_name}";
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization";
        
        if (\$request_method = 'OPTIONS') {
            return 204;
        }
    }
    
    # WebSocket proxy
    location /ws/ {
        proxy_pass http://websocket_backend/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 86400;
    }
    
    # PHP processing
    location @php {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param HTTPS on;
    }
    
    # React app
    location / {
        limit_req zone=web burst=100 nodelay;
        try_files \$uri \$uri/ /index.html;
    }
    
    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
    
    # Block access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~ \.(sql|conf|env)$ {
        deny all;
        access_log off;
        log_not_found off;
    }
}
EOF

# Configure PHP-FPM
cat > /etc/php-fpm.d/smokeout-nyc.conf << EOF
[smokeout-nyc]
user = ec2-user
group = ec2-user
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 1000
php_admin_value[error_log] = /var/log/php-fpm/smokeout-nyc-error.log
php_admin_flag[log_errors] = on
EOF

# Create PHP-FPM log directory
mkdir -p /var/log/php-fpm
chown ec2-user:ec2-user /var/log/php-fpm

# Configure CloudWatch Agent
cat > /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json << EOF
{
    "agent": {
        "metrics_collection_interval": 60,
        "run_as_user": "root"
    },
    "metrics": {
        "namespace": "SmokeoutNYC/EC2",
        "metrics_collected": {
            "cpu": {
                "measurement": [
                    "cpu_usage_idle",
                    "cpu_usage_iowait",
                    "cpu_usage_system",
                    "cpu_usage_user"
                ],
                "metrics_collection_interval": 60
            },
            "disk": {
                "measurement": [
                    "used_percent"
                ],
                "metrics_collection_interval": 60,
                "resources": [
                    "*"
                ]
            },
            "diskio": {
                "measurement": [
                    "io_time",
                    "read_bytes",
                    "write_bytes",
                    "reads",
                    "writes"
                ],
                "metrics_collection_interval": 60,
                "resources": [
                    "*"
                ]
            },
            "mem": {
                "measurement": [
                    "mem_used_percent"
                ],
                "metrics_collection_interval": 60
            },
            "netstat": {
                "measurement": [
                    "tcp_established",
                    "tcp_time_wait"
                ],
                "metrics_collection_interval": 60
            },
            "swap": {
                "measurement": [
                    "swap_used_percent"
                ],
                "metrics_collection_interval": 60
            }
        }
    },
    "logs": {
        "logs_collected": {
            "files": {
                "collect_list": [
                    {
                        "file_path": "/var/log/nginx/access.log",
                        "log_group_name": "/aws/ec2/smokeout-nyc",
                        "log_stream_name": "{instance_id}/nginx/access.log"
                    },
                    {
                        "file_path": "/var/log/nginx/error.log",
                        "log_group_name": "/aws/ec2/smokeout-nyc",
                        "log_stream_name": "{instance_id}/nginx/error.log"
                    },
                    {
                        "file_path": "/var/log/php-fpm/smokeout-nyc-error.log",
                        "log_group_name": "/aws/ec2/smokeout-nyc",
                        "log_stream_name": "{instance_id}/php-fpm/error.log"
                    },
                    {
                        "file_path": "/var/www/smokeout-nyc/logs/application.log",
                        "log_group_name": "/aws/ec2/smokeout-nyc",
                        "log_stream_name": "{instance_id}/application.log"
                    }
                ]
            }
        }
    }
}
EOF

# Start CloudWatch Agent
/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
    -a fetch-config -m ec2 -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json -s

# Create systemd service for WebSocket server
cat > /etc/systemd/system/smokeout-websocket.service << EOF
[Unit]
Description=SmokeoutNYC WebSocket Server
After=network.target

[Service]
Type=simple
User=ec2-user
WorkingDirectory=/var/www/smokeout-nyc/server
ExecStart=/usr/bin/php websocket_server.php
Restart=always
RestartSec=10
Environment=HOME=/home/ec2-user

[Install]
WantedBy=multi-user.target
EOF

# Create log rotation configuration
cat > /etc/logrotate.d/smokeout-nyc << EOF
/var/www/smokeout-nyc/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 644 ec2-user ec2-user
    sharedscripts
    postrotate
        systemctl reload nginx > /dev/null 2>&1 || true
        systemctl reload php-fpm > /dev/null 2>&1 || true
    endscript
}
EOF

# Create monitoring script
cat > /home/ec2-user/monitor.sh << EOF
#!/bin/bash

# SmokeoutNYC Health Monitoring Script

LOG_FILE="/var/log/smokeout-monitor.log"
WEBHOOK_URL="YOUR_SLACK_WEBHOOK_URL_HERE"

log_message() {
    echo "[\$(date)] \$1" >> \$LOG_FILE
}

check_service() {
    local service=\$1
    if ! systemctl is-active --quiet \$service; then
        log_message "WARNING: \$service is not running"
        systemctl start \$service
        if systemctl is-active --quiet \$service; then
            log_message "INFO: \$service restarted successfully"
        else
            log_message "ERROR: Failed to restart \$service"
        fi
    fi
}

# Check critical services
check_service nginx
check_service php-fpm
check_service smokeout-websocket

# Check disk space
DISK_USAGE=\$(df / | awk 'NR==2 {print \$5}' | sed 's/%//')
if [ \$DISK_USAGE -gt 85 ]; then
    log_message "WARNING: Disk usage is \${DISK_USAGE}%"
fi

# Check memory usage
MEM_USAGE=\$(free | awk 'NR==2{printf "%.0f", \$3/\$2*100}')
if [ \$MEM_USAGE -gt 85 ]; then
    log_message "WARNING: Memory usage is \${MEM_USAGE}%"
fi

# Check database connectivity
if ! mysql -h ${DB_HOST} -u smokeout_admin -p${db_password} -e "SELECT 1" >/dev/null 2>&1; then
    log_message "ERROR: Cannot connect to database"
fi

# Check Redis connectivity
if ! redis-cli -h ${REDIS_HOST} -p 6379 -a ${redis_auth_token} ping >/dev/null 2>&1; then
    log_message "ERROR: Cannot connect to Redis"
fi

log_message "Health check completed"
EOF

chmod +x /home/ec2-user/monitor.sh
chown ec2-user:ec2-user /home/ec2-user/monitor.sh

# Add monitoring script to crontab
echo "*/5 * * * * /home/ec2-user/monitor.sh" | crontab -u ec2-user -

# Start services
systemctl start php-fpm
systemctl enable php-fpm

systemctl start nginx
systemctl enable nginx

systemctl enable smokeout-websocket

# Create application logs directory
mkdir -p /var/www/smokeout-nyc/logs
chown -R ec2-user:ec2-user /var/www/smokeout-nyc/logs

# Install fail2ban for security
yum install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# Configure fail2ban
cat > /etc/fail2ban/jail.d/nginx.conf << EOF
[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 5
bantime = 600
EOF

systemctl reload fail2ban

# Final message
log_message() {
    echo "[\$(date)] \$1" >> /var/log/smokeout-setup.log
}

log_message "SmokeoutNYC instance setup completed successfully"
log_message "Environment: ${ENVIRONMENT}"
log_message "Database: ${DB_HOST}"
log_message "Redis: ${REDIS_HOST}"
log_message "S3 Bucket: ${S3_BUCKET}"

echo "Setup complete! Instance is ready for deployment." > /tmp/setup-complete