# SmokeoutNYC v2.0

A comprehensive full-stack web application ecosystem for tracking smoke shop closures, Operation Smokeout enforcement, with advanced AI risk assessment, political engagement, and gamification features.

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

### 🎮 Advanced Gaming System
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

### 🤖 AI Risk Assessment Engine
- **Dispensary Risk Analysis**: Multi-factor location risk assessment
- **Enforcement Prediction**: Real-time enforcement activity tracking
- **Regulatory Compliance**: Automated regulatory environment analysis
- **Market Intelligence**: Competition analysis and demographic insights
- **Nationwide Analytics**: State-by-state risk comparison and trends
- **Real-time Alerts**: News monitoring and regulatory change notifications
- **Membership Gating**: Tiered access based on subscription levels
- **Batch Processing**: Multi-location risk assessments
- **Historical Tracking**: Risk trend analysis and predictive modeling

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

### Infrastructure
- **AWS** cloud deployment
- **Terraform** for infrastructure as code
- **Ansible** for configuration management
- **CloudFormation** for AWS resource management

### Security & Monitoring
- **DOMPurify** for XSS prevention
- **Helmet.js** for security headers
- **Winston** for logging
- **Rate limiting** with Redis
- **Comprehensive audit trails**

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd smokeout_nyc
   ```

2. **Install dependencies**
   ```bash
   npm install
   cd client && npm install && cd ..
   ```

3. **Set up environment variables**
   ```bash
   cp env.example .env
   # Edit .env with your configuration
   ```

4. **Set up the database**
   ```bash
   npm run db:migrate
   npm run db:generate
   npm run db:seed
   ```

5. **Start the development servers**
   ```bash
   npm run dev
   ```

## Environment Configuration

Copy `env.example` to `.env` and configure:

- **Database**: PostgreSQL connection string
- **Redis**: Redis connection URL
- **JWT**: Secret key for token signing
- **OAuth**: Google and Facebook app credentials
- **Email**: SMTP configuration for notifications
- **Payment**: PayPal and Bitcoin settings
- **Maps**: Google Maps or Mapbox API keys
- **AWS**: Deployment credentials

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

### Admin Endpoints
- `GET /api/admin/dashboard` - System statistics
- `GET /api/admin/users` - User management
- `POST /api/admin/users/:id/suspend` - Suspend user
- `POST /api/admin/users/bulk-action` - Bulk user operations
- `POST /api/admin/users/message-all` - Mass messaging
- `GET /api/admin/audit-logs` - Audit trail

## Deployment

### AWS Infrastructure
```bash
# Initialize Terraform
cd infrastructure
terraform init
terraform plan
terraform apply

# Configure with Ansible
ansible-playbook -i inventory deploy.yml
```

### Docker Deployment
```bash
docker-compose up -d
```

## Security Considerations

1. **Input Sanitization**: All user input is sanitized server-side
2. **Authentication**: Multi-layer auth with JWT and session validation
3. **Authorization**: Role-based access control throughout
4. **Data Protection**: Encrypted sensitive data storage
5. **Audit Trails**: Complete logging of admin actions
6. **Rate Limiting**: Protection against abuse and DoS attacks

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

MIT License - see LICENSE file for details

## Support

For support, email info@smokeout.nyc or create an issue in the repository.

---

**SmokeoutNYC v2.0** - Tracking NYC smoke shop closures with transparency and community engagement.
