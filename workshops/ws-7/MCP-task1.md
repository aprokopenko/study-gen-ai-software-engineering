## 1. Configure Jira MCP Server

Add Atlassian Jira MCP server to enable issue tracking and project management capabilities via Claude Desktop.

### What This Enables

Once configured, the MCP server provides access to Jira functionality:

- **Issue Management**: Create, read, update, and search Jira issues
- **Project Access**: Query project information and configurations
- **Sprint Operations**: View and manage sprint data
- **Status Updates**: Transition issues through workflows
- **Comments & Attachments**: Add comments and manage issue attachments

### Configuration Steps

1. **Add the MCP server**:
   ```bash
   claude mcp add --transport http atlassian https://mcp.atlassian.com/v1/mcp
   ```

2. **Authenticate with Atlassian**:
   - The command will prompt for authentication
   - Follow the OAuth flow to authorize Claude Desktop
   - Grant necessary permissions for your Jira workspace

3. **Verify installation**:
   ```bash
   claude mcp list
   ```
   - Confirm `atlassian` appears in the list of configured servers

4. **Test the connection**:
   - Open Claude Desktop
   - Try a query like: "List my recent Jira issues"
   - Verify the MCP server responds with issue data

### Authentication Requirements

- **Atlassian Account**: Must have valid Atlassian/Jira account
- **Permissions**: Account needs appropriate Jira project access
- **API Access**: Jira workspace must allow API/MCP connections

### Troubleshooting

| Issue | Solution |
|-------|----------|
| Authentication fails | Re-run `claude mcp add` and complete OAuth flow |
| Server not responding | Check `claude mcp list` and restart Claude Desktop |
| Permission denied | Verify account has access to target Jira projects |
| Connection timeout | Check network and Jira workspace availability |

### Usage Example

After configuration, you can interact with Jira directly:

```
User: "Show me all issues in project ABC that are in progress"
Claude: [Uses MCP to query Jira and returns results]

User: "Create a new bug in project XYZ with title 'Login page error'"
Claude: [Creates issue via MCP and confirms with issue key]
```

### References

- Atlassian MCP Documentation: https://support.atlassian.com/mcp/
- Claude MCP Guide: https://docs.anthropic.com/mcp/
- Jira API Reference: https://developer.atlassian.com/cloud/jira/platform/rest/v3/
