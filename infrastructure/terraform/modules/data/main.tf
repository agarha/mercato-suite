variable "environment" { type = string }
variable "vpc_id" { type = string }
variable "subnet_ids" { type = list(string) }
variable "tags" { type = map(string) }

resource "aws_db_subnet_group" "this" {
  name       = "mercato-${var.environment}"
  subnet_ids = var.subnet_ids
  tags       = var.tags
}

resource "aws_rds_cluster" "mysql" {
  cluster_identifier          = "mercato-${var.environment}-aurora"
  engine                      = "aurora-mysql"
  engine_mode                 = "provisioned"
  database_name               = "mercato"
  master_username             = "mercato_admin"
  manage_master_user_password = true
  db_subnet_group_name        = aws_db_subnet_group.this.name
  backup_retention_period     = 35
  storage_encrypted           = true
  tags                        = var.tags

  serverlessv2_scaling_configuration {
    min_capacity = 1
    max_capacity = 8
  }
}

resource "aws_rds_cluster_instance" "mysql" {
  count              = 1
  identifier         = "mercato-${var.environment}-aurora-${count.index + 1}"
  cluster_identifier = aws_rds_cluster.mysql.id
  instance_class     = "db.serverless"
  engine             = aws_rds_cluster.mysql.engine
  tags               = var.tags
}

resource "aws_elasticache_subnet_group" "this" {
  name       = "mercato-${var.environment}"
  subnet_ids = var.subnet_ids
}

resource "aws_s3_bucket" "media" {
  bucket = "mercato-${var.environment}-media"
  tags   = var.tags
}

resource "aws_s3_bucket_versioning" "media" {
  bucket = aws_s3_bucket.media.id
  versioning_configuration {
    status = "Enabled"
  }
}

output "database_cluster_id" { value = aws_rds_cluster.mysql.id }
output "media_bucket" { value = aws_s3_bucket.media.bucket }
