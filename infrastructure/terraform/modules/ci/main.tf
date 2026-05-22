variable "environment" { type = string }
variable "tags" { type = map(string) }

resource "aws_ecr_repository" "wordpress" {
  name = "mercato/${var.environment}/wordpress"
  tags = var.tags
}

resource "aws_ecr_repository" "outbox_relay" {
  name = "mercato/${var.environment}/outbox-relay"
  tags = var.tags
}

output "wordpress_repository_url" { value = aws_ecr_repository.wordpress.repository_url }
output "outbox_relay_repository_url" { value = aws_ecr_repository.outbox_relay.repository_url }
