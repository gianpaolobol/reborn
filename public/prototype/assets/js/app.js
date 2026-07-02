(function () {
  'use strict';

  const app = document.getElementById('app');
  const demoUser = { email: 'repair.user@reborn.local', password: 'password' };

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
    error: ''
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

  function rememberFiles(fileList) {
    clearPreviews();
    state.files = Array.from(fileList || []).filter(function (file) {
      return file && String(file.type || '').indexOf('image/') === 0;
    });

    state.previews = state.files.map(function (file, index) {
      return {
        id: String(Date.now()) + '-' + String(index),
        name: file.name || ('immagine-' + String(index + 1) + '.jpg'),
        url: URL.createObjectURL(file)
      };
    });
  }

  function valueFromArray(values, fallback) {
    if (!Array.isArray(values)) {
      return fallback || 'Non letto';
    }
    const clean = values.map(function (item) { return String(item || '').trim(); }).filter(Boolean);
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

  function field(label, value) {
    return '<p><strong>' + escapeHtml(label) + '</strong><span>' + escapeHtml(value || 'Non disponibile') + '</span></p>';
  }

  function nextStepsHtml() {
    const steps = [
      'Verificare se esiste gia un file su Thingiverse, MakerWorld o Printables relativo al pezzo riconosciuto.',
      'Se il file non e reperibile, generare il modello 3D con Meshy dalle immagini caricate.',
      'Preparare un preventivo di stampa tramite slicer configurato.',
      'Proporre scaricamento file a pagamento oppure lista di stampatori locali disponibili.'
    ];
    return '<div class="next-flow"><strong>Passi Successivi</strong><ol>' + steps.map(function (item) {
      return '<li>' + escapeHtml(item) + '</li>';
    }).join('') + '</ol></div>';
  }



  function marketplaceStatusLabel(status) {
    const value = String(status || '');
    if (value === 'possible_file_found') {
      return 'Possibile file trovato';
    }
    if (value === 'possible_model_page_found') {
      return 'Possibile pagina modello trovata';
    }
    if (value === 'search_links_ready') {
      return 'Link di ricerca pronti';
    }
    if (value === 'skipped_needs_better_recognition' || value === 'skipped_no_search_terms') {
      return 'Servono dati piu precisi per cercare il file';
    }
    if (value === 'search_error') {
      return 'Ricerca file non disponibile';
    }
    return 'Ricerca file STL';
  }

  function marketplaceSearchHtml(search) {
    if (!search || search.enabled === false) {
      return '';
    }

    const status = String(search.status || '');
    if (status.indexOf('skipped_') === 0) {
      return '<div class="marketplace-panel muted"><strong>' + escapeHtml(marketplaceStatusLabel(status)) + '</strong><p>' + escapeHtml(search.message || 'Carica altre foto o un codice visibile prima della ricerca sui marketplace.') + '</p></div>';
    }

    if (status === 'search_error') {
      return '<div class="marketplace-panel muted"><strong>Ricerca file non disponibile</strong><p>Il flusso puo proseguire con generazione Meshy se non trovi un modello manualmente.</p></div>';
    }

    const candidates = Array.isArray(search.candidates) ? search.candidates.slice(0, 5) : [];
    if (!candidates.length) {
      return '<div class="marketplace-panel muted"><strong>Nessun link pronto</strong><p>Se il file non e reperibile, il prossimo step e la generazione del modello 3D.</p></div>';
    }

    const items = candidates.map(function (candidate) {
      const title = candidate.title || 'Risultato modello 3D';
      const source = candidate.source || 'web';
      const score = candidate.score ? ' - ' + String(candidate.score) + '/100' : '';
      const reason = candidate.match_reason || 'Link generato dai riferimenti del riconoscimento AI.';
      const badge = candidate.download_available ? '<span class="candidate-badge">file</span>' : '<span class="candidate-badge muted">ricerca</span>';
      return '<li><a href="' + escapeHtml(candidate.url || '#') + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(title) + '</a>' + badge + '<small>' + escapeHtml(source + score) + '</small><em>' + escapeHtml(reason) + '</em></li>';
    }).join('');

    return '<div class="marketplace-panel"><strong>' + escapeHtml(marketplaceStatusLabel(status)) + '</strong><p>Verifica questi candidati prima di passare a Meshy.</p><ul>' + items + '</ul></div>';
  }

  function resultHtml() {
    const result = resultFromJob();
    const job = state.recognitionJob || {};

    if (state.error) {
      return '<section id="recognitionResult" class="result-panel error" aria-live="polite">' +
        '<h2>Riconoscimento non effettuato</h2>' +
        '<p>Il server risponde, ma il provider AI non ha restituito un risultato.</p>' +
        '<p>Controllare .env, API key e modello configurato.</p>' +
        '</section>';
    }

    if (!result && job.status === 'failed') {
      return '<section id="recognitionResult" class="result-panel error" aria-live="polite">' +
        '<h2>Riconoscimento non effettuato</h2>' +
        '<p>Il server risponde, ma il provider AI non ha restituito un risultato.</p>' +
        '<p>Controllare .env, API key e modello configurato.</p>' +
        '</section>';
    }

    if (!result) {
      return '<section id="recognitionResult" class="result-panel empty" aria-live="polite">' +
        '<h2>Risposta del riconoscimento immagini</h2>' +
        '<p>Carica immagini per avviare il riconoscimento.</p>' +
        '</section>';
    }

    if (isProviderFailure(result)) {
      return '<section id="recognitionResult" class="result-panel error" aria-live="polite">' +
        '<h2>Riconoscimento non effettuato</h2>' +
        '<p>Il server risponde, ma il provider AI non ha restituito un risultato.</p>' +
        '<p>Controllare .env, API key e modello configurato.</p>' +
        '</section>';
    }

    const status = recognitionStatus(result);
    if (status !== 'recognized') {
      return '<section id="recognitionResult" class="result-panel needs-more" aria-live="polite">' +
        '<h2>Servono altre immagini</h2>' +
        '<p>Carica una foto laterale, del pezzo montato o con una misura visibile.</p>' +
        nextStepsHtml() +
        '</section>';
    }

    const identification = result.identification || {};
    const partSpec = result.part_spec || {};
    const brief = result.replacement_part_brief || {};
    const objectGuess = result.object_guess || {};
    const recommended = result.recommended_next_step || {};

    const part = partSpec.name_it || identification.commercial_name || objectGuess.label || 'Pezzo da confermare';
    const fn = brief.probable_function || partSpec.appliance_context || objectGuess.object_context || 'Funzione da confermare';
    const code = identification.part_number || valueFromArray(identification.visible_text, 'Non letto');
    const measures = valueFromArray(partSpec.known_dimensions || brief.critical_dimensions, 'Non lette');
    const nextReason = recommended.reason || 'trovare la soluzione migliore per sostituire il pezzo rotto.';
    const next = 'Passa allo step successivo per ' + nextReason;

    return '<section id="recognitionResult" class="result-panel recognized" aria-live="polite">' +
      '<h2>Immagine riconosciuta</h2>' +
      '<div class="result-fields">' +
      field('Pezzo:', part) +
      field('Funzione:', fn) +
      field('Codice letto:', code) +
      field('Misure lette:', measures) +
      field('Prossimo passo:', next) +
      '</div>' +
      marketplaceSearchHtml(result.marketplace_search) +
      nextStepsHtml() +
      '</section>';
  }

  function previewsHtml() {
    if (!state.previews.length) {
      return '<section class="preview-panel"><strong>Preview piccole immagini caricate</strong><div id="previewGrid" class="preview-grid empty"><p>Nessuna immagine caricata.</p></div></section>';
    }

    const cards = state.previews.map(function (item) {
      return '<figure class="preview-card"><img src="' + escapeHtml(item.url) + '" alt="Anteprima immagine caricata" /><figcaption>' + escapeHtml(item.name) + '</figcaption></figure>';
    }).join('');

    return '<section class="preview-panel"><strong>Preview piccole immagini caricate</strong><div id="previewGrid" class="preview-grid">' + cards + '</div></section>';
  }

  function render() {
    const ready = state.server === 'ready';
    const error = state.server === 'error';
    const buttonText = state.busy ? 'Analisi in corso' : (state.previews.length ? 'Carica immagini' : 'Carica foto e riconosci');
    const disabled = !ready || state.busy ? ' disabled' : '';
    const progress = clampPercent(state.progress);

    app.innerHTML =
      '<section id="serverStatus" class="server-status ' + (ready ? 'ready' : error ? 'error' : 'checking') + '" aria-live="polite">' + escapeHtml(state.serverText) + '</section>' +
      '<section class="upload-panel">' +
        '<input id="imageUploadInput" class="file-input" type="file" accept="image/*" multiple />' +
        '<button id="uploadButton" class="upload-button" type="button"' + disabled + '>' + escapeHtml(buttonText) + '</button>' +
      '</section>' +
      '<section class="progress-panel" aria-label="Barra di caricamento">' +
        '<div class="progress-meta"><strong>Caricamento immagini</strong><span>' + String(progress) + '%</span></div>' +
        '<div id="uploadProgress" class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + String(progress) + '"><span style="width: ' + String(progress) + '%"></span></div>' +
        '<p>' + escapeHtml(state.progressText) + '</p>' +
      '</section>' +
      previewsHtml() +
      resultHtml();

    document.getElementById('uploadButton').addEventListener('click', function () {
      const input = document.getElementById('imageUploadInput');
      if (input) {
        input.click();
      }
    });

    document.getElementById('imageUploadInput').addEventListener('change', function (event) {
      handleFiles(event.target.files);
    });
  }

  async function checkServer() {
    state.server = 'checking';
    state.serverText = 'Server in collegamento';
    render();

    try {
      const health = await window.REBORN_API.health();
      if (health && health.status === 'ok') {
        state.server = 'ready';
        state.serverText = 'Server Pronto';
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

  async function handleFiles(fileList) {
    rememberFiles(fileList);
    state.error = '';
    state.recognitionJob = null;

    if (!state.files.length) {
      setProgress(0, 'Seleziona almeno una immagine.');
      return;
    }

    state.busy = true;
    setProgress(5, 'Caricamento immagini');

    try {
      await ensureDemoSession();
      setProgress(18, 'Sessione demo pronta.');

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
      setProgress(100, 'Analisi completata.');
    } catch (error) {
      state.error = error && error.message ? error.message : 'Riconoscimento non effettuato';
      setProgress(100, 'Riconoscimento non effettuato.');
    } finally {
      state.busy = false;
      render();
    }
  }

  render();
  checkServer();
})();
