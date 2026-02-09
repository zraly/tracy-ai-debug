# Tracy AI Debug

Composer package that logs Tracy exceptions to JSON files for AI agents.

## Installation

```bash
composer require --dev zraly/tracy-ai-debug
```

## Configuration (development only)

If the package is installed in `require-dev`, register the extension only in a development config file (for example `config/local.neon`).

```neon
# config/local.neon
extensions:
	aiDebug: Zraly\AiDebug\DI\AiDebugExtension

aiDebug:
	logDir: %appDir%/../log/ai-debug      # Default
	enabled: true                          # Default
	snippetLines: 10                       # Lines of code context
```

## Usage

Once configured, Tracy errors are exported to JSON files in the configured directory.

## AI Agent Workflow

### Recommended (assistant-agnostic)

1. **Read latest error JSON**
   ```bash
   cat log/ai-debug/latest.json || cat "$(ls -t log/ai-debug/*.json | head -n 1)"
   ```
2. **Analyze root cause**: `type`, `message`, `file`, `line`, `codeSnippet`, `stackTrace`.
3. **Fix minimal scope** in source code.
4. **Verify** by running project tests/checks.
5. **Reproduce once** and confirm that no new error JSON is generated for the same issue.

### Optional: custom command/workflow in your AI assistant

If your assistant supports custom commands, create one such as `@fix-tracy-error` and map it to the same five steps above.

## Features

- ✅ Exports exceptions to structured JSON
- ✅ Includes code snippets around error
- ✅ Captures full stack trace with arguments
- ✅ Extracts context variables
- ✅ Redacts common secret-like keys in context variables
- ✅ Request information (URI, method, IP)
- ✅ Previous exception chain
- ✅ Keeps `latest.json` pointer for quick access (symlink with regular-file fallback)
- ✅ Preserves original Tracy logging
- ✅ Captures BlueScreen errors in development mode
- ✅ Handles string messages (warnings, notices)

## License

MIT
