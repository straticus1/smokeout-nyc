variable "project_name" {
  description = "Name of the project"
  type        = string
  default     = "smokeoutnyc"
}

variable "env" {
  description = "Environment name"
  type        = string
  default     = "prod"
}

variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "us-east-1"
}

variable "vpc_cidr" {
  description = "CIDR block for VPC"
  type        = string
  default     = "10.0.0.0/16"
}

variable "public_subnets" {
  description = "Public subnet CIDR blocks"
  type        = list(string)
  default     = ["10.0.1.0/24", "10.0.2.0/24", "10.0.3.0/24"]
}

variable "private_subnets" {
  description = "Private subnet CIDR blocks"
  type        = list(string)
  default     = ["10.0.4.0/24", "10.0.5.0/24", "10.0.6.0/24"]
}

variable "mysql_engine_version" {
  description = "MySQL engine version"
  type        = string
  default     = "8.0.35"
}

variable "mysql_family" {
  description = "MySQL parameter group family"
  type        = string
  default     = "mysql8.0"
}

variable "mysql_major_engine" {
  description = "MySQL major engine version"
  type        = string
  default     = "8.0"
}

variable "db_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t3.micro"
}

variable "db_name" {
  description = "Database name"
  type        = string
  default     = "smokeoutnyc"
}

variable "db_username" {
  description = "Database username"
  type        = string
  default     = "admin"
}

variable "db_password" {
  description = "Database password"
  type        = string
  sensitive   = true
  # Set via environment variable or terraform.tfvars
}

variable "api_image" {
  description = "Docker image for PHP API service"
  type        = string
  default     = "nginx:latest" # Placeholder - replace with actual image
}

variable "realtime_image" {
  description = "Docker image for Node.js realtime service"
  type        = string
  default     = "node:18-alpine" # Placeholder - replace with actual image
}

variable "tags" {
  description = "Common tags for all resources"
  type        = map(string)
  default = {
    Project     = "SmokeoutNYC"
    Environment = "production"
    ManagedBy   = "Terraform"
  }
}
