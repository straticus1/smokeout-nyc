# 🚀 SmokeoutNYC v2.0 - Quick Start Guide

## Prerequisites

- **Node.js** 16+ ([Download](https://nodejs.org/))
- **PHP** 8.0+ ([Download](https://php.net/downloads.php))
- **Composer** ([Download](https://getcomposer.org/download/))
- **MySQL/MariaDB** 8.0+
- **Git**

## ⚡ 5-Minute Setup

### 1. Clone and Setup
```bash
git clone <repository-url>
cd smokeout_nyc
chmod +x setup.sh
./setup.sh
```

The setup script will:
- ✅ Check system requirements
- ✅ Install all dependencies
- ✅ Configure environment files
- ✅ Set up the database
- ✅ Create default admin user
- ✅ Build the frontend

### 2. Start Development
```bash
# Option 1: Use the dev script (recommended)
chmod +x dev.sh
./dev.sh

# Option 2: Manual start
npm run dev
```

### 3. Access the Application
- **Frontend**: http://localhost:3000
- **PHP API**: http://localhost:8000
- **Node.js API**: http://localhost:3001 (if available)

### 4. Login
- **Username**: `admin`
- **Password**: `admin123`
- ⚠️ **Change these credentials immediately!**

## 🎯 Key Endpoints to Test

### Authentication
```bash
# Register new user
POST http://localhost:8000/api/auth/register
{
  "username": "testuser",
  "email": "test@example.com",
  "password": "password123"
}

# Login
POST http://localhost:8000/api/auth/login
{
  "email": "admin@smokeout.nyc",
  "password": "admin123"
}
```

### AI Risk Assessment
```bash
# Get dispensary risk assessment
GET http://localhost:8000/api/ai-risk/dispensary?lat=40.7128&lng=-74.0060&city=New%20York&state=NY

# Get closure risk assessment
GET http://localhost:8000/api/ai-risk/closure?lat=40.7128&lng=-74.0060&city=New%20York&state=NY&timeframe=12
```

### Game System
```bash
# Get player profile (requires authentication)
GET http://localhost:8000/api/game/player
Authorization: Bearer YOUR_SESSION_TOKEN

# Get available strains
GET http://localhost:8000/api/game/strains
Authorization: Bearer YOUR_SESSION_TOKEN
```

## 📁 Project Structure

```
smokeout_nyc/
├── api/                    # PHP API endpoints
│   ├── auth.php           # Authentication endpoints
│   ├── game.php           # Game system endpoints
│   ├── ai_risk_meter.php  # AI risk assessment
│   └── helpers/           # Helper functions
├── client/                # React frontend
│   ├── src/
│   │   ├── components/    # React components
│   │   ├── pages/         # Page components
│   │   └── contexts/      # React contexts
│   └── public/            # Static assets
├── database/              # Database schemas
├── config/                # Configuration files
├── scripts/               # Utility scripts
├── .env                   # Environment configuration
├── setup.sh              # Setup script
└── dev.sh                # Development server script
```

## 🔧 Configuration

### Environment Variables (.env)
```bash
# Database
DB_HOST=localhost
DB_NAME=smokeout_nyc
DB_USER=your_user
DB_PASS=your_password

# JWT
JWT_SECRET=your-secret-key

# OAuth (optional)
GOOGLE_CLIENT_ID=your-google-client-id
FACEBOOK_APP_ID=your-facebook-app-id

# Maps (optional)
GOOGLE_MAPS_API_KEY=your-maps-api-key
```

### Client Environment (client/.env)
```bash
REACT_APP_API_BASE_URL=http://localhost:8000/api
REACT_APP_GOOGLE_MAPS_API_KEY=your-maps-api-key
REACT_APP_WEBSOCKET_URL=http://localhost:3001
```

## 🔌 External API Integration (Phase 1)

### Required API Keys

For full functionality, you'll need to obtain API keys from these services:

#### Essential Services (Phase 1)
```bash
# Copy from server/.env.external-apis to your .env file

# AI Features - OpenAI (Required for AI recommendations)
OPENAI_API_KEY=sk-your_openai_api_key_here

# Payment Processing - Stripe (Required for premium features)
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key_here
STRIPE_SECRET_KEY=sk_test_your_secret_key_here

# Geolocation - Google Maps (Enhanced location features)
GOOGLE_MAPS_API_KEY=AIzaSy_your_google_maps_api_key_here

# Notifications - Twilio SMS (Optional)
TWILIO_ACCOUNT_SID=AC_your_account_sid_here
TWILIO_AUTH_TOKEN=your_auth_token_here
TWILIO_PHONE_NUMBER=+1234567890

# Email - SendGrid (Optional)
SENDGRID_API_KEY=SG._your_sendgrid_api_key_here

# Push Notifications - Firebase (Optional)
FIREBASE_SERVER_KEY=AAAA_your_firebase_server_key_here
```

#### Getting API Keys

**OpenAI (AI Recommendations)**
1. Visit [OpenAI API](https://openai.com/api/)
2. Create account → API Keys → Create new key
3. Start with $5 credit (pay-as-you-go)

**Stripe (Payments)**
1. Visit [Stripe Dashboard](https://dashboard.stripe.com/)
2. Create account → Developers → API Keys
3. Use test keys for development

**Google Maps (Geolocation)**
1. Visit [Google Cloud Console](https://console.cloud.google.com/)
2. Create project → APIs & Services → Enable Maps/Places APIs
3. Create credentials → API Key

**Twilio (SMS Notifications)**
1. Visit [Twilio Console](https://console.twilio.com/)
2. Create account → Get phone number
3. Find Account SID and Auth Token in dashboard

#### Optional Features
```bash
# Weather Integration
OPENWEATHERMAP_API_KEY=your_openweathermap_api_key_here

# News Integration
NEWS_API_KEY=your_news_api_key_here

# Feature Flags (Enable/Disable features)
ENABLE_AI_RECOMMENDATIONS=true
ENABLE_REAL_TIME_NOTIFICATIONS=true
ENABLE_PAYMENT_PROCESSING=true
ENABLE_SMS_NOTIFICATIONS=false
```

## 🎮 Gaming System Features

### Core Mechanics
- **Growing Simulation**: Plant → Grow → Harvest → Process → Sell
- **Impairment System**: Consumption affects gameplay performance
- **Market Dynamics**: Dynamic pricing based on quality and demand
- **Mistakes**: Higher impairment = higher chance of costly mistakes

### Available Strains (Default)
- **Northern Lights** (Beginner, $10)
- **Sour Diesel** (Intermediate, $15)
- **Girl Scout Cookies** (Intermediate, $20)
- **White Widow** (Beginner, $12)
- **OG Kush** (Expert, $25)

### New Phase 1 Features
#### Advanced Analytics Dashboard
- Real-time user metrics
- AI-powered insights
- Custom reporting
- Performance tracking
- Revenue analytics

#### External API Integrations
- **AI Recommendations**: OpenAI-powered business compliance advice
- **Payment Processing**: Stripe integration for premium subscriptions
- **Real-time Features**: WebSocket server for live updates
- **Notifications**: SMS, Email, and Push notifications
- **Geolocation**: Enhanced address validation and mapping
- **Weather Integration**: Location-based weather data
- **News Feed**: Relevant regulatory news and updates

### Growing Locations
- **Small Tent** (2 plants, Free)
- **Medium Tent** (4 plants, $500)
- **Grow Room** (8 plants, $2000)
- **Greenhouse** (12 plants, $5000)
- **Outdoor Garden** (6 plants, $100)
- **Outdoor Field** (20 plants, $10000)

## 🤖 AI Risk Assessment

### Risk Factors Analyzed
1. **Financial Distress** (25% weight)
2. **Regulatory Violations** (20% weight)
3. **Enforcement Pressure** (15% weight)
4. **Market Decline** (12% weight)
5. **Operational Issues** (10% weight)
6. **Legal Challenges** (8% weight)
7. **Supply Chain Disruption** (6% weight)
8. **Community Opposition** (4% weight)

### Risk Levels
- **Very Low** (0-20%)
- **Low** (20-40%)
- **Medium** (40-60%)
- **High** (60-80%)
- **Very High** (80-100%)

## 🚨 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check if MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u your_user -p your_database
```

**PHP Server Won't Start**
```bash
# Check PHP version
php --version

# Check for syntax errors
php -l api/auth.php
```

**React Build Fails**
```bash
# Clear cache and reinstall
cd client
rm -rf node_modules package-lock.json
npm install
```

**Permission Denied**
```bash
# Fix script permissions
chmod +x setup.sh dev.sh

# Fix directory permissions
chmod -R 755 uploads logs tmp
```

### Log Files
- **PHP Server**: `logs/php-server.log`
- **React Dev**: `logs/react-server.log`
- **Node.js Server**: `logs/node-server.log`
- **MySQL**: `/var/log/mysql/error.log`

## 📊 Default Data

### Admin User
- **Username**: `admin`
- **Email**: `admin@smokeout.nyc`
- **Password**: `admin123`
- **Role**: `admin`

### Sample Politicians
- **Eric Adams** (Mayor, NYC)
- **Alexandria Ocasio-Cortez** (Representative)
- **Chuck Schumer** (Senator)

### Membership Tiers
- **Free**: 5 AI assessments/month
- **Pro** ($9.99/month): 100 AI assessments/month
- **Premium** ($29.99/month): Unlimited access

## 🔗 Useful Commands

```bash
# Database operations
mysql -u smokeout_user -p smokeout_nyc < database/complete_schema.sql

# Check API health
curl http://localhost:8000/api/health.php

# Test external API health
curl http://localhost:8000/api/external/health

# Start real-time WebSocket server
cd realtime-server && npm start

# View real-time logs
tail -f logs/php-server.log
tail -f logs/realtime-server.log

# Stop all development servers
pkill -f "php -S"
pkill -f "npm start"
pkill -f "node realtime-server"

# Reset database
mysql -u smokeout_user -p -e "DROP DATABASE smokeout_nyc; CREATE DATABASE smokeout_nyc;"
mysql -u smokeout_user -p smokeout_nyc < database/complete_schema.sql

# Test AI recommendations
curl -X POST http://localhost:8000/api/ai/recommendations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"business_type":"restaurant","address":"123 Main St","risk_factors":["recent_violation"]}'

# Test payment processing
curl -X POST http://localhost:8000/api/payments/process \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"amount":29.99,"currency":"usd","payment_method_id":"pm_test_card"}'
```

## 🎯 Next Steps

### Local Development
1. **Update API Keys**: Copy from `server/.env.external-apis` to your `.env` files
2. **Configure External Services**: 
   - Set up OpenAI account for AI recommendations
   - Configure Stripe for payment processing
   - Enable Google Maps for enhanced geolocation
   - Set up Twilio for SMS notifications (optional)
3. **Start Real-time Server**: `cd realtime-server && npm start` 
4. **Test Advanced Features**:
   - Visit `/analytics` for the advanced dashboard
   - Try AI-powered recommendations
   - Test real-time notifications
   - Process test payments with Stripe
5. **Customize Branding**: Update logos and colors
6. **Add Content**: Import smoke shop data
7. **Configure Feature Flags**: Enable/disable features in `.env`

### Production Deployment
6. **AWS Setup**: Follow the AWS deployment guide below
7. **Domain & SSL**: Configure custom domain with SSL certificate
8. **Monitoring**: Set up CloudWatch alerts and dashboards

## 📞 Getting Help

- 📧 **Email**: info@smokeout.nyc
- 📚 **Documentation**: See `README.md` for detailed info
- 🐛 **Issues**: Check `logs/` directory for error logs
- 💬 **Support**: Create an issue in the repository

## 🚀 AWS Production Deployment

### Quick AWS Setup (30 minutes)

#### Prerequisites
```bash
# Install required tools
brew install terraform  # macOS
brew install ansible    # macOS

# Or on Ubuntu/Debian
sudo apt-get update
sudo apt-get install terraform ansible

# Configure AWS CLI
aws configure
# Enter your AWS Access Key ID, Secret, Region (us-east-1), and output format (json)
```

#### Step 1: Create AWS Key Pair
```bash
# Create SSH key pair in AWS Console:
# AWS Console → EC2 → Key Pairs → Create Key Pair
# Name: smokeout-nyc-keypair
# Download the .pem file to ~/.ssh/
```

#### Step 2: Deploy Infrastructure
```bash
# Configure Terraform
cd terraform
cp terraform.tfvars.example terraform.tfvars

# Edit terraform.tfvars with your values:
vim terraform.tfvars
```

**Required terraform.tfvars configuration:**
```hcl
aws_region = "us-east-1"
project_name = "smokeout-nyc"
environment = "production"  # or "dev" for testing

# REQUIRED: Your AWS key pair name
key_pair_name = "smokeout-nyc-keypair"

# REQUIRED: Strong passwords
db_password = "YourSecureDatabasePassword123!"
jwt_secret = "your-super-secret-jwt-key-256-chars-long"

# Optional: Restrict SSH access to your IP
ssh_cidr = "YOUR.IP.ADDRESS.HERE/32"

# Instance sizing (adjust based on needs)
instance_type = "t3.small"        # Use t3.micro for dev
db_instance_class = "db.t3.micro"  # Scale up for production
```

#### Step 3: Launch Infrastructure
```bash
# Initialize and deploy
terraform init
terraform plan
terraform apply

# Note the outputs (save these!):
# - load_balancer_dns
# - database_endpoint
# - application_url
```

#### Step 4: Deploy Application
```bash
# Configure Ansible
cd ../ansible
cp ansible.cfg.example ansible.cfg

# Update inventory with EC2 instance IPs from Terraform output
vim inventory.yml

# Example inventory.yml:
# web_servers:
#   hosts:
#     web-1:
#       ansible_host: 3.85.123.45  # From Terraform output
#       ansible_user: ubuntu
```

#### Step 5: Run Deployment
```bash
# Deploy application
ansible-playbook deploy.yml

# Wait for deployment to complete (~10-15 minutes)
```

#### Step 6: Verify Deployment
```bash
# Test the application
curl http://YOUR-LOAD-BALANCER-DNS/api/health.php

# Expected response:
# {"status":"ok","timestamp":"...","services":{...}}
```

### 🌍 Accessing Your Production App

- **Application**: `http://YOUR-LOAD-BALANCER-DNS`
- **Health Check**: `http://YOUR-LOAD-BALANCER-DNS/api/health.php`
- **Admin Login**: Same as local (admin/admin123) - **Change immediately!**

### 📊 AWS Infrastructure Overview

#### What Gets Created
- **VPC**: Isolated network with public/private subnets
- **Load Balancer**: Distributes traffic across instances
- **Auto Scaling**: 1-3 EC2 instances (scales based on demand)
- **RDS MySQL**: Managed database with backups
- **S3 Bucket**: File storage for uploads
- **CloudWatch**: Monitoring and logging
- **Security Groups**: Firewall rules

#### Monthly Cost Estimate (US East)
- **Development**: ~$50-100/month
  - t3.micro instances
  - db.t3.micro database
  - Minimal storage
- **Production**: ~$200-500/month
  - t3.small+ instances
  - db.t3.small+ database
  - Enhanced monitoring

### 🔧 Production Configuration

#### Required Post-Deployment Steps

1. **Change default passwords**
   ```bash
   # SSH to EC2 instance
   ssh -i ~/.ssh/smokeout-nyc-keypair.pem ubuntu@YOUR-EC2-IP
   
   # Change admin password via web interface
   ```

2. **Configure domain (optional)**
   ```bash
   # Create Route 53 hosted zone
   # Point domain to load balancer DNS
   # Configure SSL certificate with ACM
   ```

3. **Set up monitoring alerts**
   ```bash
   # Configure CloudWatch alarms for:
   # - High CPU usage
   # - Database connections
   # - Application errors
   ```

#### Environment Variables for Production

The deployment automatically configures production environment variables. Key differences from local:

```bash
# Production .env (auto-generated)
APP_ENV=production
APP_DEBUG=false
DB_HOST=your-rds-endpoint.amazonaws.com
AWS_S3_BUCKET=smokeout-nyc-production-uploads
```

### 🚨 Production Troubleshooting

#### Common Issues

**Deployment Failed**
```bash
# Check Terraform state
terraform show

# Retry deployment
terraform apply

# Check Ansible logs
ansible-playbook deploy.yml -vv
```

**Application Not Accessible**
```bash
# Check load balancer health
aws elbv2 describe-target-health --target-group-arn YOUR-TARGET-GROUP-ARN

# Check EC2 instance status
aws ec2 describe-instances --filters "Name=tag:Name,Values=smokeout-nyc-web"

# SSH to instance and check logs
ssh -i ~/.ssh/smokeout-nyc-keypair.pem ubuntu@YOUR-EC2-IP
sudo tail -f /var/log/nginx/error.log
```

**Database Connection Issues**
```bash
# Test database connectivity from EC2
mysql -h YOUR-RDS-ENDPOINT -u admin -p smokeout_nyc

# Check security group rules
aws ec2 describe-security-groups --filters "Name=tag:Name,Values=smokeout-nyc-rds-sg"
```

#### Scaling & Optimization

**Manual Scaling**
```bash
# Update desired capacity
aws autoscaling update-auto-scaling-group \
  --auto-scaling-group-name smokeout-nyc-web-asg \
  --desired-capacity 3
```

**Database Scaling**
```bash
# Modify RDS instance class
aws rds modify-db-instance \
  --db-instance-identifier smokeout-nyc-db \
  --db-instance-class db.t3.small
```

### 💰 Cost Management

#### Cost Optimization Tips

1. **Use Reserved Instances** for production (up to 70% savings)
2. **Schedule auto-scaling** to reduce instances during low usage
3. **Enable RDS auto-scaling** for storage
4. **Use S3 lifecycle policies** to archive old uploads
5. **Monitor CloudWatch costs** and set up billing alerts

#### Cleanup (Destroy Infrastructure)

```bash
# WARNING: This will destroy everything!
cd terraform
terraform destroy

# Confirm by typing 'yes'
```

### 🔒 Security Best Practices

1. **Restrict SSH access** to your IP only
2. **Enable VPC Flow Logs** for network monitoring
3. **Use IAM roles** instead of access keys
4. **Enable CloudTrail** for API auditing
5. **Regular security updates** via Ansible
6. **Monitor suspicious activity** with CloudWatch

### 📈 Monitoring & Alerts

#### Key Metrics to Monitor
- **Application health**: HTTP 200 responses
- **Database performance**: Connection count, CPU
- **Server resources**: CPU, memory, disk space
- **Error rates**: 4xx/5xx HTTP responses
- **Response time**: Application latency

#### Setting Up Alerts
```bash
# Example CloudWatch alarm
aws cloudwatch put-metric-alarm \
  --alarm-name "SmokeoutNYC-HighCPU" \
  --alarm-description "Alert when CPU exceeds 80%" \
  --metric-name CPUUtilization \
  --namespace AWS/EC2 \
  --statistic Average \
  --period 300 \
  --threshold 80 \
  --comparison-operator GreaterThanThreshold
```

---

**Happy coding and deploying! 🚀**
