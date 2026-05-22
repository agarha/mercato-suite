variable "environment" { type = string }
variable "tags" { type = map(string) }

resource "aws_cloudwatch_log_group" "app" {
  name              = "/mercato/${var.environment}/app"
  retention_in_days = 30
  tags              = var.tags
}

resource "aws_s3_bucket" "telemetry" {
  bucket = "mercato-${var.environment}-telemetry"
  tags   = var.tags
}

output "app_log_group" { value = aws_cloudwatch_log_group.app.name }
