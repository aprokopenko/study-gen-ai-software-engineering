# Makefile — homework-6 developer tooling
#
# Cleanup helpers so you can wipe AI-generated artifacts and re-run code
# generation from a clean slate. Authored inputs are NEVER touched:
#   specification.md, AGENTS.md, TASKS.md, sample-transactions.json,
#   this Makefile, and the whole .claude/ directory.
#
# Task 1 extensions: build, install, run, validate, test, coverage, mcp, shell.
# All PHP/Composer/PHPUnit work goes through the `app` service — never on the host.

SHELL := /bin/bash

# Export host UID/GID so the container runs as the host user (files in shared/
# are then host-user-owned and deletable without sudo).
export UID := $(shell id -u)
export GID := $(shell id -g)

# Artifacts produced by code generation (safe to delete and regenerate).
GENERATED_DIRS  := src tests bin mcp docs context vendor .composer-cache
GENERATED_FILES := composer.json composer.lock phpunit.xml phpunit.xml.dist \
                   Dockerfile docker-compose.yml .dockerignore \
                   README.md HOWTORUN.md research-notes.md \
                   coverage.xml .phpunit.result.cache
SHARED_DIR      := shared

# Coverage threshold enforced by the `coverage` target (%).
COVERAGE_THRESHOLD := 80

DC := docker compose

.DEFAULT_GOAL := help
.PHONY: help build install install-hooks run validate test coverage mcp shell clean-shared clean reset

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-13s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------
# Container lifecycle
# ---------------------------------------------------------------------------

build: ## Build (or rebuild) the Docker image
	$(DC) build

install: ## Install Composer dependencies inside the container
	$(DC) run --rm app composer install --no-interaction --prefer-dist

install-hooks: ## Enable the git pre-push coverage gate (run once per clone)
	git config core.hooksPath "$(CURDIR)/.githooks"
	@echo "Git hooks path set to $(CURDIR)/.githooks — pre-push coverage gate is active."

# ---------------------------------------------------------------------------
# Pipeline execution
# ---------------------------------------------------------------------------

run: ## Run the full transaction-processing pipeline (end-to-end)
	$(DC) run --rm app php bin/run-pipeline

validate: ## Run the validator stage only (dry-run — no fraud scoring or settlement)
	$(DC) run --rm app php bin/validate-transactions

# ---------------------------------------------------------------------------
# Tests & coverage
# ---------------------------------------------------------------------------

test: ## Run the PHPUnit test suite (no coverage driver — fast)
	$(DC) run --rm app php vendor/bin/phpunit --no-coverage

coverage: ## Run tests with pcov coverage; exits non-zero if below $(COVERAGE_THRESHOLD)%
	@echo "Running tests with coverage (threshold: $(COVERAGE_THRESHOLD)%)..."
	$(DC) run --rm \
		-e XDEBUG_MODE=off \
		app php -d pcov.enabled=1 \
		vendor/bin/phpunit \
		--coverage-text \
		--coverage-clover coverage.xml \
		--colors=never 2>&1 | tee /tmp/coverage-output.txt; \
	LINES=$$(grep -E 'Lines:\s+[0-9]+\.[0-9]+%' /tmp/coverage-output.txt | head -1); \
	PCT=$$(echo "$$LINES" | grep -oE '[0-9]+\.[0-9]+' | head -1); \
	echo "Measured coverage: $${PCT}%"; \
	if [ -z "$$PCT" ]; then \
		echo "ERROR: Could not parse coverage percentage — treating as failure."; \
		exit 1; \
	fi; \
	PASS=$$(echo "$$PCT $(COVERAGE_THRESHOLD)" | awk '{print ($$1 >= $$2) ? "1" : "0"}'); \
	if [ "$$PASS" = "0" ]; then \
		echo "FAIL: coverage $${PCT}% is below the required $(COVERAGE_THRESHOLD)%."; \
		exit 1; \
	fi; \
	echo "OK: coverage $${PCT}% meets the $(COVERAGE_THRESHOLD)% threshold."

# ---------------------------------------------------------------------------
# MCP server (one-shot, stdio — no TTY so JSON-RPC frames stay clean)
# ---------------------------------------------------------------------------

mcp: ## Launch the pipeline-status MCP server in a one-shot container (stdio)
	$(DC) run --rm -T app php mcp/server.php

# ---------------------------------------------------------------------------
# Developer shell
# ---------------------------------------------------------------------------

shell: ## Open an interactive shell inside the app container
	$(DC) run --rm -it app bash

# ---------------------------------------------------------------------------
# Cleanup (existing targets — do not remove)
# ---------------------------------------------------------------------------

clean-shared: ## Empty the runtime message queues (shared/) between pipeline runs
	@rm -rf $(SHARED_DIR) 2>/dev/null || $(DC) run --rm app rm -rf $(SHARED_DIR)
	@echo "Cleared $(SHARED_DIR)/"

clean: clean-shared ## Remove dependencies and caches (vendor/, phpunit cache); keep generated source
	@rm -rf vendor coverage.xml .phpunit.result.cache 2>/dev/null \
		|| $(DC) run --rm app rm -rf vendor coverage.xml .phpunit.result.cache
	@echo "Removed vendor/ and caches"

reset: ## DESTRUCTIVE: delete ALL generated code + artifacts so code generation can be re-run
	@echo "This will delete the following generated artifacts:"
	@echo "  dirs : $(GENERATED_DIRS) $(SHARED_DIR)"
	@echo "  files: $(GENERATED_FILES)"
	@echo "Authored inputs (specification.md, AGENTS.md, TASKS.md,"
	@echo "sample-transactions.json, Makefile, .claude/) are kept."
	@if [ "$(FORCE)" != "1" ]; then \
		read -p "Proceed? [y/N] " ans; \
		[ "$$ans" = "y" ] || [ "$$ans" = "Y" ] || { echo "Aborted."; exit 1; }; \
	fi
	@# Remove container-written (root-owned) paths from inside the container first,
	@# while docker-compose.yml still exists. Best-effort: skip if Docker is absent.
	@-$(DC) run --rm app rm -rf $(SHARED_DIR) vendor coverage.xml .phpunit.result.cache 2>/dev/null || true
	@rm -rf $(GENERATED_DIRS) $(SHARED_DIR) 2>/dev/null \
		|| $(DC) run --rm app rm -rf $(GENERATED_DIRS) $(SHARED_DIR)
	@rm -f $(GENERATED_FILES)
	@echo "Reset complete — ready to re-run code generation."
