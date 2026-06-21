# Makefile — homework-6 developer tooling
#
# Cleanup helpers so you can wipe AI-generated artifacts and re-run code
# generation from a clean slate. Authored inputs are NEVER touched:
#   specification.md, AGENTS.md, TASKS.md, sample-transactions.json,
#   this Makefile, and the whole .claude/ directory.
#
# The code-generation agent (Task 1 in specification.md) extends this file with
# build / install / run / validate / test / coverage / mcp / shell targets.
# Keep the cleanup targets below when doing so.

SHELL := /bin/bash

# Artifacts produced by code generation (safe to delete and regenerate).
GENERATED_DIRS  := src tests bin mcp scripts docs context vendor
GENERATED_FILES := composer.json composer.lock phpunit.xml phpunit.xml.dist \
                   Dockerfile docker-compose.yml .dockerignore \
                   README.md HOWTORUN.md research-notes.md \
                   coverage.xml .phpunit.result.cache
SHARED_DIR      := shared

# The container runs as root, so files it writes under shared/ (etc.) are root-owned
# on the host and a plain `rm` is denied. When that happens, fall back to deleting
# from inside the container (root), which sees the project bind-mounted at /app.
DC := docker compose

.DEFAULT_GOAL := help
.PHONY: help clean-shared clean reset

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-13s\033[0m %s\n", $$1, $$2}'

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
