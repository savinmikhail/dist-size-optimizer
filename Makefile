POPULAR_LIMIT ?= 20
POPULAR_MIN_BYTES ?= 1024

analyze-popular:
	php check-top-packagist.php --limit=$(POPULAR_LIMIT) --min-bytes=$(POPULAR_MIN_BYTES)

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
