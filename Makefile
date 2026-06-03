PHP=php
COMPOSER=composer
.PHONY: test
# -----------------------------
# QA PRINCIPAL
# -----------------------------
qa: cs test
	@echo "✔ QA OK — el código no ha explotado"

# -----------------------------
# PRE-COMMIT
# -----------------------------
pre-commit: cs-fix
	./vendor/bin/phpstan analyse src --level=8
	./vendor/bin/phpstan analyse tests --level=8
	./vendor/bin/phpunit
	@echo "✔ Pre-commit OK"
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