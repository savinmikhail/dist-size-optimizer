cs:
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --diff --verbose

phpstan:
	vendor/bin/phpstan

test:
	vendor/bin/phpunit

rector:
	vendor/bin/rector process