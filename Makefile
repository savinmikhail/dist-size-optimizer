POPULAR_LIMIT ?= 100
POPULAR_MIN_BYTES ?= 1024
POPULAR_CONCURRENCY ?= 4

analyze-popular:
	php check-top-packagist.php --limit=$(POPULAR_LIMIT) --min-bytes=$(POPULAR_MIN_BYTES) --concurrency=$(POPULAR_CONCURRENCY)

cs:
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --diff --verbose

phpstan:
	vendor/bin/phpstan

test:
	vendor/bin/phpunit

rector:
	vendor/bin/rector process

composer-normalize:
	composer normalize

composer-unused:
	vendor/bin/composer-unused

composer-req:
	vendor/bin/composer-require-checker check

quality: cs rector composer-normalize composer-unused composer-req phpstan test
