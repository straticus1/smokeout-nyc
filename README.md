# 🏪 SmokeoutNYC v2.2 - Cannabis Industry Platform

[![Version](https://img.shields.io/badge/version-2.2.0-blue.svg)](https://github.com/user/smokeout_nyc)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://php.net)
[![Node.js](https://img.shields.io/badge/node.js-16%2B-green.svg)](https://nodejs.org)
[![React](https://img.shields.io/badge/react-18%2B-blue.svg)](https://reactjs.org)

A comprehensive full-stack web application ecosystem for tracking smoke shop closures, Operation Smokeout enforcement, with advanced AI risk assessment, political engagement, and gamification features.

**🎯 What makes SmokeoutNYC unique:** The world's first cannabis platform combining enforcement tracking, AI risk assessment, immersive gaming with realistic impairment mechanics, political engagement, and comprehensive business intelligence.

## Features

### 🔐 Authentication & User Management
- **Multi-factor Authentication**: Username/password + OAuth2 (Google, Facebook)
- **Role-based Access Control**: User, Store Owner, Admin, Super Admin
- **Account Management**: Email verification, password reset, profile management
- **Avatar Support**: Upload and manage user profile pictures

### 🏪 Store Management
- **Interactive Map**: View all smoke shops with status indicators
- **Store Profiles**: Detailed information, images, hours, contact details
- **Owner Claims**: Store owners can claim and manage their listings
- **Status Tracking**: Open, Closed (Operation Smokeout), Closed (Other), Reopened

### 📰 News System
- **Article Management**: Create, edit, and publish news articles
- **Rich Content**: Support for images, formatted text, and SEO metadata
- **Search & Filter**: Find articles by keywords and categories
- **Public API**: Integration with existing PHP frontend

### 🛡️ Security Features
- **XSS Protection**: Server-side content sanitization with DOMPurify
- **SQL Injection Prevention**: Prisma ORM with parameterized queries
- **Rate Limiting**: API endpoint protection against abuse
- **Input Validation**: Comprehensive data validation with Joi
- **Audit Logging**: Complete admin action tracking

### 👑 Admin Control Panel
- **User Management**: Suspend, activate, delete users individually or in bulk
- **Bulk Operations**: CSV/TSV import for mass user operations
- **News Management**: Full CRUD operations for articles
- **System Monitoring**: Audit logs, user activity, system statistics
- **Mass Communication**: Email and in-app messaging to all users

### 💰 Donation System
- **PayPal Integration**: Secure donation processing
- **Bitcoin Support**: Cryptocurrency donation acceptance
- **Donor Privacy**: Anonymous and named donation options
- **Financial Tracking**: Complete donation history and statistics

### 🗺️ Data Collection
- **Automated Scraping**: Operation Smokeout data collection
- **News Monitoring**: Automatic detection of closure announcements
- **Geocoding**: Address-to-coordinates conversion
- **Data Validation**: Manual review and verification system

### 💬 Real-time Features
- **Live Chat**: Global chat system for users
- **Online Users**: Real-time user count display
- **Instant Notifications**: Admin broadcasts and system alerts
- **Socket.io Integration**: WebSocket-based real-time communication

### 🔔 Advanced AI-Powered Notification System (Phase 1)
- **Smart Delivery Optimization**: AI-powered timing and channel selection
  - Machine learning algorithms analyze user behavior patterns
  - Optimal delivery timing based on engagement history
  - Automatic channel selection (in-app, email, push, SMS, Discord, Slack)
- **Intelligent Content Personalization**: Context-aware notification content
  - Risk-level appropriate messaging
  - User preference-based content adaptation
  - Personalized recommendations and insights
- **Advanced Analytics Dashboard**: Comprehensive notification performance tracking
  - Engagement rate analysis and optimization suggestions
  - Channel preference insights and effectiveness metrics
  - AI impact measurement and improvement tracking
- **Behavioral Pattern Analysis**: Deep insights into user notification preferences
  - Peak engagement hour identification
  - Channel effectiveness by time of day
  - Predictive analysis for future optimization
- **Quiet Hours and Preferences**: Granular user control over notification delivery
  - Configurable quiet hours with timezone awareness
  - Category-based notification preferences
  - Frequency controls (immediate, batched, digest)
- **Multi-Channel Delivery**: Support for 6 different delivery channels
  - In-app notifications with rich content
  - Email with HTML templates and personalization
  - Push notifications for mobile devices
  - SMS for urgent alerts
  - Discord and Slack integration for community notifications
- **AI Insights and Recommendations**: Machine learning-powered optimization
  - User behavior pattern recognition
  - Delivery timing optimization suggestions
  - Channel effectiveness analysis
  - Predictive engagement modeling

### 🎮 Enhanced Multiplayer Gaming System
- **Cannabis Growing Simulation**: Plant strains, harvest products, manage grow operations
- **Consumption Mechanics**: Realistic impairment system affecting gameplay
- **Product Processing**: Convert harvested plants into various product types
- **Market Dynamics**: Smoke shop and dealer sales with risk/reward mechanics
- **Impairment Consequences**: Mistakes and reduced performance when impaired
- **Premium Features**: Token-based and real-money premium upgrades
- **Challenge System**: Daily/weekly challenges with rewards
- **Loyalty Program**: Points and rewards for consistent gameplay
- **Achievement System**: Unlockable achievements and progression tracking
- **Economic Simulation**: Dynamic pricing and market conditions

#### 🏭 New Multiplayer Features (Phase 1)
- **Guild System**: Create, join, and manage gaming guilds with role-based permissions
  - Guild types: Casual, Competitive, Professional
  - Member management with leadership roles
  - Guild-specific activities and achievements
- **Cooperative Growing Operations**: Team-based cannabis cultivation projects
  - Investment pooling for larger operations
  - Shared risk and reward distribution
  - Collaborative decision-making on strain selection and growing methods
- **Player-to-Player Trading**: Comprehensive marketplace for game assets
  - Secure trading system with escrow functionality
  - Item listings with expiration dates and pricing
  - Trade history and reputation tracking
- **Competitive Gaming**: Tournament and competition system
  - Registration-based competitions with entry fees
  - Prize pools and leaderboard tracking
  - Various competition formats (growing contests, profit challenges)
- **Real-time Social Features**: Enhanced player interaction capabilities
  - Friend systems and social networking
  - Player status tracking and activity feeds
  - Guild chat and communication tools

### 🤖 AI Risk Assistant with Natural Language Explanations
- **Conversational AI Interface**: Natural language chat system for risk assessment queries
- **Plain-English Risk Explanations**: Complex risk factors explained in simple, understandable terms
- **Personalized Recommendations**: AI-generated suggestions based on user profile and risk tolerance
- **Interactive Risk Insights**: Real-time analysis with actionable advice and next steps
- **Context-Aware Responses**: AI considers user history, current market conditions, and business context
- **Persistent Chat History**: Conversation tracking with context retention across sessions
- **Smart Risk Visualization**: Color-coded risk levels with intuitive icons and detailed explanations
- **Multi-Factor Risk Analysis**: Comprehensive dispensary location risk assessment using:
  - Location proximity to schools, churches, and parks
  - Local crime rates and demographic analysis
  - Regulatory environment scoring
  - Market saturation and competition analysis
  - Enforcement activity tracking
  - Zoning compliance verification
- **Advanced Closure Risk Scoring**: Business closure prediction with 8 weighted factors:
  - Financial distress analysis (25% weight)
  - Regulatory violation tracking (20% weight)
  - Enforcement pressure monitoring (15% weight)
  - Market decline assessment (12% weight)
  - Operational issues detection (10% weight)
  - Legal challenges evaluation (8% weight)
  - Supply chain disruption analysis (6% weight)
  - Community opposition measurement (4% weight)
- **Time-Based Risk Modeling**: Adjustable timeframe analysis (6-24+ months)
- **Emergency Action Plans**: Critical recommendations for high-risk businesses
- **Real-time Intelligence**: Live monitoring of news alerts, enforcement activity, and regulatory changes

### 💰 Political Donation System
- **Multi-Payment Processing**: Credit card and PayPal integration
- **Political Campaign Integration**: Direct donations to politicians and campaigns
- **Donor Privacy**: Anonymous and named donation options with FEC compliance
- **Fee Transparency**: Processing fee breakdown and net amount calculation
- **Campaign Finance Compliance**: Address collection for reporting requirements
- **Donation Settings**: Per-politician customizable limits and messaging
- **Financial Tracking**: Complete donation history and statistics

### 🏷️ Product & Strain Database
- **Comprehensive Strain Catalog**: Detailed cannabis strain information
- **Store Integration**: Products linked to specific stores and locations
- **Admin Approval**: Moderated content system with quality control
- **Advanced Search**: Filter by type, THC/CBD content, price, effects
- **User Reviews**: Community-driven product ratings and feedback
- **Availability Tracking**: Real-time stock and availability updates

## Technology Stack

### Backend
- **Node.js** with **Express.js** and **TypeScript**
- **PHP** backend with comprehensive API system
- **PostgreSQL** database with **Prisma ORM**
- **Redis** for caching and session management
- **Socket.io** for real-time features
- **Multi-Provider OAuth2**: Google, Facebook, Microsoft, Twitter
- **JWT** and **PHP Sessions** for authentication
- **Advanced Game Engine**: Complex mechanics and state management
- **AI Risk Engine**: Machine learning-powered risk assessments

### Frontend
- **React.js** with **TypeScript**
- **Tailwind CSS** for responsive styling
- **Universal Map System**: MapLibre GL, Google Maps, and Leaflet integration
- **Headless UI** and **Heroicons** for accessible components
- **React Hot Toast** for user notifications
- **Axios** for API communication
- **Context API** for state management
- **Socket.io Client** for real-time features

### Infrastructure & DevOps
- **AWS** cloud deployment with auto-scaling
- **Terraform** for infrastructure as code (IaC)
- **Ansible** for configuration management and deployment automation
- **Application Load Balancer** with health checks
- **RDS MySQL** with automated backups
- **S3** for file storage and static assets
- **CloudWatch** for monitoring and logging
- **Auto Scaling Groups** for high availability
- **VPC** with public/private subnets
- **IAM roles** with least privilege access

### Security & Monitoring
- **DOMPurify** for XSS prevention
- **Helmet.js** for security headers
- **Winston** for logging
- **Rate limiting** with Redis
- **Comprehensive audit trails**

## Quick Start

See [QUICKSTART.md](QUICKSTART.md) for detailed setup instructions.

### Local Development

1. **Clone and setup**
   ```bash
   git clone <repository-url>
   cd smokeout_nyc
   ./setup.sh
   ```

2. **Start development environment**
   ```bash
   ./dev.sh
   ```

3. **Run tests**
   ```bash
   ./test.sh
   ```

### Automated Setup

The project includes comprehensive automation scripts:
- `setup.sh` - Complete environment setup
- `dev.sh` - Start all development servers
- `test.sh` - Validate installation and setup

## Environment Configuration

The project includes example environment files:
- `.env.example` - Main application configuration
- `client/.env.example` - Frontend configuration
- `terraform/terraform.tfvars.example` - Infrastructure configuration

### Required Environment Variables

- **Database**: MySQL connection details
- **JWT**: Secret key for token signing
- **OAuth**: Google and Facebook app credentials
- **Email**: SMTP configuration for notifications
- **Payment**: PayPal and Stripe settings
- **Maps**: Google Maps API key
- **AWS**: S3 bucket and CloudWatch configuration
- **External APIs**: OpenAI, News API keys

## API Documentation

### Authentication Endpoints
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `GET /api/auth/google` - Google OAuth
- `GET /api/auth/facebook` - Facebook OAuth
- `GET /api/auth/microsoft` - Microsoft OAuth
- `GET /api/auth/twitter` - Twitter OAuth
- `POST /api/auth/forgot-password` - Password reset request
- `POST /api/auth/reset-password` - Password reset confirmation

### Store Endpoints
- `GET /api/stores` - List stores with filtering
- `GET /api/stores/:id` - Get store details
- `POST /api/stores` - Create store (authenticated)
- `PUT /api/stores/:id` - Update store (owner/admin)
- `POST /api/stores/:id/claim` - Claim store ownership

### News Endpoints
- `GET /api/news` - Public news articles
- `GET /api/news/:slug` - Single article by slug
- `POST /api/news/admin` - Create article (admin)
- `PUT /api/news/admin/:id` - Update article (admin)
- `DELETE /api/news/admin/:id` - Delete article (admin)

### Game Endpoints
- `GET /api/game/player` - Get player profile and stats
- `GET /api/game/strains` - Get available strains for player level
- `POST /api/game/plants` - Plant new seed
- `PUT /api/game/plants/:id` - Harvest plant
- `GET /api/game/locations` - Get available growing locations
- `POST /api/game/sales` - Sell harvested plant
- `GET /api/game/shop` - Get token packages
- `POST /api/game/shop` - Purchase tokens
- `GET /api/game/achievements` - Get player achievements
- `GET /api/game/market/:location` - Get market prices

### Advanced Game Endpoints
- `POST /api/game/consume` - Consume cannabis product
- `GET /api/game/consume` - Get current impairment status
- `POST /api/game/products` - Create product from harvested plant
- `GET /api/game/smokeshops` - Get available smoke shops
- `POST /api/game/smokeshops/:id/sell` - Bulk sell to smoke shop
- `GET /api/game/dealers` - Get available dealers
- `POST /api/game/dealers/:id/sell` - Sell to dealer (risky)
- `GET /api/game/premium` - Get premium features
- `POST /api/game/premium` - Purchase premium feature
- `GET /api/game/challenges` - Get active challenges
- `POST /api/game/challenges/:id/claim` - Claim challenge reward
- `GET /api/game/loyalty` - Get loyalty status
- `POST /api/game/loyalty/redeem` - Redeem loyalty points
- `GET /api/game/mistakes` - Get player mistake history

### AI Risk Assessment Endpoints
- `GET /api/ai-risk/dispensary` - Get dispensary risk assessment
- `POST /api/ai-risk/dispensary` - Batch risk assessment
- `GET /api/ai-risk/closure` - Get business closure risk assessment
- `POST /api/ai-risk/closure` - Batch closure risk assessment (up to 5 locations)
- `GET /api/ai-risk/enforcement` - Get enforcement risk for area
- `GET /api/ai-risk/nationwide` - Get nationwide risk analysis
- `GET /api/ai-risk/realtime` - Get real-time risk updates

### Political Donation Endpoints
- `POST /api/donations/donate` - Make political donation
- `POST /api/donations/process` - Process payment
- `GET /api/donations/:politician/settings` - Get donation settings
- `PUT /api/donations/:politician/settings` - Update donation settings
- `GET /api/donations/history` - Get user donation history
- `GET /api/donations/:politician/stats` - Get donation statistics
- `GET /api/donations/recent` - Get recent donations

### Membership Endpoints
- `GET /api/membership/tiers` - Get available membership tiers
- `POST /api/membership/subscribe` - Subscribe to membership
- `GET /api/membership/status` - Get current membership status
- `POST /api/membership/cancel` - Cancel membership
- `GET /api/membership/usage` - Get usage statistics

### NFT Integration Endpoints
- `GET /api/nft/genetics` - Get available genetics NFTs
- `GET /api/nft/genetics/:id` - Get specific genetics NFT details
- `POST /api/nft/genetics/:id` - Mint genetics NFT
- `GET /api/nft/marketplace` - Get marketplace listings
- `POST /api/nft/marketplace` - List NFT for sale
- `GET /api/nft/collection` - Get user's NFT collection
- `POST /api/nft/breeding` - Breed new genetics from NFTs

### AR/VR Visualization Endpoints
- `GET /api/ar/plants` - Get AR-enabled plants
- `GET /api/ar/plants/:id` - Get AR model for specific plant
- `POST /api/ar/plants/:id/capture` - Capture AR session data
- `GET /api/ar/rooms` - Get AR room environments
- `POST /api/ar/rooms/:id/customize` - Customize AR room
- `GET /api/ar/sessions` - Get AR session history
- `GET /api/ar/models` - Get available AR models

### Social Trading Endpoints
- `GET /api/social-trading/strategies` - Get public trading strategies
- `GET /api/social-trading/strategies/:id` - Get specific strategy
- `POST /api/social-trading/strategies` - Create new strategy
- `PUT /api/social-trading/strategies/:id` - Update strategy
- `POST /api/social-trading/copy/:id` - Copy trading strategy
- `GET /api/social-trading/leaderboard` - Get performance leaderboard
- `GET /api/social-trading/portfolio` - Get user's trading portfolio

### Voice Assistant Endpoints
- `POST /api/voice/command` - Process voice command
- `GET /api/voice/capabilities` - Get available voice commands
- `POST /api/voice/preferences` - Update voice settings
- `GET /api/voice/history` - Get voice command history

### Enterprise & White Label Endpoints
- `GET /api/white-label/config` - Get branding configuration
- `POST /api/white-label/config` - Update branding
- `GET /api/white-label/analytics` - Get usage analytics
- `POST /api/white-label/deployment` - Deploy custom instance

### Professional Services Endpoints
- `GET /api/legal-network/attorneys` - Find cannabis attorneys
- `POST /api/legal-network/consultation` - Request consultation
- `GET /api/insurance-marketplace/quotes` - Get insurance quotes
- `POST /api/insurance-marketplace/application` - Submit application
- `GET /api/accounting-tools/reports` - Generate financial reports
- `POST /api/accounting-tools/transactions` - Record transactions

### Advanced Data Service Endpoints
- `GET /api/data-service/export` - Export user data
- `POST /api/data-service/import` - Import data
- `GET /api/data-service/analytics` - Get advanced analytics
- `POST /api/data-service/webhook` - Register webhook

### Premium Features Endpoints
- `GET /api/premium/features` - Get available premium features
- `POST /api/premium/activate` - Activate premium feature
- `GET /api/premium/status` - Get premium subscription status
- `POST /api/premium/cancel` - Cancel premium subscription

### User Interface Customization Endpoints
- `GET /api/ui/themes` - Get available themes
- `POST /api/ui/theme` - Apply custom theme
- `GET /api/ui/layout` - Get dashboard layout
- `POST /api/ui/layout` - Save custom layout

### Admin Endpoints
- `GET /api/admin/dashboard` - System statistics
- `GET /api/admin/users` - User management
- `POST /api/admin/users/:id/suspend` - Suspend user
- `POST /api/admin/users/bulk-action` - Bulk user operations
- `POST /api/admin/users/message-all` - Mass messaging
- `GET /api/admin/audit-logs` - Audit trail

## Deployment

### AWS Production Deployment

The project includes complete AWS infrastructure automation with Terraform and Ansible.

#### Prerequisites

1. **AWS CLI configured** with appropriate permissions
2. **Terraform** installed (v1.0+)
3. **Ansible** installed (v4.0+)
4. **SSH key pair** created in AWS

#### Infrastructure Setup

1. **Configure Terraform variables**
   ```bash
   cd terraform
   cp terraform.tfvars.example terraform.tfvars
   # Edit terraform.tfvars with your values
   ```

2. **Deploy infrastructure**
   ```bash
   terraform init
   terraform plan
   terraform apply
   ```

#### Application Deployment

1. **Update Ansible inventory** with EC2 instance IPs from Terraform output
   ```bash
   cd ansible
   # Update inventory.yml with instance details
   ```

2. **Deploy application**
   ```bash
   ansible-playbook deploy.yml
   ```

#### Infrastructure Components

- **VPC**: Multi-AZ with public/private subnets
- **Application Load Balancer**: With health checks
- **Auto Scaling Group**: 1-3 EC2 instances
- **RDS MySQL**: Multi-AZ with automated backups
- **S3 Bucket**: For file uploads and static assets
- **CloudWatch**: Comprehensive monitoring and logging
- **Security Groups**: Least privilege network access
- **IAM Roles**: Secure service-to-service communication

#### Terraform Outputs

After deployment, Terraform provides:
- Load balancer DNS name
- Database endpoint
- S3 bucket information
- Application URL
- Health check endpoint

#### Monitoring & Maintenance

- **Health checks**: Automated via ALB and CloudWatch
- **Logs**: Centralized in CloudWatch Logs
- **Metrics**: CPU, memory, disk, and application metrics
- **Scaling**: Automatic based on CPU utilization
- **Backups**: Daily RDS snapshots with 7-day retention

#### Cost Optimization

- **Development**: Use `t3.micro` instances and `db.t3.micro`
- **Production**: Scale up to `t3.small` or larger as needed
- **Storage**: Start with minimal allocations, auto-scaling enabled
- **Monitoring**: CloudWatch free tier covers basic monitoring

### Local Development Deployment

```bash
# Start all services locally
./dev.sh

# Access application
# Frontend: http://localhost:3000
# PHP API: http://localhost:8000
# Node.js API: http://localhost:3001
```

### Docker Deployment (Alternative)

```bash
# Coming soon - Docker Compose configuration
docker-compose up -d
```

## Security Considerations

### Application Security
1. **Input Sanitization**: All user input is sanitized server-side
2. **Authentication**: Multi-layer auth with JWT and session validation
3. **Authorization**: Role-based access control throughout
4. **Data Protection**: Encrypted sensitive data storage
5. **Audit Trails**: Complete logging of admin actions
6. **Rate Limiting**: Protection against abuse and DoS attacks

### Infrastructure Security
1. **VPC Isolation**: Private subnets for database and internal services
2. **Security Groups**: Restrictive firewall rules
3. **IAM Roles**: Least privilege access principles
4. **Encryption**: Data encrypted at rest and in transit
5. **SSL/TLS**: HTTPS enforced for all external communications
6. **Monitoring**: Real-time security monitoring with CloudWatch
7. **Fail2ban**: Automatic IP blocking for suspicious activity
8. **Regular Updates**: Automated security patches via Ansible

## Testing

The project includes comprehensive testing tools:

```bash
# Run all tests
./test.sh

# Test specific components
php -l api/*.php  # PHP syntax check
npm test          # JavaScript/React tests
```

### Test Coverage

- **Environment validation**: Configuration and dependencies
- **Database connectivity**: Connection and schema validation
- **API endpoints**: Health checks and basic functionality
- **File permissions**: Security and access validation
- **Service availability**: All required services running

## Performance Monitoring

### Local Development
- Health check endpoint: `http://localhost:8000/api/health.php`
- Application logs: `logs/` directory
- Development tools: Browser dev tools, PHP error logs

### Production Monitoring
- **CloudWatch Metrics**: CPU, memory, disk, network
- **CloudWatch Logs**: Application and system logs
- **Health Checks**: Load balancer health monitoring
- **Alerts**: Automated notifications for issues
- **Performance Insights**: RDS performance monitoring

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests: `./test.sh`
5. Submit a pull request

### Development Guidelines
- Follow existing code style and patterns
- Add tests for new functionality
- Update documentation as needed
- Ensure all tests pass before submitting
- Use descriptive commit messages

## License

MIT License - see LICENSE file for details

## Support

For support, email info@smokeout.nyc or create an issue in the repository.

---

**SmokeoutNYC v2.0** - Tracking NYC smoke shop closures with transparency and community engagement.
