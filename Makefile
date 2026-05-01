.PHONY: test test-all test-83 test-84 test-85 phpstan build

test: test-all

test-all:
	@$(MAKE) test-83; $(MAKE) test-84; $(MAKE) test-85

test-83:
	@docker compose run --rm php83 vendor/bin/phpunit

test-84:
	@docker compose run --rm php84 vendor/bin/phpunit

test-85:
	@docker compose run --rm php85 vendor/bin/phpunit

phpstan:
	docker compose run --rm php85 vendor/bin/phpstan analyse --memory-limit=512M

build:
	docker compose build
