tests: up
	docker-compose run php php FpDbTest/test.php
	docker-compose run php vendor/bin/phpunit
	docker-compose down

up: install
	docker-compose up -d

install:
	docker run --rm -u "$$(id -u):$$(id -g)" -v "$$(pwd):/app" composer:latest composer install --prefer-dist --ignore-platform-reqs

.PHONY: tests