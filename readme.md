# Tracy AI Debug

Composer package that logs Tracy exceptions to JSON files for AI agents.

## Installation

```bash
composer require zraly/tracy-ai-debug
```

## Configuration

Register the extension in your `config.neon`:

```neon
extensions:
    aiDebug: Zraly\AiDebug\DI\AiDebugExtension

aiDebug:
    logDir: %appDir%/../log/ai-debug      # Default
    enabled: true                          # Default, disable in production
    snippetLines: 10                       # Lines of code context
```

## Usage

Once configured, all Tracy exceptions are automatically logged to JSON files in the specified directory.

### JSON Output Example

```json
{
    "type": "TypeError",
    "message": "Argument #1 must be of type string, null given",
    "file": "/app/Presenters/HomePresenter.php",
    "line": 42,
    "priority": "error",
    "timestamp": "2026-01-26T17:06:13+01:00",
    "stackTrace": [
        {
            "file": "/app/Presenters/HomePresenter.php",
            "line": 42,
            "function": "processData",
            "class": "App\\Presenters\\HomePresenter"
        }
    ],
    "codeSnippet": {
        "startLine": 37,
        "endLine": 47,
        "errorLine": 42,
        "code": {
            "37": "    public function processData(string $data): void",
            "42": "        $result = $this->service->handle($data);"
        }
    },
    "context": {
        "request": {
            "uri": "/home/process",
            "method": "POST"
        }
    }
}
```

### AI Agent Workflow

Create `.agent/workflows/fix-error.md` in your project:

```markdown
---
description: Analyze and fix the latest Tracy error from AI debug log
---

# Fix Tracy Error

1. **Read the latest error**
   \`\`\`bash
   cat log/ai-debug/latest.json
   \`\`\`

2. **Analyze**: Check `type`, `message`, `file`, `line`, and `codeSnippet`

3. **View the file**: Open the file at the error line

4. **Fix**: Implement the necessary code changes

5. **Verify**: Have user reproduce - no new JSON should appear
```

**Usage:** Type `@fix-error` in your AI assistant chat. The agent will read the workflow and fix the latest error.


## Features

- ✅ Exports exceptions to structured JSON
- ✅ Includes code snippets around error
- ✅ Captures full stack trace with arguments
- ✅ Extracts context variables
- ✅ Request information (URI, method, IP)
- ✅ Previous exception chain
- ✅ Creates `latest.json` symlink for quick access
- ✅ Preserves original Tracy logging
- ✅ Captures BlueScreen errors in development mode
- ✅ Handles string messages (warnings, notices)

## License

MIT
