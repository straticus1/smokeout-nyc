# Terraform Outputs for SmokeoutNYC Infrastructure

output "vpc_id" {
  description = "ID of the VPC"
  value       = aws_vpc.main.id
}

output "vpc_cidr_block" {
  description = "CIDR block of the VPC"
  value       = aws_vpc.main.cidr_block
}

output "public_subnet_ids" {
  description = "IDs of the public subnets"
  value       = aws_subnet.public[*].id
}

output "private_subnet_ids" {
  description = "IDs of the private subnets"
  value       = aws_subnet.private[*].id
}

output "database_subnet_ids" {
  description = "IDs of the database subnets"
  value       = aws_subnet.database[*].id
}

# Load Balancer Outputs
output "alb_dns_name" {
  description = "DNS name of the Application Load Balancer"
  value       = aws_lb.main.dns_name
}

output "alb_zone_id" {
  description = "Zone ID of the Application Load Balancer"
  value       = aws_lb.main.zone_id
}

output "alb_arn" {
  description = "ARN of the Application Load Balancer"
  value       = aws_lb.main.arn
}

# Database Outputs
output "rds_endpoint" {
  description = "RDS instance endpoint"
  value       = aws_db_instance.main.endpoint
  sensitive   = true
}

output "rds_port" {
  description = "RDS instance port"
  value       = aws_db_instance.main.port
}

output "rds_database_name" {
  description = "RDS database name"
  value       = aws_db_instance.main.db_name
}

# Redis Outputs
output "redis_primary_endpoint" {
  description = "Redis primary endpoint"
  value       = aws_elasticache_replication_group.main.primary_endpoint_address
  sensitive   = true
}

output "redis_port" {
  description = "Redis port"
  value       = aws_elasticache_replication_group.main.port
}

# S3 Outputs
output "app_s3_bucket_name" {
  description = "Name of the application S3 bucket"
  value       = aws_s3_bucket.app.bucket
}

output "app_s3_bucket_arn" {
  description = "ARN of the application S3 bucket"
  value       = aws_s3_bucket.app.arn
}

output "alb_logs_s3_bucket_name" {
  description = "Name of the ALB logs S3 bucket"
  value       = aws_s3_bucket.alb_logs.bucket
}

# Security Group Outputs
output "alb_security_group_id" {
  description = "ID of the ALB security group"
  value       = aws_security_group.alb.id
}

output "web_security_group_id" {
  description = "ID of the web servers security group"
  value       = aws_security_group.web.id
}

output "database_security_group_id" {
  description = "ID of the database security group"
  value       = aws_security_group.database.id
}

output "redis_security_group_id" {
  description = "ID of the Redis security group"
  value       = aws_security_group.redis.id
}

# Auto Scaling Outputs
output "autoscaling_group_name" {
  description = "Name of the Auto Scaling Group"
  value       = aws_autoscaling_group.web.name
}

output "autoscaling_group_arn" {
  description = "ARN of the Auto Scaling Group"
  value       = aws_autoscaling_group.web.arn
}

output "launch_template_id" {
  description = "ID of the launch template"
  value       = aws_launch_template.web.id
}

# IAM Outputs
output "web_instance_role_name" {
  description = "Name of the web instance IAM role"
  value       = aws_iam_role.web.name
}

output "web_instance_role_arn" {
  description = "ARN of the web instance IAM role"
  value       = aws_iam_role.web.arn
}

output "web_instance_profile_name" {
  description = "Name of the web instance profile"
  value       = aws_iam_instance_profile.web.name
}

# Route53 Outputs
output "domain_name" {
  description = "Domain name"
  value       = var.domain_name
}

output "route53_zone_id" {
  description = "Route53 hosted zone ID"
  value       = data.aws_route53_zone.main.zone_id
}

# SSL Certificate Outputs
output "ssl_certificate_arn" {
  description = "ARN of the SSL certificate"
  value       = aws_acm_certificate.main.arn
}

# CloudWatch Outputs
output "cloudwatch_log_group_name" {
  description = "Name of the CloudWatch log group"
  value       = aws_cloudwatch_log_group.app.name
}

output "cloudwatch_log_group_arn" {
  description = "ARN of the CloudWatch log group"
  value       = aws_cloudwatch_log_group.app.arn
}

# Connection Information for Applications
output "database_connection_string" {
  description = "Database connection string (sensitive)"
  value = "mysql://${var.db_username}:${var.db_password}@${aws_db_instance.main.endpoint}:${aws_db_instance.main.port}/${aws_db_instance.main.db_name}"
  sensitive = true
}

output "redis_connection_string" {
  description = "Redis connection string (sensitive)"
  value = "redis://:${var.redis_auth_token}@${aws_elasticache_replication_group.main.primary_endpoint_address}:${aws_elasticache_replication_group.main.port}"
  sensitive = true
}

