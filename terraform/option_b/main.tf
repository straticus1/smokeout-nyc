terraform {
  required_version = ">= 1.5.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = ">= 5.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

# Networking
module "vpc" {
  source = "terraform-aws-modules/vpc/aws"
  version = "5.5.1"

  name = "smokeoutnyc-vpc"
  cidr = var.vpc_cidr

  azs             = slice(data.aws_availability_zones.available.names, 0, 3)
  public_subnets  = var.public_subnets
  private_subnets = var.private_subnets

  enable_nat_gateway   = true
  single_nat_gateway   = true
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = var.tags
}

data "aws_availability_zones" "available" {}

# S3 bucket for assets and logs
resource "aws_s3_bucket" "assets" {
  bucket = "${var.project_name}-assets-${var.env}"
  force_destroy = false
  tags = var.tags
}

resource "aws_s3_bucket_versioning" "assets" {
  bucket = aws_s3_bucket.assets.id
  versioning_configuration { status = "Enabled" }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "assets" {
  bucket = aws_s3_bucket.assets.id
  rule { apply_server_side_encryption_by_default { sse_algorithm = "AES256" } }
}

# RDS (MySQL)
module "db" {
  source  = "terraform-aws-modules/rds/aws"
  version = "6.7.0"

  identifier = "${var.project_name}-${var.env}-mysql"

  engine               = "mysql"
  engine_version       = var.mysql_engine_version
  family               = var.mysql_family
  major_engine_version = var.mysql_major_engine
  instance_class       = var.db_instance_class

  allocated_storage     = 50
  max_allocated_storage = 200

  db_name  = var.db_name
  username = var.db_username
  password = var.db_password

  create_db_subnet_group = true
  subnet_ids             = module.vpc.private_subnets

  vpc_security_group_ids = [aws_security_group.db.id]

  multi_az               = false
  publicly_accessible    = false
  storage_encrypted      = true
  skip_final_snapshot    = true

  tags = var.tags
}

resource "aws_security_group" "db" {
  name        = "${var.project_name}-${var.env}-db-sg"
  description = "DB access"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description = "MySQL from app"
    from_port   = 3306
    to_port     = 3306
    protocol    = "tcp"
    security_groups = [aws_security_group.app.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = var.tags
}

# Application security group
resource "aws_security_group" "app" {
  name        = "${var.project_name}-${var.env}-app-sg"
  description = "App access"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description = "HTTP from ALB"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = var.tags
}

# ALB security group
resource "aws_security_group" "alb" {
  name        = "${var.project_name}-${var.env}-alb-sg"
  description = "ALB access"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = var.tags
}

# ECS cluster (Fargate) to run PHP API and Node realtime services
resource "aws_ecs_cluster" "this" {
  name = "${var.project_name}-${var.env}-cluster"
  setting {
    name  = "containerInsights"
    value = "enabled"
  }
  tags = var.tags
}

# IAM roles for ECS tasks
data "aws_iam_policy_document" "ecs_task_assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals { type = "Service" identifiers = ["ecs-tasks.amazonaws.com"] }
  }
}

resource "aws_iam_role" "ecs_task_role" {
  name               = "${var.project_name}-${var.env}-ecs-task-role"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json
}

resource "aws_iam_role" "ecs_execution_role" {
  name               = "${var.project_name}-${var.env}-ecs-exec-role"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json
}

resource "aws_iam_role_policy_attachment" "ecs_exec_policy" {
  role       = aws_iam_role.ecs_execution_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# ALB
resource "aws_lb" "app" {
  name               = "${var.project_name}-${var.env}-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = module.vpc.public_subnets
  enable_deletion_protection = false
  tags = var.tags
}

# Target groups
resource "aws_lb_target_group" "api" {
  name     = "${var.project_name}-${var.env}-api-tg"
  port     = 80
  protocol = "HTTP"
  vpc_id   = module.vpc.vpc_id
  health_check { path = "/api/health" matcher = "200-399" }
  tags = var.tags
}

resource "aws_lb_target_group" "realtime" {
  name     = "${var.project_name}-${var.env}-rt-tg"
  port     = 80
  protocol = "HTTP"
  vpc_id   = module.vpc.vpc_id
  health_check { path = "/socket.io/" matcher = "200-399" }
  tags = var.tags
}

# Listeners
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.app.arn
  port              = 80
  protocol          = "HTTP"
  default_action {
    type = "fixed-response"
    fixed_response { content_type = "text/plain" message_body = "Not Found" status_code = "404" }
  }
}

# Listener rules
resource "aws_lb_listener_rule" "api" {
  listener_arn = aws_lb_listener.http.arn
  priority     = 10
  action { type = "forward" target_group_arn = aws_lb_target_group.api.arn }
  condition { path_pattern { values = ["/api*", "/php*", "/index.php*"] } }
}

resource "aws_lb_listener_rule" "realtime" {
  listener_arn = aws_lb_listener.http.arn
  priority     = 20
  action { type = "forward" target_group_arn = aws_lb_target_group.realtime.arn }
  condition { path_pattern { values = ["/socket.io*", "/realtime*"] } }
}

# ECS Task definitions (placeholders - images to be supplied in CI/CD)
resource "aws_ecs_task_definition" "api" {
  family                   = "${var.project_name}-${var.env}-api"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_execution_role.arn
  task_role_arn            = aws_iam_role.ecs_task_role.arn

  container_definitions = jsonencode([
    {
      name      = "php-api"
      image     = var.api_image
      essential = true
      portMappings = [{ containerPort = 80, protocol = "tcp" }]
      environment = [
        { name = "DB_HOST", value = module.db.db_instance_address },
        { name = "DB_NAME", value = var.db_name },
        { name = "DB_USER", value = var.db_username },
        { name = "DB_PASS", value = var.db_password }
      ]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = "/ecs/${var.project_name}-${var.env}-api"
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "ecs"
        }
      }
    }
  ])
}

