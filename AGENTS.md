# Agent Instructions

This repository provides Tracy errors in JSON format for AI-assisted debugging.

## Inputs

- Primary: `log/ai-debug/latest.json`
- Fallback: newest `log/ai-debug/*.json`

## Default workflow

1. Read the latest error JSON.
2. Identify root cause from `message`, `file`, `line`, `codeSnippet`, and `stackTrace`.
3. Implement the smallest safe fix.
4. Verify with relevant tests/checks.
5. Summarize what changed and how it was verified.

## Constraints

- Keep changes minimal and focused on the reported error.
- Preserve existing behavior outside the fix scope.
- Do not remove logging or reduce exception detail quality.
