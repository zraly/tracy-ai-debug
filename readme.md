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

This package keeps AI guidance minimal and tool-agnostic:

- `/AGENTS.md` is the canonical project instruction file.
- `/docs/ai/fix-tracy-error.md` is a short reusable fix flow.

If your AI tool supports project instruction files, point it to `AGENTS.md`.  
If it supports custom commands/skills, map them to `docs/ai/fix-tracy-error.md`.

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
