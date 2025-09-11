#!/bin/bash

# SmokeoutNYC Deployment Script
# Complete infrastructure deployment and application setup

set -e

# Configuration
PROJECT_NAME="smokeout-nyc"
ENVIRONMENT="production"
AWS_REGION="us-east-1"
TERRAFORM_DIR="terraform"
ANSIBLE_DIR="ansible"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

# Function to check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check required commands
    local required_commands=("terraform" "ansible-playbook" "aws" "git" "curl")
    
    for cmd in "${required_commands[@]}"; do
        if ! command -v $cmd &> /dev/null; then
            error "$cmd is required but not installed"
            exit 1
        fi
    done
    
    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        error "AWS credentials not configured. Run 'aws configure' first."
        exit 1
    fi
    
    # Check Terraform version
    local tf_version=$(terraform version -json | jq -r '.terraform_version')
    log "Terraform version: $tf_version"
    
    # Check Ansible version
    local ansible_version=$(ansible --version | head -n 1)
    log "Ansible version: $ansible_version"
    
    success "All prerequisites satisfied"
}

# Function to deploy infrastructure
deploy_infrastructure() {
    log "Deploying infrastructure with Terraform..."
    
    cd $TERRAFORM_DIR
    
    # Initialize Terraform
    log "Initializing Terraform..."
    terraform init
    
    # Plan deployment
    log "Planning infrastructure deployment..."
    terraform plan -var="environment=$ENVIRONMENT" -out=tfplan
    
    # Apply deployment
    log "Applying infrastructure deployment..."
    terraform apply tfplan
    
    # Save outputs
    terraform output -json > ../terraform_outputs.json
    
    cd ..
    
    success "Infrastructure deployment completed"
}

# Function to wait for infrastructure
wait_for_infrastructure() {
    log "Waiting for infrastructure to be ready..."
    
    # Get ALB DNS name from terraform outputs
    local alb_dns=$(jq -r '.alb_dns_name.value' terraform_outputs.json)
    
    if [ "$alb_dns" == "null" ]; then
        error "Could not get ALB DNS name from terraform outputs"
        exit 1
    fi
    
    log "Waiting for ALB to be accessible: $alb_dns"
    
    # Wait for ALB to respond
    local max_attempts=60
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if curl -f -s "http://$alb_dns/health" &> /dev/null; then
            success "Infrastructure is ready"
            return 0
        fi
        
        log "Attempt $((attempt + 1))/$max_attempts - Infrastructure not ready yet..."
        sleep 30
        ((attempt++))
    done
    
    error "Infrastructure not ready after $((max_attempts * 30)) seconds"
    exit 1
}

# Function to create dynamic inventory
create_dynamic_inventory() {
    log "Creating dynamic inventory..."
    
    # Get instance IPs from terraform outputs
    local instance_ips=$(aws ec2 describe-instances \
        --filters "Name=tag:Name,Values=${PROJECT_NAME}-${ENVIRONMENT}-web" \
                  "Name=instance-state-name,Values=running" \
        --query 'Reservations[*].Instances[*].PrivateIpAddress' \
        --output text)
    
    if [ -z "$instance_ips" ]; then
        error "No running instances found"
        exit 1
    fi
    
    # Create inventory file
    cat > $ANSIBLE_DIR/inventory/dynamic << EOF
[web_servers]
EOF
    
    local index=1
    for ip in $instance_ips; do
        echo "web-$index.${PROJECT_NAME}.local ansible_host=$ip" >> $ANSIBLE_DIR/inventory/dynamic
        ((index++))
    done
    
    # Add group variables
    cat >> $ANSIBLE_DIR/inventory/dynamic << EOF

[web_servers:vars]
EOF
    
    # Add variables from terraform outputs
    local db_endpoint=$(jq -r '.rds_endpoint.value' terraform_outputs.json)
    local redis_endpoint=$(jq -r '.redis_primary_endpoint.value' terraform_outputs.json)
    local s3_bucket=$(jq -r '.app_s3_bucket_name.value' terraform_outputs.json)
    
    cat >> $ANSIBLE_DIR/inventory/dynamic << EOF
db_host=$db_endpoint
redis_host=$redis_endpoint
s3_bucket=$s3_bucket
environment=$ENVIRONMENT
domain_name=smokeout.nyc
ansible_user=ec2-user
ansible_ssh_private_key_file=~/.ssh/${PROJECT_NAME}-key.pem
ansible_ssh_common_args='-o StrictHostKeyChecking=no'
EOF
    
    success "Dynamic inventory created with $(echo $instance_ips | wc -w) instances"
}

