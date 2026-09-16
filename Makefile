PHP=php
COMPOSER=composer
.PHONY: install test qa ci cs cs-fix stan mutation pre-commit deploy-landing
# -----------------------------
# SETUP
# -----------------------------
install:
	$(COMPOSER) install

# -----------------------------
# QA (before each commit)
# -----------------------------
qa: cs stan test
	@echo "✔ QA OK — el código no ha explotado"

# -----------------------------
# CI (before opening the PR)
# -----------------------------
ci: qa mutation

# -----------------------------
# PRE-COMMIT (alias of ci)
# -----------------------------
pre-commit: ci

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
	./vendor/bin/phpstan analyse --memory-limit=1G $(PATHS)

# -----------------------------
# TESTS
# -----------------------------
test:
	./vendor/bin/phpunit

test-coverage:
	./vendor/bin/phpunit --coverage-text

# -----------------------------
# MUTATION TESTING
# -----------------------------
mutation:
	./vendor/bin/infection --threads=max --show-mutations

# -----------------------------
# LANDING
# -----------------------------
deploy-landing:
	cd landing && npx wrangler deploy