resource "aws_cloudwatch_log_group" "api" {
  name              = "/ecs/${var.project_name}-${var.env}-api"
  retention_in_days = 14
}

resource "aws_ecs_task_definition" "realtime" {
  family                   = "${var.project_name}-${var.env}-realtime"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 256
  memory                   = 512
  execution_role_arn       = aws_iam_role.ecs_execution_role.arn
  task_role_arn            = aws_iam_role.ecs_task_role.arn

  container_definitions = jsonencode([
    {
      name      = "node-realtime"
      image     = var.realtime_image
      essential = true
      portMappings = [{ containerPort = 80, protocol = "tcp" }]
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = "/ecs/${var.project_name}-${var.env}-realtime"
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "ecs"
        }
      }
    }
  ])
}

resource "aws_cloudwatch_log_group" "realtime" {
  name              = "/ecs/${var.project_name}-${var.env}-realtime"
  retention_in_days = 14
}

# ECS Services
resource "aws_ecs_service" "api" {
  name            = "${var.project_name}-${var.env}-api"
  cluster         = aws_ecs_cluster.this.id
  task_definition = aws_ecs_task_definition.api.arn
  desired_count   = 2
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = module.vpc.private_subnets
    security_groups = [aws_security_group.app.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.api.arn
    container_name   = "php-api"
    container_port   = 80
  }

  lifecycle { ignore_changes = [desired_count] }
}

resource "aws_ecs_service" "realtime" {
  name            = "${var.project_name}-${var.env}-realtime"
  cluster         = aws_ecs_cluster.this.id
  task_definition = aws_ecs_task_definition.realtime.arn
  desired_count   = 2
  launch_type     = "FARGATE"

  network_configuration {
    subnets         = module.vpc.private_subnets
    security_groups = [aws_security_group.app.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.realtime.arn
    container_name   = "node-realtime"
    container_port   = 80
  }

  lifecycle { ignore_changes = [desired_count] }
}

output "alb_dns_name" {
  value = aws_lb.app.dns_name
}

