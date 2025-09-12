.PHONY: test build clean help

# Default target
help:
	@echo "Available commands:"
	@echo "  test          - Run tests using Docker"
	@echo "  build         - Build Docker image"
	@echo "  clean         - Clean up Docker containers and images"
	@echo "  shell         - Open shell in the app container"

# Run tests
test:
	docker compose run --rm app composer test

# Build the Docker image
build:
	docker compose build

# Clean up Docker resources
clean:
	docker compose down --volumes --remove-orphans
	docker system prune -f

# Open shell in the app container
shell:
	docker compose run --rm app bash

# Run specific test file
test-file:
	@if [ -z "$(FILE)" ]; then \
		echo "Usage: make test-file FILE=tests/TenancyTest.php"; \
		exit 1; \
	fi
	docker compose run --rm app ./vendor/bin/phpunit $(FILE)
