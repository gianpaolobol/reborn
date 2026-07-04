(function () {
  'use strict';

  const app = document.getElementById('app');
  const demoUser = { email: 'repair.user@reborn.local', password: 'password' };
  const MAX_IMAGES = 8;
  const MAX_IMAGE_BYTES = 15 * 1024 * 1024;
  const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

  const state = {
    server: 'checking',
    serverText: 'Server in collegamento',
    busy: false,
    progress: 0,
    progressText: 'In attesa di immagini.',
    previews: [],
    files: [],
    repairCase: null,
    attachments: [],
    recognitionJob: null,
    error: '',
    activeAction: '',
    manualQuery: '',
    selectedCriteria: null,
    selectedLinks: null,
    linkNotes: '',
    libraryContribution: null,
    libraryContributionError: '',
    libraryContributionBusy: false,
    libraryMatches: [],
    libraryMatchesBusy: false,
    forceOtherModelsSearch: false,
    modelPreferenceBusy: '',
    downloadedModels: {},
    preferredModels: {},
    downloadSessionId: 'reborn_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2),
    assistRequest: null
  };

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function clampPercent(value) {
    return Math.max(0, Math.min(100, Math.round(Number(value || 0))));
  }

  function setProgress(percent, text) {
    state.progress = clampPercent(percent);
    state.progressText = text || state.progressText;
    render();
  }

  function clearPreviews() {
    state.previews.forEach(function (item) {
      if (item.url) {
        URL.revokeObjectURL(item.url);
      }
    });
    state.previews = [];
  }

  function extensionOf(fileName) {
    const parts = String(fileName || '').toLowerCase().split('.');
    return parts.length > 1 ? parts.pop() : '';
  }

  function validateImageFiles(fileList) {
    const files = Array.from(fileList || []);
    if (!files.length) {
      return { files: [], error: 'Seleziona almeno una immagine.' };
    }

    if (files.length > MAX_IMAGES) {
      return { files: [], error: 'Puoi caricare al massimo ' + String(MAX_IMAGES) + ' immagini per volta.' };
    }

    for (const file of files) {
      const type = String(file.type || '').toLowerCase();
      const extension = extensionOf(file.name);
      if (ALLOWED_IMAGE_TYPES.indexOf(type) < 0 || ALLOWED_EXTENSIONS.indexOf(extension) < 0) {
        return { files: [], error: 'Formato non supportato. Usa JPG, PNG o WEBP.' };
      }

      if (file.size > MAX_IMAGE_BYTES) {
        return { files: [], error: 'Una immagine è troppo grande. Il limite è 15 MB per file.' };
      }
    }

    return { files: files, error: '' };
  }

  function rememberFiles(fileList) {
    clearPreviews();
    const validation = validateImageFiles(fileList);
    state.files = validation.files;
    state.error = validation.error;

    state.previews = state.files.map(function (file, index) {
      return {
        id: String(Date.now()) + '-' + String(index),
        name: file.name || ('immagine-' + String(index + 1) + '.jpg'),
        url: URL.createObjectURL(file)
      };
    });

    return !validation.error;
  }

  function cleanList(values) {
    if (!Array.isArray(values)) {
      return [];
    }
    return values.map(function (item) { return String(item || '').trim(); }).filter(Boolean);
  }



  function isPlaceholderText(value) {
    const text = String(value || '').trim().toLowerCase();
    return !text || text === 'non letto' || text === 'non letta' || text === 'non lette' || text === 'non disponibile' || text === 'non disponibili';
  }

  function significantWords(value, maxWords) {
    const stopwords = {
      'a': true, 'ad': true, 'al': true, 'alla': true, 'alle': true, 'allo': true, 'ai': true, 'agli': true,
      'con': true, 'da': true, 'dal': true, 'dalla': true, 'delle': true, 'dei': true, 'del': true, 'di': true,
      'e': true, 'ed': true, 'il': true, 'la': true, 'le': true, 'lo': true, 'gli': true, 'i': true, 'in': true,
      'non': true, 'o': true, 'per': true, 'su': true, 'sul': true, 'sulla': true, 'un': true, 'una': true, 'uno': true,
      'pezzo': true, 'ricambio': true, 'modello': true, 'file': true, 'stampa': true, 'stampabile': true,
      'the': true, 'and': true, 'or': true, 'of': true, 'for': true, 'with': true, 'to': true, 'from': true, 'by': true,
      'part': true, 'replacement': true, 'spare': true, 'model': true, 'print': true, 'printable': true
    };
    const raw = String(value || '')
      .replace(/pezzo\s*:/ig, ' ')
      .replace(/[\/|,;:()\[\]{}]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (!raw) {
      return '';
    }
    const words = raw.split(' ');
    const result = [];
    const seen = {};
    words.forEach(function (word) {
      const cleaned = word.replace(/^[-_.]+|[-_.]+$/g, '').trim();
      if (!cleaned) {
        return;
      }
      const key = cleaned.toLowerCase();
      if (stopwords[key] || seen[key]) {
        return;
      }
      seen[key] = true;
      result.push(cleaned);
    });
    const fallback = result.length ? result : words.filter(Boolean);
    return fallback.slice(0, maxWords || 3).join(' ');
  }

  function conciseIdentifier(value) {
    if (isPlaceholderText(value)) {
      return '';
    }
    return significantWords(value, 3) || String(value || '').trim();
  }

  function valueFromArray(values, fallback) {
    const clean = cleanList(values);
    return clean.length ? clean.join(', ') : (fallback || 'Non letto');
  }

  function resultFromJob() {
    const job = state.recognitionJob || {};
    return job.result_json || job.result || null;
  }

  function isProviderFailure(result) {
    const job = state.recognitionJob || {};
    const mode = String(result && result.recognition_mode ? result.recognition_mode : '');
    const providerStatus = String(result && result.ai_provider && result.ai_provider.status ? result.ai_provider.status : '');
    return job.status === 'failed' || providerStatus === 'error_fallback' || mode.indexOf('fallback_after_') === 0 || mode.indexOf('mock') >= 0 || mode.indexOf('deterministic') >= 0 || mode === 'live_provider_unavailable';
  }

  function recognitionStatus(result) {
    return String(result && result.identification && result.identification.status ? result.identification.status : 'unclear');
  }

  function isPreliminaryRecognition(result) {
    const verification = result && result.verification ? result.verification : {};
    const gate = result && result.quality_gate ? result.quality_gate : {};
    const verificationStatus = String(verification.status || gate.verification_status || '');
    return verificationStatus.indexOf('preliminary') >= 0 || verificationStatus === 'pending_verifier';
  }

  function confidenceScore(result) {
    const gate = result && result.quality_gate ? result.quality_gate : {};
    if (gate.quality_score !== undefined && gate.quality_score !== null && gate.quality_score !== '') {
      return clampPercent(gate.quality_score);
    }

    const raw = Number(result && result.object_guess ? result.object_guess.confidence : 0);
    if (!Number.isFinite(raw) || raw <= 0) {
      return 0;
    }
    return raw <= 1 ? clampPercent(raw * 100) : clampPercent(raw);
  }

  function confidenceLabel(score) {
    if (score >= 80) {
      return 'Alta';
    }
    if (score >= 60) {
      return 'Media';
    }
    if (score > 0) {
      return 'Bassa';
    }
    return 'Non disponibile';
  }

  function qualityNoticeHtml(result) {
    const score = confidenceScore(result);
    if (!isPreliminaryRecognition(result)) {
      return '';
    }

    return '<div class="quality-notice"><strong>Riconoscimento preliminare</strong>' +
      '<p>Serve una conferma prima di procedere. Aggiungi una foto frontale, laterale o con una misura visibile.</p>' +
      (score > 0 ? '<p>Affidabilità stimata: ' + escapeHtml(confidenceLabel(score)) + ' (' + String(score) + '/100).</p>' : '') +
      '</div>';
  }

  function field(label, value) {
    return '<p><strong>' + escapeHtml(label) + '</strong><span>' + escapeHtml(value || 'Non disponibile') + '</span></p>';
  }

  function uniqueValues(values) {
    const seen = {};
    const result = [];
    cleanList(values).forEach(function (item) {
      const key = item.toLowerCase();
      if (!seen[key]) {
        seen[key] = true;
        result.push(item);
      }
    });
    return result;
  }

  function addCriterion(list, id, label, value, checked) {
    if (isPlaceholderText(value)) {
      return;
    }
    const text = conciseIdentifier(value);
    if (!text) {
      return;
    }
    list.push({ id: id, label: label, value: text, checked: checked !== false });
  }

  function criteriaFromResult(result) {
    const identification = result.identification || {};
    const partSpec = result.part_spec || {};
    const brief = result.replacement_part_brief || {};
    const objectGuess = result.object_guess || {};
    const criteria = [];

    addCriterion(criteria, 'part-it', 'Nome pezzo', partSpec.name_it || identification.commercial_name || objectGuess.label, true);
    addCriterion(criteria, 'part-en', 'Nome inglese', partSpec.name_en, true);
    addCriterion(criteria, 'function', 'Funzione', brief.probable_function || partSpec.appliance_context, true);
    addCriterion(criteria, 'part-number', 'Marca/Codice', identification.part_number, true);
    addCriterion(criteria, 'brand', 'Marca', identification.brand, true);
    addCriterion(criteria, 'material', 'Materiale', brief.material_hint || partSpec.material_hint, false);

    uniqueValues(identification.visible_text || []).slice(0, 3).forEach(function (text, index) {
      addCriterion(criteria, 'visible-text-' + String(index), 'Testo visibile', text, index === 0);
    });

    uniqueValues(partSpec.known_dimensions || brief.critical_dimensions || []).slice(0, 3).forEach(function (text, index) {
      addCriterion(criteria, 'dimension-' + String(index), 'Misura', text, false);
    });

    return criteria;
  }

  function selectedCriteriaMap(criteria) {
    if (!state.selectedCriteria) {
      const initial = {};
      criteria.forEach(function (criterion) {
        initial[criterion.id] = criterion.checked;
      });
      state.selectedCriteria = initial;
    }
    return state.selectedCriteria;
  }

  function selectedCriteriaValues(result) {
    const criteria = criteriaFromResult(result);
    const selected = selectedCriteriaMap(criteria);
    return criteria.filter(function (criterion) {
      return selected[criterion.id] === true;
    }).map(function (criterion) {
      return criterion.value;
    });
  }

  function compactSearchQuery(values) {
    const seen = {};
    const tokens = [];
    cleanList(values).forEach(function (value) {
      String(value || '').replace(/[\/|,;:()\[\]{}]+/g, ' ').split(/\s+/).forEach(function (word) {
        const cleaned = word.replace(/^[-_.]+|[-_.]+$/g, '').trim();
        if (!cleaned) {
          return;
        }
        const key = cleaned.toLowerCase();
        if (seen[key]) {
          return;
        }
        seen[key] = true;
        tokens.push(cleaned);
      });
    });
    return tokens.join(' ').trim();
  }

  function criteriaSearchQuery(result) {
    const values = selectedCriteriaValues(result);
    return compactSearchQuery(values) || resultPartName(result);
  }

  function selectedSearchQuery(result) {
    const manual = String(state.manualQuery || '').trim();
    return manual || criteriaSearchQuery(result);
  }

  function searchCriteriaHtml(result) {
    const criteria = criteriaFromResult(result);
    if (!criteria.length) {
      return '<div class="criteria-panel muted"><strong>Criteri di ricerca</strong><p>L’analisi non ha ancora estratto criteri sufficienti. Usa il pulsante Identificazione per scrivere tu la descrizione.</p></div>';
    }

    const selected = selectedCriteriaMap(criteria);
    const items = criteria.map(function (criterion) {
      const checked = selected[criterion.id] === true ? ' checked' : '';
      return '<label class="criteria-option"><input type="checkbox" data-criterion-id="' + escapeHtml(criterion.id) + '"' + checked + ' />' +
        '<span><strong>' + escapeHtml(criterion.label) + '</strong>' + escapeHtml(criterion.value) + '</span></label>';
    }).join('');

    const query = selectedSearchQuery(result);
    return '<div class="criteria-panel"><strong>Criteri di ricerca</strong>' +
      '<div class="criteria-grid">' + items + '</div>' +
      '<label class="generated-query"><span>Ricerca composta</span><input id="composedQueryInput" name="composedQuery" value="' + escapeHtml(query) + '" placeholder="Modifica la ricerca" /></label>' +
      marketplaceManualLinksHtml(query) +
      '</div>';
  }

  function marketplaceSearchUrl(source, query) {
    const encoded = encodeURIComponent(String(query || '').trim());
    if (!encoded) {
      return '#';
    }
    if (source === 'thingiverse') {
      return 'https://www.thingiverse.com/search?q=' + encoded + '&type=things&sort=relevant';
    }
    if (source === 'makerworld') {
      return 'https://makerworld.com/en/search/models?keyword=' + encoded;
    }
    return 'https://www.printables.com/search/models?q=' + encoded;
  }

  function marketplaceManualLinksHtml(query) {
    const clean = String(query || '').trim();
    if (!clean) {
      return '';
    }
    return '<div class="manual-links site-links" aria-label="Link di ricerca manuale">' +
      '<a data-marketplace-source="printables" href="' + escapeHtml(marketplaceSearchUrl('printables', clean)) + '" target="_blank" rel="noopener noreferrer">Cerca su Printables</a>' +
      '<a data-marketplace-source="thingiverse" href="' + escapeHtml(marketplaceSearchUrl('thingiverse', clean)) + '" target="_blank" rel="noopener noreferrer">Cerca su Thingiverse</a>' +
      '<a data-marketplace-source="makerworld" href="' + escapeHtml(marketplaceSearchUrl('makerworld', clean)) + '" target="_blank" rel="noopener noreferrer">Cerca su MakerWorld</a>' +
      '</div>';
  }

  function updateMarketplaceLinkTargets(query) {
    document.querySelectorAll('[data-marketplace-source]').forEach(function (link) {
      const source = link.getAttribute('data-marketplace-source') || 'printables';
      link.setAttribute('href', marketplaceSearchUrl(source, query));
    });
  }

  function modelDownloadUrl(match) {
    const url = String(match && match.download_url ? match.download_url : '');
    if (!url) {
      return '';
    }
    const separator = url.indexOf('?') >= 0 ? '&' : '?';
    return url + separator + 'download_session=' + encodeURIComponent(state.downloadSessionId || '');
  }

  function modelPreviewUrl(match) {
    const url = String(match && match.download_url ? match.download_url : '');
    if (!url) {
      return '';
    }
    const separator = url.indexOf('?') >= 0 ? '&' : '?';
    return url + separator + 'preview=1';
  }

  function modelViewerHtml(match) {
    const id = String(match && match.id ? match.id : '');
    const previewUrl = modelPreviewUrl(match);
    const fileName = String(match && match.file_name ? match.file_name : '');
    const extension = fileName.split('.').pop().toLowerCase();
    if (!id || !previewUrl) {
      return '<div class="model-viewer unavailable"><span>Anteprima 3D non disponibile</span></div>';
    }
    return '<div class="model-viewer" data-model-viewer-id="' + escapeHtml(id) + '" data-model-viewer-url="' + escapeHtml(previewUrl) + '" data-model-file-name="' + escapeHtml(fileName) + '">' +
      '<canvas width="260" height="180" aria-label="Anteprima 3D interattiva"></canvas>' +
      '<span>Trascina per ruotare</span>' +
      '</div>';
  }

  function hasAssociatedModels() {
    return Array.isArray(state.libraryMatches) && state.libraryMatches.length > 0;
  }

  function searchOtherModelsButtonHtml() {
    return '<div class="secondary-search-panel"><button class="action-button" type="button" data-action="search-other-models">Cerca altri modelli</button></div>';
  }

  function libraryMatchesHtml() {
    if (state.libraryMatchesBusy) {
      return '<div class="library-matches"><strong>Libreria Re-born</strong><p>Controllo se esistono già modelli associati a questo pezzo.</p></div>';
    }
    if (!state.libraryMatches || !state.libraryMatches.length) {
      return '';
    }
    const fromCache = (function () {
      const result = resultFromJob() || {};
      return String(result.source || '') === 'reborn_library_cache';
    })();
    const count = state.libraryMatches.length;
    const cards = state.libraryMatches.map(function (match) {
      const id = String(match.id || '');
      const downloadUrl = modelDownloadUrl(match);
      const download = downloadUrl ? '<a class="action-button small" href="' + escapeHtml(downloadUrl) + '" target="_blank" rel="noopener noreferrer" data-download-model-id="' + escapeHtml(id) + '">Scarica modello</a>' : '';
      const source = match.source ? 'Fonte: ' + match.source : 'Fonte da verificare';
      const score = match.match_score ? 'Compatibilità stimata: ' + String(match.match_score) + '/100' : 'Compatibilità da verificare';
      const downloads = Number(match.download_count || 0);
      const preferences = Number(match.preference_count || 0);
      const downloadedInPage = id && state.downloadedModels && state.downloadedModels[id] === true;
      const alreadyPreferred = id && state.preferredModels && state.preferredModels[id] === true;
      const prefDisabled = (!downloadedInPage || alreadyPreferred || state.modelPreferenceBusy === id) ? ' disabled' : '';
      const prefTitle = alreadyPreferred ? 'Preferenza già assegnata in questa pagina' : (downloadedInPage ? 'Assegna preferenza' : 'Scarica prima il modello per assegnare una preferenza');
      const preferenceButton = id ? '<button class="model-stat preference-button" type="button" data-action="prefer-model" data-model-id="' + escapeHtml(id) + '" title="' + escapeHtml(prefTitle) + '"' + prefDisabled + '><span class="reborn-rings" aria-hidden="true"></span><span>Preferenze ' + String(preferences) + '</span></button>' : '<span class="model-stat"><span class="reborn-rings" aria-hidden="true"></span><span>Preferenze ' + String(preferences) + '</span></span>';
      return '<article class="library-match-card model-card-with-viewer">' +
        '<strong class="model-title">' + escapeHtml(match.title || match.query || 'Modello 3D in libreria') + '</strong>' +
        '<div class="model-card-body">' +
          modelViewerHtml(match) +
          '<div class="model-card-info">' +
            '<p>' + escapeHtml(source) + ' · ' + escapeHtml(score) + '</p>' +
            '<div class="model-stats"><span class="model-stat">downloads ' + String(downloads) + '</span>' + preferenceButton + '</div>' +
            '<div class="match-actions">' + download + '</div>' +
          '</div>' +
        '</div>' +
      '</article>';
    }).join('');
    const intro = fromCache
      ? 'Ho trovato ' + String(count) + ' modell' + (count === 1 ? 'o già associato' : 'i già associati') + ' a questa immagine nella libreria Re-born.'
      : 'Ho trovato schede modello collegate a criteri o immagini simili. Verifica compatibilità e licenza prima della stampa.';
    const heading = fromCache ? 'Modelli 3D già disponibili' : 'Modelli già presenti nella libreria Re-born';
    return '<div class="library-matches' + (fromCache ? ' direct' : '') + '"><strong>' + escapeHtml(heading) + '</strong><p>' + escapeHtml(intro) + '</p>' + cards + '</div>';
  }


  function actionButtonsHtml(result) {
    return '<div class="action-panel" aria-label="Azioni finali">' +
      '<button class="action-button" type="button" data-action="identify">Identificazione</button>' +
      '<button class="action-button primary" type="button" data-action="assist">Richiesta di identificazione assistita</button>' +
      '</div>' +
      actionDetailHtml(result);
  }

  function actionDetailHtml(result) {
    if (state.activeAction === 'identify') {
      return identificationFormHtml(result);
    }
    if (state.activeAction === 'assist') {
      return assistedRequestHtml(result);
    }
    return '';
  }

  function libraryContributionHtml(result, query) {
    const saved = state.libraryContribution;
    const saving = state.libraryContributionBusy;
    // Il bottone parte disabilitato: viene abilitato da bindEvents quando l'utente sceglie un file.
    const disabled = ' disabled';
    const buttonText = saving ? 'Caricamento in corso' : 'Verifica modello 3D';
    return '<div class="library-contribution">' +
      '<strong>Carica il modello 3D corrispondente alla tua ricerca</strong>' +
      '<label>Link al modello 3D<input name="libraryUrl" type="url" placeholder="https://..." /></label>' +
      '<label>File modello 3D<input name="modelFile" type="file" accept=".stl,.3mf,.obj,.step,.stp,.zip" required /></label>' +
      '<label>Note per la verifica<textarea name="libraryNotes" rows="3" placeholder="Es. verificare foro, misure e compatibilità prima della stampa.">' + escapeHtml(state.linkNotes || '') + '</textarea></label>' +
      '<button class="action-button primary" type="button" data-action="save-library"' + disabled + '>' + escapeHtml(buttonText) + '</button>' +
      (state.libraryContributionError ? '<p class="error-note">' + escapeHtml(state.libraryContributionError) + '</p>' : '') +
      (saved ? '<p class="success-note">Modello 3D caricato nella libreria Re-born per verifica. ID: ' + escapeHtml(saved.contribution_id || saved.id || 'registrato') + '</p>' : '') +
      '</div>';
  }

  function identificationFormHtml(result) {
    const query = state.manualQuery || selectedSearchQuery(result);
    return '<form id="identificationForm" class="detail-card" enctype="multipart/form-data">' +
      '<strong>Identificazione</strong>' +
      '<p>Modifica i parametri di ricerca, apri i siti suggeriti e carica il modello 3D corrispondente alla tua ricerca per la verifica Re-born.</p>' +
      '<label>Descrizione da usare nella ricerca<input name="manualQuery" value="' + escapeHtml(query) + '" placeholder="es. manopola lavatrice Whirlpool foro D" /></label>' +
      '<button class="action-button primary" type="submit">Aggiorna criteri</button>' +
      marketplaceManualLinksHtml(query) +
      libraryContributionHtml(result, query) +
      '</form>';
  }

  function assistedRequestHtml(result) {
    const submitted = state.assistRequest;
    return '<form id="assistedRequestForm" class="detail-card">' +
      '<strong>Richiesta di identificazione assistita</strong>' +
      '<p>ti aiuteremo a trovare il tuo ricambio</p>' +
      '<label>Email<input name="customerEmail" type="email" required placeholder="nome@email.it" value="' + escapeHtml(submitted && submitted.email ? submitted.email : '') + '" /></label>' +
      '<label>Telefono<input name="customerPhone" type="tel" required placeholder="+39 ..." value="' + escapeHtml(submitted && submitted.phone ? submitted.phone : '') + '" /></label>' +
      '<label>Nome<input name="customerName" required placeholder="Nome" value="' + escapeHtml(submitted && submitted.name ? submitted.name : '') + '" /></label>' +
      '<label>Messaggio<textarea name="customerMessage" rows="4" placeholder="Aggiungi marca, modello, misure o urgenza."></textarea></label>' +
      '<button class="action-button primary" type="submit">Invia richiesta a Re-born</button>' +
      (submitted ? '<p class="success-note">Richiesta preparata per l’invio. Controlla il tuo programma email e inviala.</p>' : '') +
      '</form>';
  }

  function assistedBrief(result, contact) {
    const identification = result.identification || {};
    const partSpec = result.part_spec || {};
    const brief = result.replacement_part_brief || {};
    const selected = selectedCriteriaValues(result);
    const contribution = state.libraryContribution || {};
    const contributionLines = [];
    if (contribution && (contribution.contribution_id || contribution.id)) {
      contributionLines.push('Scheda verifica modello: ' + (contribution.contribution_id || contribution.id));
    }
    if (contribution && contribution.url) {
      contributionLines.push('Link modello: ' + contribution.url);
    }
    if (contribution && contribution.title) {
      contributionLines.push('Riferimento modello: ' + contribution.title);
    }
    if (contribution && contribution.file_name) {
      contributionLines.push('File caricato: ' + contribution.file_name);
    }
    const lines = [
      'Richiesta di identificazione assistita Re-born',
      'Email cliente: ' + ((contact && contact.email) || 'non indicata'),
      'Telefono cliente: ' + ((contact && contact.phone) || 'non indicato'),
      'Nome cliente: ' + ((contact && contact.name) || 'non indicato'),
      '',
      'Pezzo indicato: ' + resultPartName(result),
      'Funzione probabile: ' + (brief.probable_function || partSpec.appliance_context || 'da confermare'),
      'Codice/marcature: ' + (identification.part_number || valueFromArray(identification.visible_text, 'non disponibili')),
      'Misure note: ' + valueFromArray(partSpec.known_dimensions || brief.critical_dimensions, 'da misurare'),
      'Materiale suggerito: ' + (brief.material_hint || 'da confermare'),
      'Criteri scelti: ' + (selected.length ? selected.join(' | ') : 'da confermare'),
      'Ricerca composta: ' + selectedSearchQuery(result),
      '',
      'Modello 3D da verificare:',
      contributionLines.length ? contributionLines.join('\n') : 'nessun modello 3D ancora indicato',
      '',
      'Note cliente:',
      ((contact && contact.message) || state.linkNotes || 'nessuna nota'),
      '',
      'Nota: verificare identità del pezzo, compatibilità, misure e licenza del modello prima di ogni uso.'
    ];
    return lines.join('\n');
  }

  function resultPartName(result) {
    const identification = result.identification || {};
    const partSpec = result.part_spec || {};
    const objectGuess = result.object_guess || {};
    const raw = partSpec.name_it || identification.commercial_name || objectGuess.label || '';
    return raw ? (conciseIdentifier(raw) || raw) : 'Pezzo da confermare';
  }

  function associatedImagesHtml() {
    if (!state.previews || !state.previews.length) {
      return '';
    }
    const items = state.previews.map(function (item) {
      return '<figure class="associated-image"><img src="' + escapeHtml(item.url) + '" alt="Immagine associata al pezzo riconosciuto" /><figcaption>' + escapeHtml(item.name || 'immagine') + '</figcaption></figure>';
    }).join('');
    return '<div class="associated-images" aria-label="Immagini caricate associate">' + items + '</div>';
  }

  function resultHtml() {
    const result = resultFromJob();
    const job = state.recognitionJob || {};

    if (state.error) {
      return '<section id="recognitionResult" class="result-panel error" aria-live="polite">' +
        '<h2>Analisi non completata</h2>' +
        '<p>' + escapeHtml(state.error) + '</p>' +
        '<p>Puoi riprovare con immagini JPG, PNG o WEBP più nitide, oppure inviare una richiesta di identificazione assistita.</p>' +
        '</section>';
    }

    if (!result && job.status === 'failed') {
      return '<section id="recognitionResult" class="result-panel error" aria-live="polite">' +
        '<h2>Riconoscimento non effettuato</h2>' +
        '<p>L’analisi automatica non è al momento disponibile. Riprova più tardi o invia una richiesta di identificazione assistita.</p>' +
        '</section>';
    }

    if (!result) {
      return '<section id="recognitionResult" class="result-panel empty" aria-live="polite">' +
        '<h2>Carica una foto del pezzo</h2>' +
        '<p>Fotografa il pezzo rotto o il ricambio. Reborn lo riconosce e cerca il modello 3D compatibile.</p>' +
        '</section>';
    }

    if (isProviderFailure(result)) {
      return '<section id="recognitionResult" class="result-panel error" aria-live="polite">' +
        '<h2>Riconoscimento non effettuato</h2>' +
        '<p>L’analisi automatica non è al momento disponibile. Riprova più tardi o prosegui con una richiesta di identificazione assistita.</p>' +
        '</section>';
    }

    const status = recognitionStatus(result);
    if (status !== 'recognized') {
      return '<section id="recognitionResult" class="result-panel needs-more" aria-live="polite">' +
        '<h2>Servono altre immagini</h2>' +
        '<p>Carica una foto frontale, laterale, del pezzo montato o con una misura visibile.</p>' +
        actionButtonsHtml(result) +
        '</section>';
    }

    const identification = result.identification || {};
    const partSpec = result.part_spec || {};
    const brief = result.replacement_part_brief || {};
    const score = confidenceScore(result);
    const preliminary = isPreliminaryRecognition(result);
    const panelClass = preliminary ? 'result-panel recognized preliminary' : 'result-panel recognized';
    const part = resultPartName(result);
    const fn = brief.probable_function || partSpec.appliance_context || 'Funzione da confermare';
    const brandCodeValues = uniqueValues([
      identification.brand,
      identification.part_number,
      valueFromArray(identification.visible_text, '')
    ]);
    const brandCode = brandCodeValues.length ? brandCodeValues.join(' / ') : 'Non letto';
    const dimensionValues = cleanList(partSpec.known_dimensions || brief.critical_dimensions);
    const measures = dimensionValues.length ? conciseIdentifier(dimensionValues.join(' ')) : 'non disponibili';
    const reliability = score > 0 ? String(score) + '%' : 'Non disponibile';
    const reproductionNotice = 'Scegli i criteri di ricerca per trovare il tuo ricambio';

    const associatedModelsReady = hasAssociatedModels();
    const compactAssociatedView = associatedModelsReady && state.forceOtherModelsSearch !== true;

    if (compactAssociatedView) {
      return '<section id="recognitionResult" class="' + panelClass + '" aria-live="polite">' +
        '<div class="result-heading">' +
          '<h2 class="recognized-part-name">' + escapeHtml(part) + '</h2>' +
          '<div class="confidence-chip"><span>affidabilità</span><strong>' + escapeHtml(reliability) + '</strong></div>' +
        '</div>' +
        associatedImagesHtml() +
        qualityNoticeHtml(result) +
        libraryMatchesHtml() +
        searchOtherModelsButtonHtml() +
        '</section>';
    }

    return '<section id="recognitionResult" class="' + panelClass + '" aria-live="polite">' +
      '<div class="result-heading">' +
        '<h2 class="recognized-part-name">' + escapeHtml(part) + '</h2>' +
        '<div class="confidence-chip"><span>affidabilità</span><strong>' + escapeHtml(reliability) + '</strong></div>' +
      '</div>' +
      associatedImagesHtml() +
      (state.forceOtherModelsSearch === true && associatedModelsReady ? '<div class="secondary-search-instance"><strong>Nuova ricerca per questa immagine</strong><p>Usa la stessa immagine caricata per cercare e associare altri modelli compatibili.</p></div>' : '') +
      qualityNoticeHtml(result) +
      (state.forceOtherModelsSearch === true ? '' : libraryMatchesHtml()) +
      '<div class="result-fields">' +
      field('Funzione:', fn) +
      field('Marca/Codice:', brandCode) +
      field('Dimensioni:', measures) +
      '</div>' +
      '<p class="reproduction-notice">' + escapeHtml(reproductionNotice) + '</p>' +
      searchCriteriaHtml(result) +
      actionButtonsHtml(result) +
      '</section>';
  }

  function imageFingerprints() {
    return state.attachments.map(function (attachment, index) {
      const preview = state.previews[index] || {};
      return {
        id: String(attachment.id || ''),
        repair_case_id: String(attachment.repair_case_id || (state.repairCase && state.repairCase.id) || ''),
        original_filename: String(attachment.original_filename || preview.name || ''),
        mime_type: String(attachment.mime_type || ''),
        size_bytes: Number(attachment.size_bytes || 0),
        sha256: String(attachment.sha256 || '')
      };
    });
  }

  async function refreshLibraryMatches(result) {
    if (!result || !window.REBORN_API || !window.REBORN_API.searchLibraryMatches) {
      return;
    }
    state.libraryMatchesBusy = true;
    state.libraryMatches = [];
    render();
    try {
      const response = await window.REBORN_API.searchLibraryMatches({
        query: selectedSearchQuery(result),
        criteria: selectedCriteriaValues(result),
        recognition_summary: {
          part: resultPartName(result),
          confidence: confidenceScore(result),
          identification: result.identification || {},
          part_spec: result.part_spec || {},
          replacement_part_brief: result.replacement_part_brief || {}
        },
        attachment_ids: state.attachments.map(function (attachment) { return String(attachment.id || ''); }).filter(Boolean),
        image_fingerprints: imageFingerprints()
      });
      state.libraryMatches = Array.isArray(response.matches) ? response.matches : [];
    } catch (error) {
      state.libraryMatches = [];
    } finally {
      state.libraryMatchesBusy = false;
      render();
    }
  }

  function parseAsciiStl(text) {
    const vertices = [];
    const re = /vertex\s+([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)\s+([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)\s+([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)/ig;
    let match;
    while ((match = re.exec(text)) !== null) {
      vertices.push([Number(match[1]), Number(match[2]), Number(match[3])]);
    }
    return vertices;
  }

  function parseBinaryStl(buffer) {
    const view = new DataView(buffer);
    if (view.byteLength < 84) {
      return [];
    }
    const triangles = view.getUint32(80, true);
    const expected = 84 + triangles * 50;
    if (expected > view.byteLength || triangles > 200000) {
      return [];
    }
    const vertices = [];
    let offset = 84;
    for (let i = 0; i < triangles; i += 1) {
      offset += 12;
      for (let v = 0; v < 3; v += 1) {
        vertices.push([
          view.getFloat32(offset, true),
          view.getFloat32(offset + 4, true),
          view.getFloat32(offset + 8, true)
        ]);
        offset += 12;
      }
      offset += 2;
    }
    return vertices;
  }

  function parseObj(text) {
    const points = [];
    text.split(/\r?\n/).forEach(function (line) {
      const parts = line.trim().split(/\s+/);
      if (parts[0] === 'v' && parts.length >= 4) {
        points.push([Number(parts[1]), Number(parts[2]), Number(parts[3])]);
      }
    });
    return points.filter(function (point) {
      return point.every(Number.isFinite);
    });
  }

  function normalizePoints(points) {
    if (!points.length) {
      return [];
    }
    const min = [Infinity, Infinity, Infinity];
    const max = [-Infinity, -Infinity, -Infinity];
    points.forEach(function (p) {
      for (let i = 0; i < 3; i += 1) {
        min[i] = Math.min(min[i], p[i]);
        max[i] = Math.max(max[i], p[i]);
      }
    });
    const center = [(min[0] + max[0]) / 2, (min[1] + max[1]) / 2, (min[2] + max[2]) / 2];
    const scale = Math.max(max[0] - min[0], max[1] - min[1], max[2] - min[2]) || 1;
    const step = Math.max(1, Math.ceil(points.length / 2400));
    const normalized = [];
    for (let i = 0; i < points.length; i += step) {
      normalized.push([
        (points[i][0] - center[0]) / scale,
        (points[i][1] - center[1]) / scale,
        (points[i][2] - center[2]) / scale
      ]);
    }
    return normalized;
  }

  function drawModelPreview(canvas, points, rotX, rotY) {
    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = '#f7faf7';
    ctx.fillRect(0, 0, w, h);
    ctx.strokeStyle = '#d8ead8';
    ctx.strokeRect(0.5, 0.5, w - 1, h - 1);
    if (!points.length) {
      ctx.fillStyle = '#516051';
      ctx.font = '13px Arial, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Anteprima non disponibile', w / 2, h / 2);
      return;
    }
    const sinX = Math.sin(rotX);
    const cosX = Math.cos(rotX);
    const sinY = Math.sin(rotY);
    const cosY = Math.cos(rotY);
    const projected = points.map(function (p) {
      let x = p[0], y = p[1], z = p[2];
      const x1 = x * cosY - z * sinY;
      const z1 = x * sinY + z * cosY;
      const y1 = y * cosX - z1 * sinX;
      const z2 = y * sinX + z1 * cosX;
      const depth = 1.8 + z2;
      return {
        x: w / 2 + (x1 / depth) * w * 1.35,
        y: h / 2 - (y1 / depth) * h * 1.35,
        z: z2
      };
    }).sort(function (a, b) { return a.z - b.z; });
    ctx.fillStyle = '#1f8e2e';
    projected.forEach(function (p) {
      ctx.globalAlpha = 0.78;
      ctx.beginPath();
      ctx.arc(p.x, p.y, 1.5, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.globalAlpha = 1;
  }

  async function initModelViewer(viewer) {
    if (!viewer || viewer.dataset.viewerReady === '1') {
      return;
    }
    viewer.dataset.viewerReady = '1';
    const canvas = viewer.querySelector('canvas');
    const url = viewer.getAttribute('data-model-viewer-url') || '';
    const fileName = (viewer.getAttribute('data-model-file-name') || '').toLowerCase();
    if (!canvas || !url) {
      return;
    }
    let points = [];
    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      const buffer = await response.arrayBuffer();
      const text = new TextDecoder('utf-8', { fatal: false }).decode(buffer.slice(0, Math.min(buffer.byteLength, 2048)));
      if (fileName.endsWith('.obj')) {
        points = parseObj(new TextDecoder('utf-8', { fatal: false }).decode(buffer));
      } else if (fileName.endsWith('.stl') || text.trim().toLowerCase().startsWith('solid')) {
        const fullText = text.trim().toLowerCase().startsWith('solid') ? new TextDecoder('utf-8', { fatal: false }).decode(buffer) : '';
        points = fullText ? parseAsciiStl(fullText) : [];
        if (!points.length) {
          points = parseBinaryStl(buffer);
        }
      }
      points = normalizePoints(points);
    } catch (error) {
      points = [];
    }

    let rotX = -0.45;
    let rotY = 0.7;
    let dragging = false;
    let lastX = 0;
    let lastY = 0;
    drawModelPreview(canvas, points, rotX, rotY);

    canvas.addEventListener('pointerdown', function (event) {
      dragging = true;
      lastX = event.clientX;
      lastY = event.clientY;
      canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', function (event) {
      if (!dragging) {
        return;
      }
      const dx = event.clientX - lastX;
      const dy = event.clientY - lastY;
      lastX = event.clientX;
      lastY = event.clientY;
      rotY += dx * 0.015;
      rotX += dy * 0.015;
      drawModelPreview(canvas, points, rotX, rotY);
    });
    canvas.addEventListener('pointerup', function () {
      dragging = false;
    });
    canvas.addEventListener('pointercancel', function () {
      dragging = false;
    });
  }

  function initModelViewers() {
    document.querySelectorAll('[data-model-viewer-url]').forEach(function (viewer) {
      initModelViewer(viewer);
    });
  }

  function recordModelDownloadInPage(modelId) {
    const id = String(modelId || '').trim();
    if (!id) {
      return;
    }
    state.downloadedModels = state.downloadedModels || {};
    if (state.downloadedModels[id] === true) {
      return;
    }
    state.downloadedModels[id] = true;
    state.libraryMatches = (state.libraryMatches || []).map(function (match) {
      if (String(match.id || '') !== id) {
        return match;
      }
      return Object.assign({}, match, {
        download_count: Number(match.download_count || 0) + 1
      });
    });
    render();
  }

  async function recordModelPreference(modelId) {
    const id = String(modelId || '').trim();
    if (!id || !window.REBORN_API || !window.REBORN_API.preferLibraryModel) {
      return;
    }
    if (!state.downloadedModels || state.downloadedModels[id] !== true || (state.preferredModels && state.preferredModels[id] === true)) {
      return;
    }
    state.modelPreferenceBusy = id;
    render();
    try {
      const response = await window.REBORN_API.preferLibraryModel(id, true);
      const preferenceCount = Number(response.preference_count || 0);
      const downloadCount = Number(response.download_count || 0);
      state.preferredModels = state.preferredModels || {};
      state.preferredModels[id] = true;
      state.libraryMatches = (state.libraryMatches || []).map(function (match) {
        if (String(match.id || '') !== id) {
          return match;
        }
        return Object.assign({}, match, {
          preference_count: preferenceCount,
          download_count: downloadCount
        });
      });
    } catch (error) {
      state.error = 'Preferenza non registrata. Riprova tra poco.';
    } finally {
      state.modelPreferenceBusy = '';
      render();
    }
  }

  function heroHtml() {
    var camIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="#30A040" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.4"/></svg>';
    var scanIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="#30A040" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V5a1 1 0 0 1 1-1h2M20 7V5a1 1 0 0 0-1-1h-2M4 17v2a1 1 0 0 0 1 1h2M20 17v2a1 1 0 0 1-1 1h-2"/><path d="M3 12h18"/></svg>';
    var truckIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="#30A040" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v9H3zM14 9h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>';
    return '<section class="hero">' +
      '<p class="hero-kicker">Ripara, non ricomprare</p>' +
      '<h1 class="hero-title">Da una foto del pezzo rotto<br>al ricambio a casa tua</h1>' +
      '<p class="hero-lead">Fotografa il componente rotto. Reborn lo riconosce, trova il modello 3D compatibile e te lo fa stampare e spedire.</p>' +
      '<div class="hero-steps">' +
        '<div class="hero-step"><div class="hero-ic">' + camIcon + '</div><div class="hero-n">01</div><h3>Fotografa</h3><p>Scatta una foto del pezzo rotto o mancante.</p><span class="hero-arrow">&rarr;</span></div>' +
        '<div class="hero-step"><div class="hero-ic">' + scanIcon + '</div><div class="hero-n">02</div><h3>Reborn riconosce</h3><p>L\'AI identifica il pezzo e trova il modello 3D.</p><span class="hero-arrow">&rarr;</span></div>' +
        '<div class="hero-step"><div class="hero-ic">' + truckIcon + '</div><div class="hero-n">03</div><h3>Stampa e ricevi</h3><p>Scaricalo o fallo stampare e spedire a casa.</p></div>' +
      '</div>' +
    '</section>';
  }

  function render() {
    const ready = state.server === 'ready';
    const error = state.server === 'error';
    const buttonText = state.busy ? 'Analisi in corso' : (state.previews.length ? 'Carica nuove immagini' : 'Carica foto e riconosci');
    const disabled = !ready || state.busy ? ' disabled' : '';
    const progress = clampPercent(state.progress);
    const busyClass = state.busy ? ' is-busy' : '';
    const uploadStyle = state.busy
      ? ' style="background: linear-gradient(90deg, var(--reborn-green-dark) 0%, var(--reborn-green-dark) ' + String(progress) + '%, var(--reborn-green-pale) ' + String(progress) + '%, var(--reborn-green-pale) 100%); color: #ffffff;"'
      : '';

    app.innerHTML =
      '<header class="brand-header">' +
        '<span class="brand-logo"><img src="/prototype/assets/img/reborn-logo.png" alt="Reborn" height="34" /></span>' +
        '<nav class="brand-nav" aria-label="Navigazione">' +
          '<span class="on">Home</span><span>Come si usa</span><span>Contatti</span>' +
        '</nav>' +
        '<span id="serverStatus" class="server-status ' + (ready ? 'ready' : error ? 'error' : 'checking') + '" aria-live="polite" hidden>' + escapeHtml(state.serverText) + '</span>' +
      '</header>' +
      (state.previews.length || state.busy ? '' : heroHtml()) +
      '<section class="upload-panel">' +
        '<input id="imageUploadInput" class="file-input" type="file" accept="image/jpeg,image/png,image/webp" multiple />' +
        '<button id="uploadButton" class="upload-button' + busyClass + '" type="button"' + disabled + uploadStyle + ' aria-label="' + escapeHtml(buttonText + (state.busy ? ' ' + String(progress) + '%' : '')) + '">' + escapeHtml(buttonText) + '</button>' +
        '<span class="sr-only" aria-live="polite">' + escapeHtml(state.busy ? state.progressText + ' ' + String(progress) + '%' : '') + '</span>' +
        '<p class="upload-hint">Carica foto nitide del pezzo, possibilmente da più angolazioni e con una misura visibile.</p>' +
      '</section>' +
      resultHtml();

    bindEvents();
    initModelViewers();
  }

  function bindEvents() {
    const uploadButton = document.getElementById('uploadButton');
    const uploadInput = document.getElementById('imageUploadInput');
    if (uploadButton && uploadInput) {
      uploadButton.addEventListener('click', function () {
        uploadInput.click();
      });
      uploadInput.addEventListener('change', function (event) {
        handleFiles(event.target.files);
      });
    }

    document.querySelectorAll('[data-download-model-id]').forEach(function (link) {
      link.addEventListener('click', function () {
        recordModelDownloadInPage(link.getAttribute('data-download-model-id') || '');
      });
    });

    document.querySelectorAll('[data-action]').forEach(function (button) {
      button.addEventListener('click', function () {
        const action = button.getAttribute('data-action') || '';
        if (action === 'save-library') {
          return;
        }
        if (action === 'prefer-model') {
          recordModelPreference(button.getAttribute('data-model-id') || '');
          return;
        }
        if (action === 'search-other-models') {
          state.forceOtherModelsSearch = true;
          state.activeAction = 'identify';
          render();
          return;
        }
        state.activeAction = state.activeAction === action ? '' : action;
        render();
      });
    });

    document.querySelectorAll('[data-criterion-id]').forEach(function (input) {
      input.addEventListener('change', function () {
        const id = input.getAttribute('data-criterion-id') || '';
        state.selectedCriteria = state.selectedCriteria || {};
        state.selectedCriteria[id] = input.checked === true;
        const result = resultFromJob();
        if (result) {
          state.manualQuery = criteriaSearchQuery(result);
        }
        render();
      });
    });

    const composedQueryInput = document.getElementById('composedQueryInput');
    if (composedQueryInput) {
      composedQueryInput.addEventListener('input', function () {
        state.manualQuery = String(composedQueryInput.value || '').trim();
        updateMarketplaceLinkTargets(state.manualQuery);
      });
      composedQueryInput.addEventListener('change', function () {
        state.manualQuery = String(composedQueryInput.value || '').trim();
        render();
      });
    }

    const identificationForm = document.getElementById('identificationForm');
    if (identificationForm) {
      identificationForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const data = new FormData(identificationForm);
        state.manualQuery = String(data.get('manualQuery') || '').trim();
        render();
      });
    }

    const saveLibraryButton = document.querySelector('[data-action="save-library"]');
    const modelFileInput = identificationForm ? identificationForm.querySelector('input[name="modelFile"]') : null;
    if (saveLibraryButton && modelFileInput) {
      const updateModelVerifyState = function () {
        saveLibraryButton.disabled = !(modelFileInput.files && modelFileInput.files.length > 0) || state.libraryContributionBusy === true;
      };
      modelFileInput.addEventListener('change', updateModelVerifyState);
      updateModelVerifyState();
    }
    if (saveLibraryButton && identificationForm) {
      saveLibraryButton.addEventListener('click', async function () {
        const result = resultFromJob() || {};
        const data = new FormData(identificationForm);
        const url = String(data.get('libraryUrl') || '').trim();
        const title = String(state.manualQuery || selectedSearchQuery(result) || resultPartName(result) || '').trim();
        const file = data.get('modelFile');
        const hasFile = file && typeof file === 'object' && file.name;

        if (!hasFile) {
          state.libraryContributionError = 'Carica il file del modello 3D da verificare e associare alla libreria Re-born.';
          render();
          return;
        }

        state.libraryContributionBusy = true;
        state.libraryContributionError = '';
        render();

        try {
          const payload = new FormData();
          payload.append('source', '');
          payload.append('title', title);
          payload.append('url', url);
          payload.append('license', '');
          payload.append('notes', String(data.get('libraryNotes') || '').trim());
          payload.append('query', String(state.manualQuery || selectedSearchQuery(result)).trim());
          payload.append('repair_case_id', state.repairCase && state.repairCase.id ? String(state.repairCase.id) : '');
          payload.append('attachment_ids_json', JSON.stringify(state.attachments.map(function (attachment) { return String(attachment.id || ''); }).filter(Boolean)));
          payload.append('image_fingerprints_json', JSON.stringify(imageFingerprints()));
          payload.append('criteria_json', JSON.stringify(selectedCriteriaValues(result)));
          payload.append('recognition_summary_json', JSON.stringify({
            part: resultPartName(result),
            confidence: confidenceScore(result),
            confidence_label: confidenceLabel(confidenceScore(result)),
            identification: result.identification || {},
            part_spec: result.part_spec || {},
            replacement_part_brief: result.replacement_part_brief || {}
          }));
          payload.append('contribution_type', url ? 'link_and_file' : 'file');
          if (hasFile) {
            payload.append('model_file', file, file.name);
          }

          const response = await window.REBORN_API.saveLibraryContribution(payload);
          state.linkNotes = String(data.get('libraryNotes') || '').trim();
          state.libraryContribution = Object.assign({}, response.contribution || {}, {
            contribution_id: response.contribution_id || (response.contribution && response.contribution.id) || '',
            source: '',
            title: title,
            url: url,
            file_name: hasFile ? file.name : ''
          });
        } catch (error) {
          state.libraryContributionError = 'Non sono riuscito ad avviare la verifica del modello 3D. Puoi comunque inviare una richiesta di identificazione assistita.';
        } finally {
          state.libraryContributionBusy = false;
          render();
        }
      });
    }

    const assistedRequestForm = document.getElementById('assistedRequestForm');
    if (assistedRequestForm) {
      assistedRequestForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const result = resultFromJob();
        const data = new FormData(assistedRequestForm);
        const contact = {
          email: String(data.get('customerEmail') || '').trim(),
          phone: String(data.get('customerPhone') || '').trim(),
          name: String(data.get('customerName') || '').trim(),
          message: String(data.get('customerMessage') || '').trim()
        };
        if (!contact.email || !contact.phone || !contact.name) {
          return;
        }
        state.assistRequest = contact;
        const subject = 'Richiesta identificazione assistita Re-born - ' + resultPartName(result || {});
        const body = assistedBrief(result || {}, contact);
        window.location.href = 'mailto:' + 'stampa3dbolo' + '@' + 'gmail.com' + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
        render();
      });
    }
  }

  async function checkServer() {
    state.server = 'checking';
    state.serverText = 'Server in collegamento';
    render();

    try {
      const health = await window.REBORN_API.health();
      if (health && health.status === 'ok') {
        state.server = 'ready';
        state.serverText = 'Server pronto';
      } else {
        state.server = 'error';
        state.serverText = 'Server non disponibile';
      }
    } catch (error) {
      state.server = 'error';
      state.serverText = 'Server non disponibile';
    }

    render();
  }

  async function ensureDemoSession() {
    await window.REBORN_API.login(demoUser.email, demoUser.password);
  }

  async function ensureRepairCase() {
    if (state.repairCase && state.repairCase.id) {
      return state.repairCase;
    }

    const created = await window.REBORN_API.createRepairCase();
    state.repairCase = created.repair_case;
    return state.repairCase;
  }

  function userMessageForError(error) {
    const status = error && error.status ? Number(error.status) : 0;
    const raw = String(error && error.message ? error.message : '').toLowerCase();

    if (status === 413 || raw.indexOf('too large') >= 0) {
      return 'Il file è troppo grande. Usa immagini sotto i 15 MB.';
    }
    if (status === 415 || raw.indexOf('unsupported') >= 0 || raw.indexOf('mime') >= 0) {
      return 'Formato non supportato. Usa JPG, PNG o WEBP.';
    }
    if (status === 422) {
      return 'Una delle immagini non è valida. Controlla formato e dimensione, poi riprova.';
    }
    if (raw.indexOf('abort') >= 0 || raw.indexOf('timeout') >= 0) {
      return 'L’analisi sta impiegando troppo tempo. Riprova con meno immagini o immagini più leggere.';
    }
    return 'L’analisi non è al momento disponibile. Riprova più tardi o invia una richiesta di identificazione assistita.';
  }

  async function handleFiles(fileList) {
    state.activeAction = '';
    state.manualQuery = '';
    state.selectedCriteria = null;
    state.selectedLinks = null;
    state.linkNotes = '';
    state.libraryContribution = null;
    state.libraryContributionError = '';
    state.libraryContributionBusy = false;
    state.libraryMatches = [];
    state.libraryMatchesBusy = false;
    state.forceOtherModelsSearch = false;
    state.assistRequest = null;
    state.recognitionJob = null;

    if (!rememberFiles(fileList)) {
      setProgress(0, state.error || 'Seleziona almeno una immagine valida.');
      return;
    }

    state.error = '';
    state.busy = true;
    setProgress(5, 'Caricamento immagini');

    try {
      await ensureDemoSession();
      setProgress(18, 'Sessione pronta.');

      const repairCase = await ensureRepairCase();
      setProgress(28, 'Richiesta creata. Caricamento immagini.');

      state.attachments = [];
      for (let index = 0; index < state.files.length; index += 1) {
        const uploaded = await window.REBORN_API.uploadRepairAttachment(repairCase.id, state.files[index]);
        if (uploaded && uploaded.attachment) {
          state.attachments.push(uploaded.attachment);
        }
        const percent = 35 + Math.round(((index + 1) / state.files.length) * 30);
        setProgress(percent, 'Caricamento immagine ' + String(index + 1) + ' di ' + String(state.files.length) + '.');
      }

      const attachmentIds = state.attachments.map(function (attachment) {
        return String(attachment.id || '');
      }).filter(Boolean);

      if (!attachmentIds.length) {
        throw new Error('Nessuna immagine ricevuta dal backend.');
      }

      setProgress(78, 'Analisi in corso');
      const response = await window.REBORN_API.requestRecognition(repairCase.id, attachmentIds);
      state.recognitionJob = response.recognition_job || null;
      const recognized = resultFromJob();
      state.libraryMatches = (recognized && Array.isArray(recognized.library_matches)) ? recognized.library_matches : [];
      if (recognized && String(recognized.source || '') === 'reborn_library_cache') {
        setProgress(100, state.libraryMatches.length ? 'Risultato e modello trovati in libreria.' : 'Risultato già presente in libreria.');
      } else {
        setProgress(100, 'Analisi completata.');
      }
      if (recognized && recognitionStatus(recognized) === 'recognized' && !state.libraryMatches.length) {
        await refreshLibraryMatches(recognized);
      }
    } catch (error) {
      state.error = userMessageForError(error);
      setProgress(100, 'Analisi non completata.');
    } finally {
      state.busy = false;
      render();
    }
  }

  render();
  checkServer();
})();
