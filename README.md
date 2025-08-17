# SmokeoutNYC v2.0

A comprehensive web application for tracking smoke shop closures and Operation Smokeout enforcement in New York City.

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

### 🏷️ Product Gallery
- **Strain Database**: Searchable cannabis product catalog
- **Store Integration**: Products linked to specific stores
- **Admin Approval**: Moderated content system
- **Advanced Search**: Filter by type, THC/CBD content, price

## Technology Stack

### Backend
- **Node.js** with **Express.js** and **TypeScript**
- **PostgreSQL** database with **Prisma ORM**
- **Redis** for caching and session management
- **Socket.io** for real-time features
- **Passport.js** for authentication
- **JWT** for secure token-based auth

### Frontend
- **React.js** with **TypeScript**
- **Tailwind CSS** for styling
- **Google Maps API** / **Mapbox** for mapping
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
