# Terraform Variables for SmokeoutNYC Infrastructure

variable "project_name" {
  description = "Name of the project"
  type        = string
  default     = "smokeout-nyc"
}

variable "environment" {
  description = "Environment name (dev, staging, production)"
  type        = string
  default     = "production"
  
  validation {
    condition     = contains(["dev", "staging", "production"], var.environment)
    error_message = "Environment must be one of: dev, staging, production."
  }
}

variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "us-east-1"
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
  default     = "smokeout.nyc"
}

variable "allowed_cidr_blocks" {
  description = "CIDR blocks allowed for SSH access"
  type        = string
  default     = "0.0.0.0/0" # Restrict this in production
}

# EC2 Configuration
variable "ami_id" {
  description = "AMI ID for EC2 instances"
  type        = string
  default     = "ami-0c02fb55956c7d316" # Amazon Linux 2 AMI (us-east-1)
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.medium"
}

variable "key_pair_name" {
  description = "Name of the AWS key pair for EC2 instances"
  type        = string
  default     = "smokeout-nyc-key"
}

# Auto Scaling Configuration
variable "asg_min_size" {
  description = "Minimum number of instances in the auto scaling group"
  type        = number
  default     = 2
}

variable "asg_max_size" {
  description = "Maximum number of instances in the auto scaling group"
  type        = number
  default     = 10
}

variable "asg_desired_capacity" {
  description = "Desired number of instances in the auto scaling group"
  type        = number
  default     = 3
}

# RDS Configuration
variable "db_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t3.micro"
}

variable "db_allocated_storage" {
  description = "Initial allocated storage for RDS (GB)"
  type        = number
  default     = 20
}

variable "db_max_allocated_storage" {
  description = "Maximum allocated storage for RDS (GB)"
  type        = number
  default     = 100
}

variable "db_name" {
  description = "Name of the database"
  type        = string
  default     = "smokeout_nyc"
}

variable "db_username" {
  description = "Database master username"
  type        = string
  default     = "smokeout_admin"
}

variable "db_password" {
  description = "Database master password"
  type        = string
  sensitive   = true
  default     = "ChangeThisPassword123!" # Change in production
}

# Redis Configuration
variable "redis_node_type" {
  description = "ElastiCache node type"
  type        = string
  default     = "cache.t3.micro"
}

variable "redis_num_cache_nodes" {
  description = "Number of cache nodes"
  type        = number
  default     = 2
}

variable "redis_auth_token" {
  description = "Auth token for Redis cluster"
  type        = string
  sensitive   = true
  default     = "ChangeThisRedisToken123!" # Change in production
}

# Monitoring and Logging
variable "enable_detailed_monitoring" {
  description = "Enable detailed CloudWatch monitoring"
  type        = bool
  default     = true
}

variable "log_retention_days" {
  description = "Log retention period in days"
  type        = number
  default     = 30
}

# Backup Configuration
variable "backup_retention_period" {
  description = "Database backup retention period in days"
  type        = number
  default     = 7
}

# SSL/TLS Configuration
variable "ssl_policy" {
  description = "SSL policy for ALB listeners"
  type        = string
  default     = "ELBSecurityPolicy-TLS-1-2-2017-01"
}

# Notification Configuration
variable "notification_email" {
  description = "Email address for CloudWatch alarm notifications"
  type        = string
  default     = "admin@smokeout.nyc"
}

# Cost Optimization
variable "enable_spot_instances" {
  description = "Enable Spot instances for cost optimization (non-production)"
  type        = bool
  default     = false
}

variable "spot_price" {
  description = "Maximum price for Spot instances"
  type        = string
  default     = "0.05"
}

# Security Configuration
variable "enable_waf" {
  description = "Enable AWS WAF for additional security"
  type        = bool
  default     = true
}

variable "enable_shield" {
  description = "Enable AWS Shield Advanced"
  type        = bool
  default     = false
}

# Performance Configuration
variable "enable_cloudfront" {
  description = "Enable CloudFront CDN"
  type        = bool
  default     = true
}

variable "cloudfront_price_class" {
  description = "CloudFront price class"
  type        = string
  default     = "PriceClass_100"
  
  validation {
    condition = contains([
      "PriceClass_All",
      "PriceClass_200", 
      "PriceClass_100"
    ], var.cloudfront_price_class)
    error_message = "CloudFront price class must be one of: PriceClass_All, PriceClass_200, PriceClass_100."
  }
}

# Database Performance
variable "enable_performance_insights" {
  description = "Enable RDS Performance Insights"
  type        = bool
  default     = true
}

variable "performance_insights_retention_period" {
  description = "Performance Insights retention period in days"
  type        = number
  default     = 7
}

# High Availability Configuration
variable "enable_multi_az" {
  description = "Enable Multi-AZ deployment for RDS"
  type        = bool
  default     = true
}

variable "enable_cross_region_backup" {
  description = "Enable cross-region backup"
  type        = bool
  default     = false
}

variable "backup_region" {
  description = "Backup region for cross-region backup"
  type        = string
  default     = "us-west-2"
}

# Gaming-specific Configuration
variable "enable_websocket_support" {
  description = "Enable WebSocket support for real-time gaming"
  type        = bool
  default     = true
}

variable "enable_session_stickiness" {
  description = "Enable session stickiness for ALB"
  type        = bool
  default     = true
}

variable "websocket_idle_timeout" {
  description = "WebSocket idle timeout in seconds"
  type        = number
  default     = 3600
}

# Development Configuration
variable "enable_ssh_access" {
  description = "Enable SSH access to EC2 instances"
  type        = bool
  default     = true
}

variable "enable_debug_mode" {
  description = "Enable debug mode for applications"
  type        = bool
  default     = false
}

# Resource Tagging
variable "additional_tags" {
  description = "Additional tags to apply to all resources"
  type        = map(string)
  default = {
    Owner       = "DevOps Team"
    Application = "Cannabis Industry Platform"
    Contact     = "admin@smokeout.nyc"
  }
}