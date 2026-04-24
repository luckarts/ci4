.PHONY: test test-unit test-integration test-feature serve setup db-up db-down palace palace-auto palace-extract palace-verify palace-search

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

palace:
	python3 scripts/palace/palace_blog_auto.py

palace-auto:
	python3 scripts/palace/palace_blog_auto.py

palace-extract:
	python3 scripts/palace/palace_blog_auto.py --extract

palace-verify:
	python3 scripts/palace/palace_blog_auto.py --verify

palace-search:
	@read -p "Search query: " query; mempalace search "$$query"

.DEFAULT_GOAL := help
