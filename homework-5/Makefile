IMAGE   := lorem-mcp-server
SRC_DIR := $(shell pwd)/lorem-mcp-server

.PHONY: build install run test

build:
	docker build -t $(IMAGE) $(SRC_DIR)

# Populates vendor/ on the host (useful for IDE autocomplete).
# Requires the image to be built first (make build).
install: build
	docker run --rm \
		-v "$(SRC_DIR):/app" \
		--entrypoint composer \
		$(IMAGE) \
		install --no-dev --optimize-autoloader --no-interaction

run:
	docker run -i --rm $(IMAGE)

test:
	@printf '%s\n%s\n%s\n' \
		'{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}},"id":1}' \
		'{"jsonrpc":"2.0","method":"notifications/initialized","params":{}}' \
		'{"jsonrpc":"2.0","method":"tools/call","params":{"name":"read","arguments":{"wordCount":5}},"id":2}' \
	| docker run -i --rm $(IMAGE)
