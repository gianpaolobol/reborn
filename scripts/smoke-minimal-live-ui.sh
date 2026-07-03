#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-${1:-http://127.0.0.1:8080}}"
ROOT_HTML="$(mktemp)"
PROTOTYPE_HTML="$(mktemp)"
cleanup() {
  rm -f "$ROOT_HTML" "$PROTOTYPE_HTML"
}
trap cleanup EXIT

php -l public/index.php >/dev/null
php -l config/ai.php >/dev/null
php -l config/marketplace_search.php >/dev/null
php -l src/AI/Application/RequestRecognitionJobService.php >/dev/null
php -l src/Marketplace/Application/SearchReplacementModelService.php >/dev/null
node --check public/prototype/assets/js/app.js >/dev/null
node --check public/prototype/assets/js/api-client.js >/dev/null

curl -fsSL "$BASE_URL/" -o "$ROOT_HTML"
curl -fsSL "$BASE_URL/prototype/index.html" -o "$PROTOTYPE_HTML"
curl -fsSL "$BASE_URL/api/health" | grep -q '"status"[[:space:]]*:[[:space:]]*"ok"'

grep -q 'Carica foto e riconosci' public/prototype/assets/js/app.js
grep -q 'Criteri di ricerca' public/prototype/assets/js/app.js
grep -q 'Identificazione' public/prototype/assets/js/app.js
grep -q 'Richiesta di identificazione assistita' public/prototype/assets/js/app.js
grep -q 'Carica il modello 3D corrispondente alla tua ricerca' public/prototype/assets/js/app.js
grep -q 'Link al modello 3D<input' public/prototype/assets/js/app.js
grep -q 'File modello 3D<input' public/prototype/assets/js/app.js
grep -q 'data-action="save-library"' public/prototype/assets/js/app.js
grep -q 'recognized-part-name' public/prototype/assets/js/app.js
grep -q 'confidence-chip' public/prototype/assets/js/app.js
grep -q 'Marca/Codice' public/prototype/assets/js/app.js
grep -q 'Dimensioni' public/prototype/assets/js/app.js
grep -q 'Scegli i criteri di ricerca per trovare il tuo ricambio' public/prototype/assets/js/app.js
grep -q 'Ricerca composta' public/prototype/assets/js/app.js
grep -q 'composedQueryInput' public/prototype/assets/js/app.js
grep -q 'criteriaSearchQuery(result)' public/prototype/assets/js/app.js
grep -q 'updateMarketplaceLinkTargets' public/prototype/assets/js/app.js
grep -q 'data-marketplace-source' public/prototype/assets/js/app.js
grep -q 'ti aiuteremo a trovare il tuo ricambio' public/prototype/assets/js/app.js
grep -q 'Email<input' public/prototype/assets/js/app.js
grep -q 'Nome<input' public/prototype/assets/js/app.js
grep -q 'significantWords(value, 3' public/prototype/assets/js/app.js
grep -q 'href="/prototype/assets/css/reborn.css' "$ROOT_HTML"
grep -q 'src="/prototype/assets/js/app.js' "$ROOT_HTML"

if grep -q 'Controllare \.env\|Verifica avanzata non completata\|slicer configurato\|Richiedi stampa\|Apri migliore ricerca\|Ricerche generate\|STL 3MF ricambio stampa 3D\|Scegli quali informazioni usare\|La ricerca composta resta pulita\|Pezzo riconosciuto\|Pezzo probabilmente riconosciuto\|Per procedere con la riproduzione\|Invia a Re-born una richiesta\|Email per risposta\|Nome facoltativo\|significantWords(value, 4\|Fonte del modello\|Parametri di ricerca\|Ricerca usata\|Link al modello 3D opzionale\|Il file viene salvato nella libreria Re-born' public/prototype/assets/js/app.js; then
  echo "Consumer UI still contains technical or outdated copy." >&2
  exit 1
fi

if grep -q 'MARKETPLACE_SEARCH_FETCH_ENABLED=true\|WEB_STL_SEARCH_ENABLED=true' .env.example .env.ci.example; then
  echo "Marketplace scraping/fetch defaults must stay disabled in examples." >&2
  exit 1
fi

echo "Minimal live UI smoke passed at $BASE_URL"
