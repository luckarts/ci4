.PHONY: test test-unit test-integration test-feature serve setup db-up db-down

setup:
	bash scripts/install-hooks.sh

db-up:
	cd backend && docker-compose -f docker-compose.test.yml up -d

db-down:
	cd backend && docker-compose -f docker-compose.test.yml down

test-unit:
	cd backend && vendor/bin/phpunit --testsuite unit

test-integration:
	cd backend && vendor/bin/phpunit --testsuite integration

test-feature:
	cd backend && vendor/bin/phpunit --testsuite feature

test:
	cd backend && vendor/bin/phpunit

serve:
	cd backend && php spark serve --host 0.0.0.0 --port 8080

.DEFAULT_GOAL := help
