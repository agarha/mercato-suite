variable "environment" { type = string }
variable "tags" { type = map(string) }

resource "aws_kms_key" "this" {
  description             = "Mercato ${var.environment} secrets key"
  deletion_window_in_days = 30
  enable_key_rotation     = true
  tags                    = var.tags
}

resource "aws_secretsmanager_secret" "suite" {
  name       = "mercato/${var.environment}/suite"
  kms_key_id = aws_kms_key.this.key_id
  tags       = var.tags
}

output "secret_name" { value = aws_secretsmanager_secret.suite.name }