# Function to deploy application
deploy_application() {
    log "Deploying application with Ansible..."
    
    cd $ANSIBLE_DIR
    
    # Test connectivity
    log "Testing connectivity to instances..."
    ansible web_servers -i inventory/dynamic -m ping
    
    # Deploy application
    log "Running application deployment..."
    ansible-playbook -i inventory/dynamic site.yml \
        --extra-vars "branch=main" \
        --extra-vars "run_migrations=true"
    
    cd ..
    
    success "Application deployment completed"
}

# Function to run smoke tests
run_smoke_tests() {
    log "Running smoke tests..."
    
    local app_url="https://smokeout.nyc"
    
    # Test basic connectivity
    log "Testing application URL: $app_url"
    if curl -f -s "$app_url/health" | grep -q "healthy"; then
        success "Health check passed"
    else
        error "Health check failed"
        return 1
    fi
    
    # Test API endpoints
    log "Testing API endpoints..."
    local api_endpoints=(
        "/api/health"
        "/api/auth/status"
        "/api/game/status"
    )
    
    for endpoint in "${api_endpoints[@]}"; do
        log "Testing $app_url$endpoint"
        if curl -f -s "$app_url$endpoint" &> /dev/null; then
            success "$endpoint - OK"
        else
            warning "$endpoint - Failed (may be expected for auth endpoints)"
        fi
    done
    
    # Test WebSocket connection
    log "Testing WebSocket connection..."
    if command -v wscat &> /dev/null; then
        echo "test" | timeout 5 wscat -c "wss://smokeout.nyc/ws" &> /dev/null
        if [ $? -eq 0 ]; then
            success "WebSocket connection - OK"
        else
            warning "WebSocket connection - Failed"
        fi
    else
        warning "wscat not installed, skipping WebSocket test"
    fi
    
    success "Smoke tests completed"
}

# Function to create SSL certificate
setup_ssl() {
    log "Setting up SSL certificate..."
    
    # The certificate is already created by Terraform via ACM
    # Just verify it's ready
    local cert_arn=$(jq -r '.ssl_certificate_arn.value' terraform_outputs.json)
    
    if [ "$cert_arn" != "null" ]; then
        success "SSL certificate configured: $cert_arn"
    else
        error "SSL certificate not found"
        return 1
    fi
}

# Function to setup monitoring
setup_monitoring() {
    log "Setting up monitoring..."
    
    # CloudWatch is already configured via Terraform and user data
    # Additional monitoring setup can be added here
    
    log "Creating custom dashboards..."
    
    cat > cloudwatch-dashboard.json << EOF
{
    "widgets": [
        {
            "type": "metric",
            "properties": {
                "metrics": [
                    [ "AWS/ApplicationELB", "RequestCount", "LoadBalancer", "$(jq -r '.alb_arn.value' terraform_outputs.json)" ],
                    [ ".", "TargetResponseTime", ".", "." ],
                    [ "AWS/EC2", "CPUUtilization", "AutoScalingGroupName", "$(jq -r '.autoscaling_group_name.value' terraform_outputs.json)" ]
                ],
                "period": 300,
                "stat": "Average",
                "region": "$AWS_REGION",
                "title": "SmokeoutNYC Metrics"
            }
        }
    ]
}
EOF
    
    aws cloudwatch put-dashboard \
        --dashboard-name "SmokeoutNYC-$ENVIRONMENT" \
        --dashboard-body file://cloudwatch-dashboard.json
    
    success "Monitoring dashboard created"
}

# Function to backup current deployment
create_backup() {
    log "Creating deployment backup..."
    
    local backup_name="${PROJECT_NAME}-backup-$(date +%Y%m%d-%H%M%S)"
    
    # Create database backup via first web server
    cd $ANSIBLE_DIR
    ansible web_servers[0] -i inventory/dynamic \
        -m shell -a "mysqldump -h {{ db_host }} -u {{ db_username }} -p{{ db_password }} {{ db_name }} > /tmp/$backup_name.sql"
    
    # Upload to S3
    ansible web_servers[0] -i inventory/dynamic \
        -m aws_s3 -a "bucket={{ s3_bucket }} object=backups/$backup_name.sql src=/tmp/$backup_name.sql mode=put"
    
    cd ..
    
    success "Backup created: $backup_name"
}

