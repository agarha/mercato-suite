terraform {
  required_version = ">= 1.7.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = "us-east-1"
}

locals {
  environment = "prod"
  tags = {
    project     = "mercato"
    environment = local.environment
    managed_by  = "terraform"
  }
}

module "network" {
  source      = "../../modules/network"
  environment = local.environment
  tags        = local.tags
}

module "data" {
  source      = "../../modules/data"
  environment = local.environment
  vpc_id      = module.network.vpc_id
  subnet_ids  = module.network.private_subnet_ids
  tags        = local.tags
}

module "eks" {
  source      = "../../modules/eks"
  environment = local.environment
  vpc_id      = module.network.vpc_id
  subnet_ids  = module.network.private_subnet_ids
  tags        = local.tags
}

module "secrets" {
  source      = "../../modules/secrets"
  environment = local.environment
  tags        = local.tags
}

module "edge" {
  source      = "../../modules/edge"
  environment = local.environment
  tags        = local.tags
}

module "observability" {
  source      = "../../modules/observability"
  environment = local.environment
  tags        = local.tags
}

module "ci" {
  source      = "../../modules/ci"
  environment = local.environment
  tags        = local.tags
}
