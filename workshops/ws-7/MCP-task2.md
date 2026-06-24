## 2. Configure Context7 MCP Server

Add Context7 MCP server to access up-to-date, version-specific documentation and code examples directly from source repositories.

### What This Enables

Context7 pulls live documentation and code examples from official sources and places them directly into your prompt context:

- **Version-Specific Docs**: Get documentation for exact library/framework versions
- **Code Examples**: Access real, tested code samples from official repos
- **Up-to-Date Content**: Always fetches latest documentation from source
- **Multi-Framework Support**: Works with popular frameworks and libraries
- **Contextual Help**: Relevant docs based on your current coding task

### Configuration Steps

1. **Add the MCP server**:
   ```bash
   claude mcp add --scope user context7 -- npx -y @upstash/context7-mcp
   ```
   - The `--scope user` flag makes it available across all projects
   - `npx -y` automatically downloads and runs the latest version

2. **Optional: Add API Key for higher rate limits**:
   ```bash
   claude mcp add --scope user context7 -- npx -y @upstash/context7-mcp --api-key YOUR_API_KEY
   ```
   - Without API key: Lower rate limits (sufficient for personal use)
   - With API key: Higher quotas (recommended for heavy usage)
   - Get API key from: https://github.com/upstash/context7

3. **Verify installation**:
   ```bash
   claude mcp list
   ```
   - Confirm `context7` appears in the list of configured servers

4. **Test the connection**:
   - Open Claude Desktop
   - Try: "Show me React 18 useState documentation"
   - Verify Context7 fetches and returns the docs

### Configuration Options

```bash
# Basic installation (no API key)
claude mcp add --scope user context7 -- npx -y @upstash/context7-mcp

# With API key for higher limits
claude mcp add --scope user context7 -- npx -y @upstash/context7-mcp --api-key YOUR_KEY

# Project-specific (omit --scope user)
claude mcp add context7 -- npx -y @upstash/context7-mcp
```

### References

- Context7 GitHub: https://github.com/upstash/context7
- Upstash Documentation: https://upstash.com/docs
