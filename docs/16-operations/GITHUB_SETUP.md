# GitHub Setup — Re-born

## Stato

Il repository ufficiale è:

```text
https://github.com/gianpaolobol/reborn
```

Il commit locale esiste ma il push può fallire se GitHub non è autenticato.

## Installare GitHub CLI su Windows

```powershell
winget install --id GitHub.cli
```

Chiudere e riaprire PowerShell.

## Autenticazione

```powershell
gh auth login
```

Scelte consigliate:

- GitHub.com
- HTTPS
- Login with browser

## Verificare remote

```powershell
git remote -v
```

Se manca:

```powershell
git remote add origin https://github.com/gianpaolobol/reborn.git
```

## Push

```powershell
git branch -M main
git push -u origin main
```

## Commit consigliato per questo bootstrap

```powershell
git add .
git commit -m "docs: bootstrap Re-born OS"
git push -u origin main
```
