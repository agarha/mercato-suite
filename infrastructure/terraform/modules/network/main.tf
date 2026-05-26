variable "environment" { type = string }
variable "tags" { type = map(string) }

resource "aws_vpc" "this" {
  cidr_block           = "10.42.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true
  tags                 = merge(var.tags, { Name = "mercato-${var.environment}" })
}

resource "aws_subnet" "private" {
  count             = 3
  vpc_id            = aws_vpc.this.id
  cidr_block        = cidrsubnet(aws_vpc.this.cidr_block, 4, count.index)
  availability_zone = ["us-east-1a", "us-east-1b", "us-east-1c"][count.index]
  tags              = merge(var.tags, { Name = "mercato-${var.environment}-private-${count.index + 1}" })
}

output "vpc_id" { value = aws_vpc.this.id }
output "private_subnet_ids" { value = aws_subnet.private[*].id }
