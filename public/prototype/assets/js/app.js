const app = document.getElementById('app');
const nav = document.querySelector('.topnav');
const menuButton = document.getElementById('menuButton');

const D = window.REBORN_DATA;
const S = window.REBORN_STATE;

function html(strings, ...values) {
  return strings.map((s, i) => s + (values[i] ?? '')).join('');
}

function safe(value) {
  return String(value ?? '').replace(/[&<>'"]/g, c => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;'
  }[c]));
}

function setActiveNav(route) {
  document.querySelectorAll('[data-nav]').forEach(link => {
    link.classList.toggle('is-active', link.dataset.nav === route);
  });
}

function toast(message) {
  const old = document.querySelector('.toast');
  if (old) old.remove();
  const node = document.createElement('div');
  node.className = 'toast';
  node.textContent = message;
  document.body.appendChild(node);
  setTimeout(() => node.remove(), 2600);
}

function setBusy(value) {
  S.busy = value;
  document.body.classList.toggle('is-busy', value);
}

function formatEuro(cents) {
  if (typeof cents !== 'number' || Number.isNaN(cents)) return 'Quote after validation';
  return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

function apiBanner() {
  const api = S.api;
  const statusClass = api.status === 'live' ? 'live' : api.status === 'mock' ? 'mock' : api.status === 'error' ? 'error' : 'checking';
  const label = api.status === 'live' ? 'Live API' : api.status === 'mock' ? 'Mock mode' : api.status === 'error' ? 'API error' : 'Checking API';
  const sync = api.lastSyncAt ? `Last sync ${new Date(api.lastSyncAt).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}` : 'No sync yet';
  return html`<div class="api-banner ${statusClass}" role="status">
    <div><strong>${label}</strong><span>${safe(api.message)}</span></div>
    <div class="api-banner-actions">
      <span>${sync}</span>
      <button class="mini-button" type="button" onclick="refreshApiData()" ${S.busy ? 'disabled' : ''}>Refresh API</button>
    </div>
  </div>`;
}

function layout(_title, body, opts = {}) {
  const current = opts.currentStep ?? null;
  return html`
    ${apiBanner()}
    ${current ? stepper(current) : ''}
    ${body}
  `;
}

function stepper(active) {
  const steps = [
    ['start', '01', 'Describe'],
    ['capture', '02', 'Capture'],
    ['diagnosis', '03', 'Diagnose'],
    ['repair-paths', '04', 'Decide'],
    ['checkout', '05', 'Repair']
  ];
  const activeIndex = steps.findIndex(s => s[0] === active);
  return html`<div class="stepper" aria-label="Repair journey progress">
    ${steps.map((s, i) => `<div class="step ${i < activeIndex ? 'done' : ''} ${s[0] === active ? 'active' : ''}"><strong>${s[1]}</strong>${s[2]}</div>`).join('')}
  </div>`;
}

function metric(value, label) {
  return `<div class="metric"><strong>${safe(value)}</strong><span>${safe(label)}</span></div>`;
}

function badges(items) {
  return `<div class="badges">${items.map(([t, c]) => `<span class="badge ${c || ''}">${safe(t)}</span>`).join('')}</div>`;
}

function getActiveProduct() {
  const repairCase = S.api.repairCase;
  if (!repairCase) return D.product;

  return {
    detectedName: repairCase.recognized_product
      ? `${repairCase.recognized_product} — ${repairCase.recognized_component || 'repair component'}`
      : repairCase.title,
    confidence: Number(repairCase.confidence_score || 0),
    repairDna: `RB-CASE-${repairCase.id.slice(0, 8).toUpperCase()}`,
    category: repairCase.category || 'unknown',
    status: repairCase.status || 'intake_received',
    risk: repairCase.confidence_score >= 0.7 ? 'Low' : 'Needs validation',
    material: 'Provider validation required',
    dimensions: 'Captured in intake / planned image pipeline',
    estimatedLife: 'Pending field validation',
    description: repairCase.description
  };
}

function getActiveRepairPaths() {
  if (!S.api.repairPaths.length) return D.repairPaths;

  return S.api.repairPaths.map(path => ({
    id: path.type || path.id,
    title: path.title,
    score: Math.round(Number(path.confidence_score || 0) * 100),
    cost: formatEuro(Number(path.estimated_price_cents)),
    eta: `${path.estimated_days || '?'} days`,
    impact: path.type === 'existing_part' ? 'Existing spare search' : path.type === 'ai_generated_cad' ? 'Learning fallback' : 'Local fulfilment',
    recommendation: path.description
  }));
}

function getActiveProviders() {
  if (!S.api.providers.length) return D.providers;

  return S.api.providers.map(provider => {
    const capabilities = Array.isArray(provider.capabilities) ? provider.capabilities : [];
    return {
      name: provider.name,
      type: capabilities[0] || 'Repair provider',
      distance: `${provider.city || 'Local'}, ${provider.country || ''}`,
      rating: Number(provider.rating || 0).toFixed(1),
      jobs: 'API',
      price: 'Quote after validation',
      eta: `${provider.average_lead_time_days || '?'} days`,
      trust: Math.round((Number(provider.rating || 0) / 5) * 100),
      material: capabilities.slice(0, 3).join(' / ') || 'Mixed capabilities'
    };
  });
}

function getKnowledgeMetrics() {
  const nodes = S.api.knowledgeNodes;
  if (!nodes.length) return { count: 14, verified: 1, providers: getActiveProviders().length, risk: 'Low' };

  const avg = nodes.reduce((sum, node) => sum + Number(node.confidence_score || 0), 0) / nodes.length;
  return {
    count: nodes.length,
    verified: nodes.filter(node => Number(node.confidence_score || 0) >= 0.78).length,
    providers: getActiveProviders().length,
    risk: avg >= 0.75 ? 'Low' : 'Review'
  };
}

function apiSnapshot() {
  const api = S.api;
  return html`<div class="api-snapshot">
    <h3>API integration state</h3>
    <div class="grid four">
      ${metric(api.repairCases.length, 'Repair cases')}
      ${metric(api.repairPaths.length || D.repairPaths.length, 'Repair paths')}
      ${metric(api.providers.length || D.providers.length, 'Providers')}
      ${metric(api.knowledgeNodes.length, 'Graph nodes')}
    </div>
    <p class="muted small">When served through <code>php -S 127.0.0.1:8080 -t public public/index.php</code>, this prototype calls the PHP API. When opened as a file, it falls back to local mock data.</p>
  </div>`;
}

function home() {
  setActiveNav('home');
  const p = getActiveProduct();
  const km = getKnowledgeMetrics();
  return layout('Home', html`
    <section class="hero">
      <div class="hero-panel">
        <p class="eyebrow">Allow anyone to repair anything</p>
        <h1>Repair Intelligence Platform</h1>
        <p class="lead">Re-born does not ask users to search for STL files. It guides them from a broken object to the best repair path: existing spare, verified model, AI-generated component or local production.</p>
        <div class="actions">
          <a class="btn green" href="#/start">Start a repair</a>
          <button class="btn secondary" onclick="createDemoRepairCase()" ${S.busy ? 'disabled' : ''}>Create live API case</button>
        </div>
      </div>
      <div class="panel dark-panel stack">
        <div class="section-head"><div><p class="eyebrow">MVP integration</p><h2>Prototype connected to backend.</h2></div></div>
        <div class="grid two">
          ${metric(`${Math.round((p.confidence || 0) * 100)}%`, 'Recognition confidence')}
          ${metric(getActiveRepairPaths().length, 'Repair paths found')}
          ${metric(getActiveProviders()[0]?.eta || '24h', 'Fastest local production')}
          ${metric(km.count, 'Knowledge nodes')}
        </div>
        <div class="timeline">
          ${D.events.map(e => `<div class="timeline-row"><div class="timeline-time">${safe(e[0])}</div><div class="timeline-content"><strong>${safe(e[1])}</strong><p class="muted small">${safe(e[2])}</p></div></div>`).join('')}
        </div>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <div><p class="eyebrow">Core promise</p><h2>The user wants the object to work again.</h2></div>
        <p class="muted">Every product decision must reduce uncertainty, increase trust, and make the repair feel possible.</p>
      </div>
      <div class="grid four">
        <article class="card"><h3>Recognize</h3><p class="muted">Identify product, component, damage and constraints from photos, text or files.</p></article>
        <article class="card"><h3>Decide</h3><p class="muted">Rank repair options by feasibility, cost, risk, ETA and sustainability.</p></article>
        <article class="card"><h3>Produce</h3><p class="muted">Connect to providers, makers, CAD creators and local materials.</p></article>
        <article class="card"><h3>Learn</h3><p class="muted">Every repair enriches Repair DNA and the Knowledge Graph.</p></article>
      </div>
    </section>
    <section class="section panel">${apiSnapshot()}</section>
  `);
}

function start() {
  setActiveNav('start');
  return layout('Start repair', html`
    <section class="hero">
      <form class="panel stack" onsubmit="submitIntakeFromPrototype(event)">
        <p class="eyebrow">Repair intake</p>
        <h2>Tell Re-born what stopped working.</h2>
        <p class="muted">This is not an STL upload form. It is a repair request that can create a live <code>repair_case</code> through the PHP API.</p>
        <div class="form-grid">
          <div class="field"><label for="repairTitle">Object type</label><input id="repairTitle" name="title" value="Dishwasher basket wheel" /></div>
          <div class="field"><label for="repairBrand">Brand / model if known</label><input id="repairBrand" name="brand" value="Bosch Series 4" /></div>
          <div class="field"><label for="repairCategory">Category</label><select id="repairCategory" name="category"><option value="home_appliance">Home appliance</option><option value="wearable">Wearable</option><option value="eyewear">Eyewear</option><option value="furniture">Furniture</option></select></div>
          <div class="field"><label for="repairUrgency">Repair urgency</label><select id="repairUrgency" name="urgency"><option>Fast, within 48 hours</option><option>Lowest cost</option><option>Best quality</option><option>Lowest environmental impact</option></select></div>
        </div>
        <div class="field"><label for="repairDescription">Description</label><textarea id="repairDescription" name="description">The lower basket wheel is broken. The dishwasher still works but the basket no longer slides correctly.</textarea></div>
        <div class="actions"><button class="btn green" type="submit" ${S.busy ? 'disabled' : ''}>Create live repair case</button><a class="btn secondary" href="#/capture">Continue with mock flow</a></div>
      </form>
      <aside class="panel dark-panel stack">
        <h3>What Re-born will do next</h3>
        <p class="muted">Recognition Engine will identify the object, Knowledge Engine will search existing repair DNA, Decision Engine will rank options, Learning Engine will update the graph after completion.</p>
        ${badges([['POST /api/v1/repair-cases', 'green'], ['Domain event', 'blue'], ['Repair DNA draft', 'orange']])}
      </aside>
    </section>
  `, { currentStep: 'start' });
}

function capture() {
  return layout('Capture', html`
    <section class="grid two">
      <div class="panel stack">
        <p class="eyebrow">Photos and dimensions</p>
        <h2>Capture the part, not the file.</h2>
        <div class="dropzone" onclick="REBORN_STATE.set('uploaded', true); toast('Prototype upload accepted. No file was actually uploaded.'); render();">
          <div><div class="dropzone-icon">▣</div><h3>${S.uploaded ? 'Photos staged for diagnosis' : 'Drop photos here'}</h3><p class="muted">Front, side, broken area, scale reference, optional STEP/STL.</p></div>
        </div>
        <div class="form-grid"><div class="field"><label>Approx. width</label><input value="36 mm" /></div><div class="field"><label>Approx. thickness</label><input value="12 mm" /></div></div>
        <div class="actions"><button class="btn green" onclick="runLiveDiagnosis()" ${S.busy ? 'disabled' : ''}>Run live diagnosis</button><a class="btn secondary" href="#/start">Back</a></div>
      </div>
      <div class="prototype-frame"><div class="device-header"><div class="dots"><span></span><span></span><span></span></div><strong>AI capture preview</strong></div><div class="frame-body"><div class="scan-visual"><div class="part-shape"></div><div class="scan-box"></div><div class="scan-line"></div></div></div></div>
    </section>
  `, { currentStep: 'capture' });
}

function diagnosis() {
  const p = getActiveProduct();
  const km = getKnowledgeMetrics();
  return layout('Diagnosis', html`
    <section class="grid two">
      <div class="panel stack">
        <p class="eyebrow">Recognition result</p>
        <h2>${safe(p.detectedName)}</h2>
        <p class="lead">${S.api.repairCase ? 'This result comes from the PHP backend Recognition Engine mock plus Knowledge Graph.' : 'This is local prototype data. Create a live case to exercise the backend API.'}</p>
        ${badges([[`${Math.round((p.confidence || 0) * 100)}% confidence`, 'green'], [p.status, 'blue'], [`Risk: ${p.risk}`, 'orange'], [p.repairDna, '']])}
        <table class="table"><tr><th>Category</th><td>${safe(p.category)}</td></tr><tr><th>Material suggestion</th><td>${safe(p.material)}</td></tr><tr><th>Estimated dimensions</th><td>${safe(p.dimensions)}</td></tr><tr><th>Expected life</th><td>${safe(p.estimatedLife)}</td></tr></table>
        <div class="actions"><button class="btn green" onclick="runLiveDiagnosis()" ${S.busy ? 'disabled' : ''}>${S.api.repairCase ? 'Re-run diagnosis' : 'Create and diagnose live case'}</button><a class="btn secondary" href="#/repair-paths">See repair paths</a></div>
      </div>
      <aside class="panel stack">
        <h3>Knowledge Graph match</h3>
        <p class="muted">Matched with prior repair nodes when the backend is live. The graph suggests material and provider constraints before purchase.</p>
        <div class="grid two">${metric(km.count, 'Graph nodes')}${metric(km.verified, 'High-confidence nodes')}${metric(km.providers, 'Provider records')}${metric(km.risk, 'Safety risk')}</div>
      </aside>
    </section>
  `, { currentStep: 'diagnosis' });
}

function repairPaths() {
  const paths = getActiveRepairPaths();
  return layout('Repair paths', html`
    <section class="section-head"><div><p class="eyebrow">Decision Engine</p><h2>Choose the best way to make it work again.</h2></div><p class="muted">Re-born ranks options by feasibility, price, ETA, trust and learning value.</p></section>
    <section class="grid three">
      ${paths.map(path => `<article class="card interactive ${S.selectedPath === path.id ? 'selected' : ''}" onclick="REBORN_STATE.set('selectedPath', '${safe(path.id)}'); toast('${safe(path.title)} selected.'); render();"><div class="section-head"><h3>${safe(path.title)}</h3><span class="badge ${path.id === 'print' || path.id === 'provider_assisted_repair' ? 'green' : path.id === 'ai' || path.id === 'ai_generated_cad' ? 'orange' : 'blue'}">Score ${safe(path.score)}</span></div><p class="muted">${safe(path.recommendation)}</p><table class="table"><tr><th>Cost</th><td>${safe(path.cost)}</td></tr><tr><th>ETA</th><td>${safe(path.eta)}</td></tr><tr><th>Impact</th><td>${safe(path.impact)}</td></tr></table></article>`).join('')}
    </section>
    <section class="section panel stack"><h3>Recommended plan</h3><p class="muted">For the MVP journey, local production with a verified model creates marketplace liquidity, validates provider fulfilment and updates the Knowledge Graph after completion.</p><div class="actions"><a class="btn green" href="#/part-detail">Continue with repair model</a><a class="btn secondary" href="#/ai-generation">Generate with AI instead</a></div></section>
  `, { currentStep: 'repair-paths' });
}

function partDetail() {
  return layout('Part detail', html`
    <section class="grid two">
      <div class="panel stack"><p class="eyebrow">Verified repair model</p><h2>Repair component candidate</h2><p class="lead">A model may be recovered from the Knowledge Graph or generated as fallback. Re-born routes it to a provider with material and tolerance constraints.</p>${badges([['Verified model candidate', 'green'], ['Royalty enabled', 'blue'], ['Commercial use review', ''], ['Repair DNA linked', 'orange']])}<table class="table"><tr><th>Recommended material</th><td>PETG-CF for home FDM, PA12 for professional SLS</td></tr><tr><th>Print constraints</th><td>0.2 mm layer, 4 walls, validation before checkout</td></tr><tr><th>Maker royalty</th><td>Calculated on fulfilled repair</td></tr><tr><th>Validation status</th><td>Backend proof-of-concept, human QA required</td></tr></table><div class="actions"><a class="btn green" href="#/provider-network">Find local production</a><a class="btn secondary" href="#/repair-paths">Back</a></div></div>
      <div class="prototype-frame"><div class="device-header"><div class="dots"><span></span><span></span><span></span></div><strong>CAD preview placeholder</strong></div><div class="frame-body"><div class="scan-visual"><div class="part-shape"></div></div></div></div>
    </section>
  `, { currentStep: 'repair-paths' });
}

function providerNetwork() {
  setActiveNav('provider-network');
  const providers = getActiveProviders();
  return layout('Providers', html`
    <section class="section-head"><div><p class="eyebrow">Distributed manufacturing</p><h2>Local providers ranked by trust and fit.</h2></div><p class="muted">Professional services and independent makers can both compete when quality, constraints and trust are explicit.</p></section>
    <section class="stack">
      ${providers.map(p => `<article class="card provider-card interactive ${S.selectedProvider === p.name ? 'selected' : ''}" onclick="REBORN_STATE.set('selectedProvider', '${safe(p.name)}'); toast('${safe(p.name)} selected.'); render();"><div class="stack"><div><h3>${safe(p.name)}</h3><p class="muted">${safe(p.type)} · ${safe(p.distance)} · ${safe(p.jobs)} completed jobs</p></div>${badges([[`Rating ${p.rating}`, 'green'], [`Trust ${p.trust}`, 'blue'], [p.material, 'orange'], [p.eta, '']])}</div><div><div class="price">${safe(p.price)}</div><p class="muted small">estimated total</p></div></article>`).join('')}
    </section>
    <section class="section panel stack"><h3>Provider agreement preview</h3><p class="muted">Re-born collects a platform fee on every fulfilled repair. Provider receives clear model constraints, quality checks and delivery expectations before accepting.</p><div class="actions"><a class="btn green" href="#/checkout">Continue to repair order</a><a class="btn secondary" href="#/provider">Open provider view</a></div></section>
  `, { currentStep: 'repair-paths' });
}

function checkout() {
  const p = getActiveProduct();
  return layout('Checkout', html`
    <section class="grid two">
      <div class="panel stack"><p class="eyebrow">Repair order</p><h2>Confirm the repair, not just the purchase.</h2><table class="table"><tr><th>Object</th><td>${safe(p.detectedName)}</td></tr><tr><th>Path</th><td>${safe(S.selectedPath)}</td></tr><tr><th>Provider</th><td>${safe(S.selectedProvider)}</td></tr><tr><th>Material</th><td>PETG-CF / PA12 equivalent</td></tr><tr><th>Platform fee</th><td>Included</td></tr><tr><th>Maker royalty</th><td>Included</td></tr></table><div class="actions"><button class="btn green" onclick="toast('Prototype order created. Production will create RepairOrder + Wallet events.')">Confirm repair order</button><a class="btn secondary" href="#/provider-network">Back</a></div></div>
      <aside class="panel dark-panel stack"><h3>Wallet and impact</h3><div class="grid two">${metric(D.wallet.credits, 'Repair Credits')}${metric(D.wallet.savedObjects, 'Objects saved')}${metric(D.wallet.co2, 'CO₂ avoided')}${metric('€2.10', 'Platform fee')}</div><p class="muted">After completion, the repair updates provider trust, maker royalty, model reliability and Knowledge Graph confidence.</p></aside>
    </section>
  `, { currentStep: 'checkout' });
}

function aiGeneration() {
  return layout('AI generation', html`
    <section class="grid two"><div class="panel stack"><p class="eyebrow">AI repair model</p><h2>Generate only when repair knowledge is missing.</h2><p class="muted">AI generation is positioned as a repair fallback, not as the product. Generated models need validation before becoming verified graph assets.</p><div class="timeline"><div class="timeline-row"><div class="timeline-time">Step 1</div><div class="timeline-content"><strong>Geometry proposal</strong><p class="muted small">AI estimates shape from photos, dimensions and similar parts.</p></div></div><div class="timeline-row"><div class="timeline-time">Step 2</div><div class="timeline-content"><strong>Constraint check</strong><p class="muted small">Wall thickness, tolerance, material and mechanical risk.</p></div></div><div class="timeline-row"><div class="timeline-time">Step 3</div><div class="timeline-content"><strong>Provider validation</strong><p class="muted small">Provider flags printability before order confirmation.</p></div></div></div><div class="actions"><a class="btn orange" href="#/provider-network">Validate with provider</a><a class="btn secondary" href="#/repair-paths">Back to safer paths</a></div></div><aside class="panel stack"><h3>AI Premium trigger</h3><p class="muted">This flow consumes Repair Credits and creates potential marketplace assets if the model becomes verified.</p>${badges([['3 credits', 'orange'], ['Validation required', 'danger'], ['Learning event', 'blue']])}</aside></section>
  `);
}

function account() {
  setActiveNav('account');
  return layout('Account', html`
    <section class="section-head"><div><p class="eyebrow">User dashboard</p><h2>Your repaired objects become intelligence.</h2></div><p class="muted">The dashboard turns repair history into trust, credits and sustainability impact.</p></section><section class="grid four">${metric(D.wallet.savedObjects, 'Objects saved')}${metric(D.wallet.credits, 'Available credits')}${metric(D.wallet.pendingRoyalties, 'Pending royalties')}${metric(D.wallet.co2, 'CO₂ avoided')}</section><section class="section grid two"><div class="panel stack"><h3>Active repair</h3><p class="status-line"><span class="status-dot"></span>${safe(getActiveProduct().detectedName)}</p><p class="muted">${S.api.repairCase ? 'Live API repair case active.' : 'Mock repair case active.'}</p><div class="actions"><a class="btn green" href="#/checkout">Open order</a></div></div><div class="panel stack"><h3>Knowledge contributions</h3><p class="muted">${S.api.knowledgeNodes.length || 2} graph records available in the current prototype state.</p>${badges([['Graph contributor', 'blue'], ['Repair feedback', 'green']])}</div></section>
  `);
}

function provider() {
  return layout('Provider view', html`
    <section class="section-head"><div><p class="eyebrow">Provider PRO</p><h2>Accept repairs with clear constraints.</h2></div><p class="muted">Providers should not receive vague STL jobs. They receive repair intent, constraints, quality checks and expected outcome.</p></section><section class="grid two"><div class="panel stack"><h3>Incoming repair order</h3><table class="table"><tr><th>Part</th><td>${safe(getActiveProduct().detectedName)}</td></tr><tr><th>Material</th><td>PETG-CF or PA12</td></tr><tr><th>Deadline</th><td>Based on selected provider SLA</td></tr><tr><th>Quality check</th><td>Dimensional photo + fit confirmation</td></tr></table><div class="actions"><button class="btn green" onclick="toast('Provider accepted the job in prototype state.')">Accept job</button><button class="btn secondary" onclick="toast('Provider requested clarification.')">Ask question</button></div></div><div class="panel stack"><h3>Provider score factors</h3>${badges([['On-time delivery', 'green'], ['Material compliance', 'blue'], ['Low return rate', 'green'], ['Local availability', 'orange']])}<p class="muted">Trust Engine will rank providers by repair success, not only by star reviews.</p></div></section>
  `);
}

function maker() {
  setActiveNav('maker');
  return layout('Maker view', html`
    <section class="hero"><div class="panel stack"><p class="eyebrow">Maker CAD marketplace</p><h2>Upload models that repair real objects.</h2><p class="lead">Maker value is not measured only by downloads. It is measured by successful repairs, low returns and verified compatibility.</p><div class="form-grid"><div class="field"><label>Model name</label><input value="Bosch lower basket wheel replacement" /></div><div class="field"><label>License</label><select><option>Repair commercial license with royalty</option><option>Free community model</option><option>Enterprise restricted</option></select></div></div><div class="field"><label>Compatibility notes</label><textarea>Compatible with Bosch Series 4 lower basket. Avoid PLA. Validate axle diameter before production.</textarea></div><div class="actions"><button class="btn green" onclick="toast('Model submitted for verification in prototype state.')">Submit for verification</button></div></div><aside class="panel dark-panel stack"><h3>Royalty logic</h3><p class="muted">Royalty is triggered by fulfilled repairs, not by speculative file views. Credits can be used for materials, prints or marketplace purchases.</p><div class="grid two">${metric('€0.80', 'royalty / repair')}${metric('14', 'verified repairs')}${metric('2.1%', 'return rate')}${metric('A-', 'model trust')}</div></aside></section>
  `);
}

function enterprise() {
  return layout('Enterprise', html`
    <section class="grid two"><div class="panel stack"><p class="eyebrow">Enterprise Portal</p><h2>Repair intelligence for product fleets.</h2><p class="muted">Brands, facilities and circular economy operators can use Re-born as an intelligence layer for spare parts, maintenance, repairability and distributed fulfilment.</p>${badges([['Fleet repair analytics', 'blue'], ['White label', ''], ['API access', 'orange'], ['Compliance reporting', 'green']])}<div class="actions"><button class="btn blue" onclick="toast('Enterprise lead captured in prototype state.')">Request demo</button></div></div><div class="panel stack"><h3>Enterprise metrics</h3><div class="grid two">${metric('1,284', 'fleet objects')}${metric('18%', 'parts recovered')}${metric('€42k', 'avoided replacement')}${metric('6.8t', 'CO₂ avoided')}</div></div></section>
  `);
}

function adminOps() {
  return layout('Admin ops', html`
    <section class="section-head"><div><p class="eyebrow">Internal console</p><h2>Repair Intelligence operations.</h2></div><p class="muted">This prototype screen clarifies what internal teams will need to monitor before scaling.</p></section><section class="grid three"><div class="panel stack"><h3>Graph queue</h3><p class="muted">${S.api.knowledgeNodes.length || 12} graph records visible.</p>${badges([['Dimensions', 'orange'], ['Material reports', 'blue'], ['Failed fits', 'danger']])}</div><div class="panel stack"><h3>Provider risk</h3><p class="muted">${getActiveProviders().length} providers available for matching.</p>${badges([['SLA review', 'orange'], ['Trust Engine', 'blue']])}</div><div class="panel stack"><h3>AI moderation</h3><p class="muted">Generated models are quarantined until printability and safety checks pass.</p>${badges([['Validation gate', 'green'], ['Safety baseline', 'danger']])}</div></section><section class="section panel">${apiSnapshot()}</section>
  `);
}

const routes = {
  '/': home,
  '/start': start,
  '/capture': capture,
  '/diagnosis': diagnosis,
  '/repair-paths': repairPaths,
  '/part-detail': partDetail,
  '/provider-network': providerNetwork,
  '/checkout': checkout,
  '/ai-generation': aiGeneration,
  '/account': account,
  '/provider': provider,
  '/maker': maker,
  '/enterprise': enterprise,
  '/admin-ops': adminOps
};

function currentRoute() {
  return location.hash.replace('#', '') || '/';
}

function render() {
  const route = currentRoute();
  const view = routes[route] || home;
  app.innerHTML = view();
  app.focus({ preventScroll: true });
  if (nav) nav.classList.remove('is-open');
  if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
}

async function refreshApiData(options = {}) {
  if (!window.REBORN_API) return;
  if (!options.silent) setBusy(true);

  try {
    const bootstrap = await window.REBORN_API.bootstrap();
    if (!bootstrap.ok && bootstrap.mode === 'mock') {
      S.setApi({
        status: 'mock',
        mode: 'mock',
        message: 'Static prototype mode: local mock data is active.',
        lastError: null,
        lastSyncAt: new Date().toISOString()
      });
      return;
    }

    const latestCase = bootstrap.repair_cases[0] || S.api.repairCase;
    S.setApi({
      status: 'live',
      mode: 'live',
      message: 'Connected to PHP API and SQLite development database.',
      lastError: null,
      repairCase: latestCase,
      repairCases: bootstrap.repair_cases,
      providers: bootstrap.providers,
      knowledgeNodes: bootstrap.knowledge_nodes,
      repairPaths: bootstrap.repair_paths,
      lastSyncAt: new Date().toISOString()
    });
  } catch (error) {
    S.setApi({
      status: 'error',
      mode: 'mock',
      message: `API unavailable: ${error.message}. Using local mock data.`,
      lastError: error.message,
      lastSyncAt: new Date().toISOString()
    });
  } finally {
    setBusy(false);
    if (!options.silent) toast('Prototype data refreshed.');
    render();
  }
}

async function bootApi() {
  render();
  const health = await window.REBORN_API.health();

  if (health.ok) {
    S.setApi({ status: 'live', mode: 'live', message: health.message, lastError: null });
    await refreshApiData({ silent: true });
  } else {
    S.setApi({ status: 'mock', mode: 'mock', message: health.message, lastError: health.reason || null, lastSyncAt: new Date().toISOString() });
    render();
  }
}

async function createDemoRepairCase() {
  await createRepairCaseFromValues({
    title: 'Bosch Series 4 dishwasher basket wheel',
    category: 'home_appliance',
    description: 'The lower basket wheel is broken. The dishwasher still works but the basket no longer slides correctly.'
  });
}

async function createRepairCaseFromValues(payload) {
  if (S.api.status !== 'live') {
    toast('Backend API is not live. Start the PHP server to create a real repair case.');
    location.hash = '#/start';
    return null;
  }

  setBusy(true);
  try {
    const result = await window.REBORN_API.createRepairCase(payload);
    const repairCase = result.repair_case;
    S.setApi({ repairCase, repairCases: [repairCase, ...S.api.repairCases], repairPaths: [], diagnosis: null, lastSyncAt: new Date().toISOString() });
    toast('Live repair case created.');
    location.hash = '#/capture';
    return repairCase;
  } catch (error) {
    S.setApi({ status: 'error', message: `Could not create repair case: ${error.message}`, lastError: error.message });
    toast('Could not create repair case.');
    return null;
  } finally {
    setBusy(false);
    render();
  }
}

async function runLiveDiagnosis() {
  if (S.api.status !== 'live') {
    toast('Backend API is not live. The screen will continue with mock diagnosis.');
    location.hash = '#/diagnosis';
    return;
  }

  setBusy(true);
  try {
    let repairCase = S.api.repairCase;
    if (!repairCase) {
      repairCase = await createRepairCaseFromValues({
        title: 'Dishwasher basket wheel',
        category: 'home_appliance',
        description: 'The lower basket wheel is broken. The dishwasher still works but the basket no longer slides correctly.'
      });
    }
    if (!repairCase) return;

    const result = await window.REBORN_API.diagnoseRepairCase(repairCase.id);
    S.setApi({
      status: 'live',
      message: 'Diagnosis completed through Recognition Engine + Knowledge Engine mock.',
      repairCase: result.repair_case,
      diagnosis: result.diagnosis,
      repairPaths: result.repair_paths || [],
      providers: result.providers || S.api.providers,
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Live diagnosis completed.');
    location.hash = '#/diagnosis';
  } catch (error) {
    S.setApi({ status: 'error', message: `Diagnosis failed: ${error.message}`, lastError: error.message });
    toast('Diagnosis failed.');
  } finally {
    setBusy(false);
    render();
  }
}

function submitIntakeFromPrototype(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const formData = new FormData(form);
  const title = String(formData.get('title') || '').trim();
  const brand = String(formData.get('brand') || '').trim();
  const category = String(formData.get('category') || 'repair').trim();
  const description = String(formData.get('description') || '').trim();

  createRepairCaseFromValues({
    title: brand ? `${brand} — ${title}` : title,
    category,
    description
  });
}

window.refreshApiData = refreshApiData;
window.createDemoRepairCase = createDemoRepairCase;
window.runLiveDiagnosis = runLiveDiagnosis;
window.submitIntakeFromPrototype = submitIntakeFromPrototype;
window.render = render;

window.addEventListener('hashchange', render);
window.addEventListener('reborn:state', () => {});
menuButton?.addEventListener('click', () => {
  const open = nav.classList.toggle('is-open');
  menuButton.setAttribute('aria-expanded', String(open));
});

bootApi();
