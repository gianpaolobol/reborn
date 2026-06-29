# GitHub Workflow

## First push

```powershell
cd C:\REBORN\REBORN
git status
git add .
git commit -m "docs: add MVP delivery pack"
git push -u origin main
```

If authentication fails:

```powershell
winget install --id GitHub.cli
gh auth login
git push -u origin main
```

## Branching

Use short feature branches:

```text
feature/identity-auth
feature/repair-case-flow
feature/admin-classification
feature/provider-quotes
feature/knowledge-signals
```

## Commit style

```text
docs: update MVP tickets
feat: add repair case creation
fix: handle photo upload validation
chore: add database migration
refactor: isolate repair service
```

## Pull request rule

Every PR must answer:

1. Which repair problem does this solve?
2. Which Knowledge Graph or AI learning signal does it create?
3. Which user flow or ticket does it implement?
4. What are the failure states?
