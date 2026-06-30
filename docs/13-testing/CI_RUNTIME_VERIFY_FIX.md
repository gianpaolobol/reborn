# CI Runtime Verification Fix

Step 38 runtime verification now uses `scripts/ci-verify-runtime.php` instead of inline `php -r` commands.

Reason: inline `php -r` snippets containing PHP variables such as `$pdo` are fragile inside GitHub Actions Bash blocks because shell expansion can corrupt the PHP code before PHP receives it.

Required marker in Actions logs:

```text
STEP38_RUNTIME_SCRIPT_VERIFY_V4
```

If the workflow still fails with `PHP Parse error: unexpected token ")" in Command line code`, GitHub is running an older workflow commit that still contains inline `php -r`.
