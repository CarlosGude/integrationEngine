PHP=php
COMPOSER=composer

# -----------------------------
# QA PRINCIPAL
# -----------------------------
qa: cs stan test
	@echo "✔ QA OK — el código no ha explotado"

# -----------------------------
# CODE STYLE
# -----------------------------
cs:
	./vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	./vendor/bin/php-cs-fixer fix

# -----------------------------
# ANALYSIS
# -----------------------------
stan:
	./vendor/bin/phpstan analyse $(PATHS)

# -----------------------------
# TESTS
# -----------------------------
test:
	./vendor/bin/phpunit

test-coverage:
	./vendor/bin/phpunit --coverage-text

# -----------------------------
# SETUP
# -----------------------------
install:
	$(COMPOSER) install

# -----------------------------
# CI SIMULATION
# -----------------------------
ci: cs stan test