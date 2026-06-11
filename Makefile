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
	./vendor/bin/phpstan analyse src --level=max
	./vendor/bin/phpstan analyse tests --level=max
	./vendor/bin/phpunit
	./vendor/bin/infection --min-msi=98 --min-covered-msi=99
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
# -----------------------------
# MUTATION TESTING
# -----------------------------
mutation:
	./vendor/bin/infection --min-msi=92 --min-covered-msi=92

ci: cs stan test mutation