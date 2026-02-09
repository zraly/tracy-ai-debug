# Fix Tracy Error

Use this flow in any AI coding assistant.

1. Read the latest error:
   ```bash
   cat log/ai-debug/latest.json || cat "$(ls -t log/ai-debug/*.json | head -n 1)"
   ```
2. Analyze root cause using:
   - `type`
   - `message`
   - `file`
   - `line`
   - `codeSnippet`
   - `stackTrace`
3. Apply the smallest safe patch.
4. Run tests/checks relevant to the changed code.
5. Confirm the issue is resolved and summarize the fix.
