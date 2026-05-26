variable "environment" { type = string }
variable "tags" { type = map(string) }

resource "aws_wafv2_web_acl" "this" {
  name  = "mercato-${var.environment}"
  scope = "REGIONAL"
  default_action {
    allow {}
  }
  visibility_config {
    cloudwatch_metrics_enabled = true
    metric_name                = "mercato-${var.environment}"
    sampled_requests_enabled   = true
  }
  tags = var.tags
}

output "waf_acl_arn" { value = aws_wafv2_web_acl.this.arn }
