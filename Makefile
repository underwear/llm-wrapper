INSTALL_CMD = docker run --rm -v $(CURDIR):/app -w /app composer:2 sh -c "composer install --ignore-platform-reqs"
TEST_CMD = docker run --rm -v $(CURDIR):/app -w /app php:8.3-cli sh -c "vendor/bin/phpunit --configuration phpunit.xml"

.PHONY: install test

install:
	$(INSTALL_CMD)

test:
	$(TEST_CMD)