# Function to rollback deployment
rollback() {
    warning "Rolling back deployment..."
    
    if [ -f "terraform_outputs.json.backup" ]; then
        log "Restoring previous infrastructure state..."
        mv terraform_outputs.json.backup terraform_outputs.json
    fi
    
    # Rollback application to previous version
    cd $ANSIBLE_DIR
    ansible-playbook -i inventory/dynamic site.yml \
        --extra-vars "branch=main" \
        --extra-vars "rollback=true"
    cd ..
    
    success "Rollback completed"
}

# Function to show deployment summary
show_summary() {
    log "Deployment Summary"
    echo "================================="
    echo "Project: $PROJECT_NAME"
    echo "Environment: $ENVIRONMENT"
    echo "Region: $AWS_REGION"
    echo "Deployed at: $(date)"
    echo ""
    
    if [ -f "terraform_outputs.json" ]; then
        echo "Infrastructure:"
        echo "- Application URL: https://$(jq -r '.domain_name.value' terraform_outputs.json)"
        echo "- ALB DNS: $(jq -r '.alb_dns_name.value' terraform_outputs.json)"
        echo "- Database: $(jq -r '.rds_endpoint.value' terraform_outputs.json)"
        echo "- Redis: $(jq -r '.redis_primary_endpoint.value' terraform_outputs.json)"
        echo "- S3 Bucket: $(jq -r '.app_s3_bucket_name.value' terraform_outputs.json)"
        echo ""
        
        echo "Estimated Monthly Cost:"
        jq -r '.estimated_monthly_cost.value | to_entries | .[] | "- \(.key): \(.value)"' terraform_outputs.json
        echo ""
    fi
    
    echo "Next Steps:"
    echo "1. Update DNS to point to the load balancer"
    echo "2. Configure monitoring alerts"
    echo "3. Set up backup schedules"
    echo "4. Review security settings"
    echo "================================="
}

# Main deployment function
main() {
    local command=${1:-deploy}
    
    case $command in
        "deploy")
            log "Starting full deployment of $PROJECT_NAME"
            check_prerequisites
            deploy_infrastructure
            wait_for_infrastructure
            create_dynamic_inventory
            setup_ssl
            deploy_application
            setup_monitoring
            run_smoke_tests
            show_summary
            success "Deployment completed successfully!"
            ;;
        "infrastructure-only")
            log "Deploying infrastructure only"
            check_prerequisites
            deploy_infrastructure
            wait_for_infrastructure
            success "Infrastructure deployment completed!"
            ;;
        "app-only")
            log "Deploying application only"
            check_prerequisites
            create_dynamic_inventory
            deploy_application
            run_smoke_tests
            success "Application deployment completed!"
            ;;
        "rollback")
            rollback
            ;;
        "backup")
            create_backup
            ;;
        "test")
            run_smoke_tests
            ;;
        "destroy")
            warning "Destroying infrastructure..."
            read -p "Are you sure you want to destroy the infrastructure? (yes/no): " confirm
            if [ "$confirm" == "yes" ]; then
                cd $TERRAFORM_DIR
                terraform destroy -var="environment=$ENVIRONMENT"
                cd ..
                success "Infrastructure destroyed"
            else
                log "Destruction cancelled"
            fi
            ;;
        *)
            echo "Usage: $0 [deploy|infrastructure-only|app-only|rollback|backup|test|destroy]"
            echo ""
            echo "Commands:"
            echo "  deploy             - Full deployment (default)"
            echo "  infrastructure-only - Deploy infrastructure only"
            echo "  app-only          - Deploy application only"
            echo "  rollback          - Rollback to previous version"
            echo "  backup            - Create backup"
            echo "  test              - Run smoke tests"
            echo "  destroy           - Destroy infrastructure"
            exit 1
            ;;
    esac
}

# Trap errors and cleanup
trap 'error "Deployment failed on line $LINENO"' ERR

# Change to script directory
cd "$(dirname "$0")"

# Run main function with arguments
main "$@"