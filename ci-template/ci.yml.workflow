name: CI

on:
  push:
    branches: [main, codex/initial-scaffold]
  pull_request:
    branches: [main]

permissions:
  contents: read

jobs:
  php:
    name: PHP — analyse + test
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, intl, bcmath, openssl, json
          coverage: none
      - name: Composer install
        run: composer install --no-progress --prefer-dist --no-interaction
      - name: PHPStan
        run: vendor/bin/phpstan analyse --no-progress
      - name: PHPUnit
        run: vendor/bin/phpunit

  go:
    name: Go — outbox relay
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: services/outbox-relay
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-go@v5
        with:
          go-version: '1.22'
      - name: Vet
        run: go vet ./...
      - name: Build
        run: go build ./...

  module-manifests:
    name: Validate module manifests
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with:
          python-version: '3.12'
      - name: Validate
        run: python3 tools/validate-manifests.py

  docker-build:
    name: Docker — boot smoke
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Build WordPress image
        run: docker build -f docker/wordpress/Dockerfile -t mercato/wp:ci .
      - name: Build outbox-relay image
        run: docker build -f services/outbox-relay/Dockerfile -t mercato/outbox-relay:ci .