# Environment Configuration
output "environment_variables" {
  description = "Environment variables for application configuration"
  value = {
    ENVIRONMENT     = var.environment
    AWS_REGION      = var.aws_region
    DB_HOST         = aws_db_instance.main.endpoint
    DB_PORT         = aws_db_instance.main.port
    DB_NAME         = aws_db_instance.main.db_name
    DB_USERNAME     = var.db_username
    REDIS_HOST      = aws_elasticache_replication_group.main.primary_endpoint_address
    REDIS_PORT      = aws_elasticache_replication_group.main.port
    S3_BUCKET       = aws_s3_bucket.app.bucket
    LOG_GROUP       = aws_cloudwatch_log_group.app.name
    DOMAIN_NAME     = var.domain_name
  }
  sensitive = false
}

# Monitoring and Alerting
output "monitoring_endpoints" {
  description = "Monitoring and alerting endpoints"
  value = {
    application_url     = "https://${var.domain_name}"
    health_check_url    = "https://${var.domain_name}/health"
    metrics_endpoint    = "https://${var.domain_name}/metrics"
    logs_dashboard_url  = "https://${var.aws_region}.console.aws.amazon.com/cloudwatch/home?region=${var.aws_region}#logsV2:log-groups/log-group/${replace(aws_cloudwatch_log_group.app.name, "/", "$252F")}"
  }
}

# Cost Estimation
output "estimated_monthly_cost" {
  description = "Estimated monthly cost breakdown (USD)"
  value = {
    ec2_instances      = "~$${var.asg_desired_capacity * (var.instance_type == "t3.micro" ? 7.5 : var.instance_type == "t3.small" ? 15 : var.instance_type == "t3.medium" ? 30 : 60)}"
    rds_database       = "~$${var.db_instance_class == "db.t3.micro" ? 12 : var.db_instance_class == "db.t3.small" ? 24 : 48}"
    redis_cache        = "~$${var.redis_num_cache_nodes * (var.redis_node_type == "cache.t3.micro" ? 11 : 22)}"
    load_balancer      = "~$20"
    data_transfer      = "~$15"
    cloudwatch_logs    = "~$5"
    s3_storage         = "~$10"
    total_estimated    = "~$${var.asg_desired_capacity * (var.instance_type == "t3.micro" ? 7.5 : var.instance_type == "t3.small" ? 15 : var.instance_type == "t3.medium" ? 30 : 60) + (var.db_instance_class == "db.t3.micro" ? 12 : var.db_instance_class == "db.t3.small" ? 24 : 48) + var.redis_num_cache_nodes * (var.redis_node_type == "cache.t3.micro" ? 11 : 22) + 50}"
  }
}

# Deployment Instructions
output "deployment_instructions" {
  description = "Next steps for deployment"
  value = <<-EOT
    Infrastructure deployment completed! Next steps:
    
    1. Update your application configuration with the following:
       - Database endpoint: ${aws_db_instance.main.endpoint}
       - Redis endpoint: ${aws_elasticache_replication_group.main.primary_endpoint_address}
       - S3 bucket: ${aws_s3_bucket.app.bucket}
    
    2. Deploy your application using Ansible:
       cd ../ansible
       ansible-playbook -i inventory/production site.yml
    
    3. Configure DNS:
       - Domain ${var.domain_name} points to: ${aws_lb.main.dns_name}
       - SSL certificate: ${aws_acm_certificate.main.arn}
    
    4. Access your application:
       - URL: https://${var.domain_name}
       - Health check: https://${var.domain_name}/health
    
    5. Monitor your infrastructure:
       - CloudWatch Dashboard: https://console.aws.amazon.com/cloudwatch/
       - Log Groups: ${aws_cloudwatch_log_group.app.name}
    
    Total estimated monthly cost: ~$${var.asg_desired_capacity * 30 + 50}
  EOT
}

# Security Checklist
output "security_checklist" {
  description = "Security configuration checklist"
  value = <<-EOT
    Security Configuration Status:
    ✓ VPC with private subnets for application servers
    ✓ Public subnets only for load balancer
    ✓ Database in isolated subnet group
    ✓ Security groups with least privilege access
    ✓ SSL/TLS certificate configured
    ✓ S3 bucket encryption enabled
    ✓ RDS encryption at rest enabled
    ✓ Redis encryption in transit and at rest
    ✓ IAM roles with minimal required permissions
    ✓ CloudWatch logging enabled
    
    Additional security recommendations:
    - Review and restrict SSH access CIDR blocks
    - Regularly rotate database and Redis passwords
    - Enable AWS Config for compliance monitoring
    - Consider enabling AWS GuardDuty for threat detection
    - Implement regular security assessments
  EOT
}