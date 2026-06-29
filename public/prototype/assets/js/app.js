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
  const auth = S.auth;
  const statusClass = api.status === 'live' ? 'live' : api.status === 'mock' ? 'mock' : api.status === 'error' ? 'error' : 'checking';
  const label = api.status === 'live' ? 'Live API' : api.status === 'mock' ? 'Mock mode' : api.status === 'error' ? 'API error' : 'Checking API';
  const sync = api.lastSyncAt ? `Last sync ${new Date(api.lastSyncAt).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}` : 'No sync yet';
  const user = auth.user;
  const authLabel = auth.status === 'authenticated' && user ? `${user.name || user.email} · ${humanRole(user.role)}` : auth.tokenStored ? 'Saved token' : 'Guest';
  return html`<div class="api-banner ${statusClass}" role="status">
    <div><strong>${label}</strong><span>${safe(api.message)}</span></div>
    <div class="api-banner-actions">
      <span>${sync}</span>
      <span class="auth-chip ${auth.status === 'authenticated' ? 'signed-in' : ''}">${safe(authLabel)}</span>
      ${auth.status === 'authenticated'
        ? `<button class="mini-button" type="button" onclick="handleLogout()" ${S.busy ? 'disabled' : ''}>Logout</button>`
        : `<a class="mini-button link-button" href="#/login">Login</a>`}
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

function humanRole(role) {
  const labels = {
    repair_user: 'Repair user',
    maker: 'Maker',
    provider: 'Provider',
    enterprise: 'Enterprise',
    admin: 'Admin',
    customer: 'Repair user'
  };
  return labels[role] || safe(role || 'Guest');
}

function roleSlug(role) {
  return role === 'repair_user' ? 'repair-user' : String(role || 'repair-user').replace(/_/g, '-');
}

function demoAccounts() {
  return [
    ['repair.user@reborn.local', 'Repair user', 'Creates owned repair cases and tracks personal repair history.'],
    ['maker@reborn.local', 'Maker', 'Views CAD/model opportunities and royalty logic.'],
    ['provider@reborn.local', 'Provider', 'Views fulfilment queue and provider network fit.'],
    ['enterprise@reborn.local', 'Enterprise', 'Views fleet repair intelligence and category metrics.'],
    ['admin@reborn.local', 'Admin', 'Views all operating dashboards and previews every role.']
  ];
}

function authRequiredPanel(target = 'this dashboard') {
  return html`<section class="grid two">
    <div class="panel stack">
      <p class="eyebrow">Authentication required</p>
      <h2>Login to open ${safe(target)}.</h2>
      <p class="muted">Step 10 connects the prototype to the Identity API introduced in Step 8 and the role dashboards introduced in Step 9.</p>
      <div class="actions"><a class="btn green" href="#/login">Open demo login</a><button class="btn secondary" onclick="loginAsDemo('repair.user@reborn.local')">Login as repair user</button></div>
    </div>
    <aside class="panel dark-panel stack">
      <h3>Protected by the backend</h3>
      <p class="muted">The prototype stores a Bearer token in localStorage and sends it to protected API endpoints. Logout revokes the session server-side.</p>
      ${badges([['Bearer token', 'blue'], ['Role access', 'green'], ['SQLite sessions', 'orange']])}
    </aside>
  </section>`;
}

function login() {
  setActiveNav('login');
  const isLive = S.api.status === 'live';
  const user = S.auth.user;
  return layout('Login', html`
    <section class="hero">
      <form class="panel stack" onsubmit="handleLogin(event)">
        <p class="eyebrow">Identity MVP</p>
        <h2>${user ? `Signed in as ${safe(user.name || user.email)}` : 'Login with a demo role.'}</h2>
        <p class="muted">Use one of the seeded accounts. Password is <code>password</code>. This is prototype authentication only, backed by the PHP API when the server is live.</p>
        ${!isLive ? `<div class="notice warning"><strong>Mock mode</strong><span>Start the PHP server to test real login.</span></div>` : ''}
        <div class="form-grid">
          <div class="field"><label for="loginEmail">Email</label><input id="loginEmail" name="email" value="admin@reborn.local" autocomplete="username" /></div>
          <div class="field"><label for="loginPassword">Password</label><input id="loginPassword" name="password" type="password" value="password" autocomplete="current-password" /></div>
        </div>
        <div class="actions"><button class="btn green" type="submit" ${S.busy || !isLive ? 'disabled' : ''}>Login</button>${user ? `<button class="btn secondary" type="button" onclick="handleLogout()">Logout</button>` : ''}<a class="btn secondary" href="#/account">Open dashboard</a></div>
      </form>
      <aside class="panel dark-panel stack">
        <h3>Demo accounts</h3>
        <div class="demo-account-list">
          ${demoAccounts().map(([email, role, text]) => `<button type="button" class="demo-account" onclick="loginAsDemo('${safe(email)}')" ${S.busy || !isLive ? 'disabled' : ''}><strong>${safe(role)}</strong><span>${safe(email)}</span><em>${safe(text)}</em></button>`).join('')}
        </div>
      </aside>
    </section>
  `);
}

function dashboardMetrics(metrics = {}) {
  const entries = Object.entries(metrics || {});
  if (!entries.length) return '<p class="muted">No metrics returned yet.</p>';
  return `<div class="grid four">${entries.slice(0, 8).map(([key, value]) => metric(String(value ?? 0), key.replace(/_/g, ' '))).join('')}</div>`;
}

function compactTable(rows = [], columns = []) {
  if (!rows || !rows.length) return '<p class="muted">No records yet.</p>';
  const cols = columns.length ? columns : Object.keys(rows[0]).slice(0, 5);
  return `<div class="table-wrap"><table class="table"><thead><tr>${cols.map(c => `<th>${safe(c.replace(/_/g, ' '))}</th>`).join('')}</tr></thead><tbody>${rows.slice(0, 6).map(row => `<tr>${cols.map(c => `<td>${safe(Array.isArray(row[c]) ? row[c].join(', ') : row[c])}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}

function roleDashboardContent(role, dashboard) {
  if (!dashboard) return '<p class="muted">Dashboard not loaded yet. Use Refresh dashboard.</p>';

  const roleName = humanRole(dashboard.role || role);
  const headline = dashboard.headline || `${roleName} dashboard`;

  let primary = '';
  if (dashboard.repair_cases) primary += `<div class="panel stack"><h3>Repair cases</h3>${compactTable(dashboard.repair_cases, ['title', 'category', 'status', 'confidence_score'])}</div>`;
  if (dashboard.next_actions) primary += `<div class="panel stack"><h3>Next actions</h3>${compactTable(dashboard.next_actions, ['action', 'reason', 'case_id'])}</div>`;
  if (dashboard.models) primary += `<div class="panel stack"><h3>Models</h3>${compactTable(dashboard.models, ['title', 'component_label', 'license', 'verification_status'])}</div>`;
  if (dashboard.opportunities) primary += `<div class="panel stack"><h3>Repair opportunities</h3>${compactTable(dashboard.opportunities, ['title', 'category', 'recognized_product', 'confidence_score'])}</div>`;
  if (dashboard.candidate_jobs) primary += `<div class="panel stack"><h3>Candidate jobs</h3>${compactTable(dashboard.candidate_jobs, ['title', 'category', 'recognized_product', 'status'])}</div>`;
  if (dashboard.provider_network) primary += `<div class="panel stack"><h3>Provider network</h3>${compactTable(dashboard.provider_network, ['name', 'city', 'rating', 'average_lead_time_days'])}</div>`;
  if (dashboard.category_breakdown) primary += `<div class="panel stack"><h3>Category breakdown</h3>${compactTable(dashboard.category_breakdown, ['category', 'total'])}</div>`;
  if (dashboard.latest_cases) primary += `<div class="panel stack"><h3>Latest cases</h3>${compactTable(dashboard.latest_cases, ['title', 'category', 'status', 'confidence_score'])}</div>`;
  if (dashboard.users_by_role) primary += `<div class="panel stack"><h3>Users by role</h3>${compactTable(dashboard.users_by_role, ['role', 'total'])}</div>`;
  if (dashboard.cases_by_status) primary += `<div class="panel stack"><h3>Cases by status</h3>${compactTable(dashboard.cases_by_status, ['status', 'total'])}</div>`;
  if (dashboard.latest_events) primary += `<div class="panel stack"><h3>Latest domain events</h3>${compactTable(dashboard.latest_events, ['name', 'occurred_at', 'id'])}</div>`;

  return html`
    <section class="section-head"><div><p class="eyebrow">${safe(roleName)}</p><h2>${safe(headline)}</h2></div><p class="muted">Live role dashboard returned by the PHP API. Admin can preview all roles; normal users are constrained by backend permissions.</p></section>
    <section class="panel stack">${dashboardMetrics(dashboard.metrics)}</section>
    <section class="section grid two">${primary || '<div class="panel stack"><h3>Dashboard payload</h3><p class="muted">No collection rows returned yet.</p></div>'}</section>
  `;
}

function roleDashboardView(role) {
  setActiveNav(role === 'maker' ? 'maker' : role === 'provider' ? 'provider-network' : role === 'admin' ? 'account' : 'account');
  if (S.auth.status !== 'authenticated') return layout(`${humanRole(role)} dashboard`, authRequiredPanel(`${humanRole(role)} dashboard`));
  const dashboard = S.api.roleDashboards[role] || (S.auth.user?.role === role || (role === 'repair_user' && S.auth.user?.role === 'repair_user') ? S.api.dashboard : null);
  return layout(`${humanRole(role)} dashboard`, html`
    ${roleDashboardContent(role, dashboard)}
    <section class="section panel stack"><h3>Dashboard controls</h3><p class="muted">The visible data depends on the currently authenticated role and backend access policy.</p><div class="actions"><button class="btn green" onclick="loadRoleDashboard('${safe(role)}')" ${S.busy ? 'disabled' : ''}>Refresh dashboard</button><a class="btn secondary" href="#/login">Switch role</a></div></section>
  `);
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
          <div class="field"><label for="repairCategory">Category</label><select id="repairCategory" name="category"><option value="home_appliance">Home appliance</option><option value="consumer_electronics">Consumer electronics</option><option value="furniture">Furniture</option><option value="mobility">Mobility</option><option value="sport">Sport</option><option value="tooling">Tooling</option><option value="generic">Generic</option></select></div>
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


function activeAttachments() {
  return Array.isArray(S.api.repairAttachments) ? S.api.repairAttachments : [];
}

function activeRecognitionJobs() {
  return Array.isArray(S.api.recognitionJobs) ? S.api.recognitionJobs : [];
}

function activeRecognitionJob() {
  return S.api.recognitionJob || activeRecognitionJobs()[0] || null;
}

function mockRecognitionResult() {
  return {
    object_guess: { label: 'appliance knob / plastic cover / hinge / wearable case', confidence: 0.72 },
    damage_assessment: { type: 'broken_part', severity: 'medium', repairability_score: 0.78 },
    recommended_next_step: { path: 'ask_more_photos', reason: 'Mock fallback suggests adding more angles before choosing a repair path.' },
    suggested_inputs: ['Add one photo from the side', 'Measure the broken part width', 'Upload any existing CAD or manual'],
    repair_notes: ['This is a preliminary AI diagnosis.', 'Final manufacturability must be verified before production.']
  };
}

function selectedFilePreview() {
  const files = Array.isArray(S.selectedUploadFiles) ? S.selectedUploadFiles : [];
  if (!files.length) return '<p class="muted small">No local files selected yet.</p>';

  return `<div class="upload-preview-grid">${files.map((file, index) => {
    const isImage = String(file.type || '').startsWith('image/');
    const url = isImage ? URL.createObjectURL(file) : '';
    return `<div class="upload-preview"><div class="upload-thumb">${isImage ? `<img src="${url}" alt="Selected upload preview ${index + 1}" />` : '▣'}</div><strong>${safe(file.name)}</strong><span>${safe(file.type || 'unknown type')} · ${Math.ceil((file.size || 0) / 1024)} KB</span></div>`;
  }).join('')}</div>`;
}

function attachmentList() {
  const attachments = activeAttachments();
  if (!attachments.length) return '<p class="muted small">No uploaded attachments yet. Upload photos, manuals or CAD evidence before running recognition.</p>';

  return `<div class="attachment-list">${attachments.map(attachment => `<div class="attachment-row"><div><strong>${safe(attachment.original_filename)}</strong><span>${safe(attachment.mime_type)} · ${Math.ceil(Number(attachment.size_bytes || 0) / 1024)} KB</span></div><code>${safe(String(attachment.id || '').slice(0, 8))}</code></div>`).join('')}</div>`;
}

function recognitionResultPanel() {
  const job = activeRecognitionJob();
  const result = job?.result_json;
  if (!job || !result) {
    return html`<div class="panel stack"><h3>AI recognition result</h3><p class="muted">Run AI recognition after upload to produce a preliminary repair diagnosis.</p></div>`;
  }

  return html`<div class="panel stack recognition-result">
    <div class="section-head"><div><p class="eyebrow">AI recognition</p><h3>${safe(result.object_guess?.label || 'Preliminary object guess')}</h3></div><span class="badge green">${Math.round(Number(result.object_guess?.confidence || 0) * 100)}% confidence</span></div>
    <div class="grid three">
      ${metric(result.damage_assessment?.type || 'unknown', 'Damage type')}
      ${metric(result.damage_assessment?.severity || 'review', 'Severity')}
      ${metric(result.recommended_next_step?.path || 'ask_more_photos', 'Next path')}
    </div>
    <p class="muted">${safe(result.recommended_next_step?.reason || 'Recognition completed with mock AI result.')}</p>
    <div class="badges">${(result.suggested_inputs || []).map(item => `<span class="badge blue">${safe(item)}</span>`).join('')}</div>
    <div class="notice"><strong>MVP guardrail</strong><span>${safe((result.repair_notes || [])[1] || 'Final manufacturability must be verified before production.')}</span></div>
  </div>`;
}

function diagnosisTimeline() {
  const repairCase = S.api.repairCase;
  const attachments = activeAttachments();
  const job = activeRecognitionJob();
  const result = job?.result_json;
  const rows = [
    ['1', 'Case created', repairCase ? `Repair DNA draft ${String(repairCase.id).slice(0, 8)}` : 'Create or select a repair case first.', repairCase ? 'done' : ''],
    ['2', 'Files uploaded', attachments.length ? `${attachments.length} attachment(s) linked to the repair case.` : 'Add photos, manuals or CAD files.', attachments.length ? 'done' : ''],
    ['3', 'AI recognition requested', job ? `Job ${String(job.id).slice(0, 8)} is ${job.status}.` : 'Run recognition from uploaded evidence.', job ? 'done' : ''],
    ['4', 'Preliminary diagnosis completed', result ? `${result.object_guess?.label || 'Object guessed'} with repairability score ${result.damage_assessment?.repairability_score || '-'}.` : 'Waiting for recognition result.', result ? 'done' : ''],
    ['5', 'Next repair action suggested', result ? `${result.recommended_next_step?.path}: ${result.recommended_next_step?.reason}` : 'Re-born will suggest the next action after diagnosis.', result ? 'done' : '']
  ];

  return `<div class="timeline diagnosis-timeline">${rows.map(([num, title, text, state]) => `<div class="timeline-row ${state}"><div class="timeline-time">${safe(num)}</div><div class="timeline-content"><strong>${safe(title)}</strong><p class="muted small">${safe(text)}</p></div></div>`).join('')}</div>`;
}

function capture() {
  setActiveNav('start');

  if (S.api.status !== 'live') {
    return layout('Upload repair evidence', html`
      <section class="section-head"><div><p class="eyebrow">Step 11 · Mock fallback</p><h2>Upload photos for repair diagnosis</h2></div><p class="muted">Add photos, manuals or CAD files so Re-born can understand what needs to be repaired.</p></section>
      <section class="grid two">
        <div class="panel stack">
          <h3>Local prototype mode</h3>
          <p class="muted">The backend is not live, so this screen shows the Step 11 flow with local staged files and a mock recognition result.</p>
          <input id="repairFileInput" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.stl,.step,.stp,.obj" onchange="handleRepairFilesSelected(event)" />
          ${selectedFilePreview()}
          <div class="actions"><button class="btn green" onclick="runMockRecognition()">Run mock AI recognition</button><a class="btn secondary" href="#/start">Back to intake</a></div>
        </div>
        <aside class="panel stack"><h3>Diagnosis timeline</h3>${diagnosisTimeline()}</aside>
      </section>
      <section class="section">${recognitionResultPanel()}</section>
    `, { currentStep: 'capture' });
  }

  if (S.auth.status !== 'authenticated') {
    return layout('Upload repair evidence', authRequiredPanel('repair evidence upload'));
  }

  const repairCase = S.api.repairCase;
  if (!repairCase) {
    return layout('Upload repair evidence', html`
      <section class="grid two">
        <div class="panel stack">
          <p class="eyebrow">Step 11</p>
          <h2>Upload photos for repair diagnosis</h2>
          <p class="muted">Add photos, manuals or CAD files so Re-born can understand what needs to be repaired. First create a repair case so every file is linked to a real Repair Journey.</p>
          <div class="actions"><button class="btn green" onclick="createDemoRepairCase()" ${S.busy ? 'disabled' : ''}>Create repair case</button><a class="btn secondary" href="#/start">Open intake form</a></div>
        </div>
        <aside class="panel dark-panel stack"><h3>Repair-first rule</h3><p class="muted">The upload is not a generic STL library. It is evidence for a real object that must return to function.</p>${badges([['Repair case required', 'green'], ['Attachment evidence', 'blue'], ['AI recognition', 'orange']])}</aside>
      </section>
    `, { currentStep: 'capture' });
  }

  return layout('Upload repair evidence', html`
    <section class="section-head"><div><p class="eyebrow">Step 11 · Repair evidence</p><h2>Upload photos for repair diagnosis</h2></div><p class="muted">Add photos, manuals or CAD files so Re-born can understand what needs to be repaired.</p></section>
    <section class="grid two">
      <div class="panel stack">
        <div class="notice"><strong>Active repair case</strong><span>${safe(repairCase.title)} · ${safe(repairCase.category)} · ${safe(String(repairCase.id).slice(0, 8))}</span></div>
        <div class="dropzone file-dropzone">
          <div><div class="dropzone-icon">▣</div><h3>Select repair evidence</h3><p class="muted">JPEG, PNG, WebP, PDF, STL, STEP, STP or OBJ. MVP limit: 15 MB per file.</p><input id="repairFileInput" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf,.stl,.step,.stp,.obj" onchange="handleRepairFilesSelected(event)" /></div>
        </div>
        ${selectedFilePreview()}
        <div class="actions"><button class="btn green" onclick="uploadSelectedRepairFiles()" ${S.busy ? 'disabled' : ''}>Upload selected files</button><button class="btn orange" onclick="runAIRecognition()" ${S.busy || !activeAttachments().length ? 'disabled' : ''}>Run AI recognition</button><a class="btn secondary" href="#/start">Edit intake</a></div>
      </div>
      <aside class="panel stack">
        <h3>Uploaded attachments</h3>
        ${attachmentList()}
      </aside>
    </section>
    <section class="section grid two">
      <div class="panel stack"><h3>Diagnosis timeline</h3>${diagnosisTimeline()}</div>
      ${recognitionResultPanel()}
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
  if (S.auth.status !== 'authenticated') {
    return layout('Account', authRequiredPanel('your repair dashboard'));
  }

  const dashboard = S.api.dashboard;
  return layout('Account', html`
    <section class="section-head"><div><p class="eyebrow">Authenticated dashboard</p><h2>${safe(S.auth.user?.name || S.auth.user?.email)}.</h2></div><p class="muted">Role: ${safe(humanRole(S.auth.user?.role))}. This page is backed by <code>GET /api/v1/dashboard</code>.</p></section>
    ${roleDashboardContent(S.auth.user?.role || 'repair_user', dashboard)}
    <section class="section panel stack"><h3>Session controls</h3><p class="muted">Token stored: ${S.auth.tokenStored ? 'yes' : 'no'}. Logout revokes the backend session and clears localStorage.</p><div class="actions"><button class="btn green" onclick="loadMyDashboard()" ${S.busy ? 'disabled' : ''}>Refresh my dashboard</button><button class="btn secondary" onclick="handleLogout()" ${S.busy ? 'disabled' : ''}>Logout</button><a class="btn secondary" href="#/login">Switch role</a></div></section>
  `);
}

function provider() {
  return roleDashboardView('provider');
}

function maker() {
  return roleDashboardView('maker');
}

function enterprise() {
  return roleDashboardView('enterprise');
}

function adminOps() {
  return roleDashboardView('admin');
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
  '/login': login,
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
      repairAttachments: bootstrap.repair_attachments || [],
      recognitionJobs: bootstrap.recognition_jobs || [],
      recognitionJob: (bootstrap.recognition_jobs || [])[0] || S.api.recognitionJob,
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
  S.setAuth({ tokenStored: Boolean(window.REBORN_API?.getToken()) });
  const health = await window.REBORN_API.health();

  if (health.ok) {
    S.setApi({ status: 'live', mode: 'live', message: health.message, lastError: null });
    await bootAuthSession();
    await refreshApiData({ silent: true });
    if (S.auth.status === 'authenticated') await loadMyDashboard({ silent: true });
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

  if (S.auth.status !== 'authenticated') {
    toast('Login required to create a live repair case.');
    location.hash = '#/login';
    return null;
  }

  setBusy(true);
  try {
    const result = await window.REBORN_API.createRepairCase(payload);
    const repairCase = result.repair_case;
    S.setApi({ repairCase, repairCases: [repairCase, ...S.api.repairCases], repairPaths: [], repairAttachments: [], recognitionJobs: [], recognitionJob: null, diagnosis: null, lastSyncAt: new Date().toISOString() });
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


function handleRepairFilesSelected(event) {
  const files = Array.from(event.currentTarget.files || []);
  S.set('selectedUploadFiles', files);
  toast(files.length ? `${files.length} file(s) selected.` : 'No files selected.');
  render();
}

async function uploadSelectedRepairFiles() {
  if (S.api.status !== 'live') {
    toast('Backend API is not live. Use mock recognition instead.');
    return;
  }

  if (S.auth.status !== 'authenticated') {
    toast('Login required to upload repair evidence.');
    location.hash = '#/login';
    return;
  }

  const repairCase = S.api.repairCase;
  if (!repairCase) {
    toast('Create a repair case before uploading files.');
    location.hash = '#/start';
    return;
  }

  const files = Array.isArray(S.selectedUploadFiles) ? S.selectedUploadFiles : [];
  if (!files.length) {
    toast('Select at least one file first.');
    return;
  }

  setBusy(true);
  try {
    for (const file of files) {
      await window.REBORN_API.uploadRepairAttachment(repairCase.id, file, String(file.type || '').startsWith('image/') ? 'diagnostic_photo' : 'repair_asset');
    }
    const payload = await window.REBORN_API.getRepairAttachments(repairCase.id);
    S.set('selectedUploadFiles', []);
    S.setApi({ repairAttachments: payload.attachments || [], lastSyncAt: new Date().toISOString() });
    toast('Repair evidence uploaded.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Upload failed: ${error.message}`, lastError: error.message });
    toast(`Upload failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

async function runAIRecognition() {
  if (S.api.status !== 'live') {
    runMockRecognition();
    return;
  }

  const repairCase = S.api.repairCase;
  const attachments = activeAttachments();
  if (!repairCase || !attachments.length) {
    toast('Upload evidence before running AI recognition.');
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.requestRecognition(repairCase.id, attachments.map(attachment => attachment.id));
    const jobs = await window.REBORN_API.getRecognitionJobs(repairCase.id).catch(() => ({ recognition_jobs: [payload.recognition_job] }));
    S.setApi({
      recognitionJob: payload.recognition_job,
      recognitionJobs: jobs.recognition_jobs || [payload.recognition_job],
      lastSyncAt: new Date().toISOString(),
      message: 'AI recognition completed from uploaded repair evidence.',
      status: 'live',
      lastError: null
    });
    toast('AI recognition completed.');
  } catch (error) {
    S.setApi({ status: 'error', message: `AI recognition failed: ${error.message}`, lastError: error.message });
    toast(`AI recognition failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

function runMockRecognition() {
  const result = mockRecognitionResult();
  const job = {
    id: 'mock-recognition-job',
    repair_case_id: S.api.repairCase?.id || 'mock-case',
    requested_by: S.auth.user?.id || 'mock-user',
    status: 'completed',
    input_attachment_ids: ['mock-attachment'],
    result_json: result,
    created_at: new Date().toISOString(),
    started_at: new Date().toISOString(),
    completed_at: new Date().toISOString()
  };
  S.setApi({ recognitionJob: job, recognitionJobs: [job], repairAttachments: activeAttachments().length ? activeAttachments() : [{ id: 'mock-attachment', original_filename: 'mock-photo.png', mime_type: 'image/png', size_bytes: 2048 }] });
  toast('Mock AI recognition completed.');
  render();
}

async function bootAuthSession() {
  if (!window.REBORN_API?.getToken()) {
    S.setAuth({ status: 'guest', user: null, tokenStored: false });
    return;
  }

  try {
    const payload = await window.REBORN_API.me();
    S.setAuth({ status: 'authenticated', user: payload.user, tokenStored: true });
  } catch (_error) {
    window.REBORN_API.setToken(null);
    S.setAuth({ status: 'guest', user: null, tokenStored: false });
  }
}

async function handleLogin(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const formData = new FormData(form);
  await loginWithCredentials(String(formData.get('email') || ''), String(formData.get('password') || ''));
}

async function loginAsDemo(email) {
  await loginWithCredentials(email, 'password');
}

async function loginWithCredentials(email, password) {
  if (S.api.status !== 'live') {
    toast('Start the PHP API server before logging in.');
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.login(email.trim(), password);
    S.setAuth({ status: 'authenticated', user: payload.user, tokenStored: true, lastLoginAt: new Date().toISOString() });
    toast(`Logged in as ${humanRole(payload.user.role)}.`);
    await refreshApiData({ silent: true });
    await loadMyDashboard({ silent: true });
    location.hash = '#/account';
  } catch (error) {
    S.setAuth({ status: 'guest', user: null, tokenStored: Boolean(window.REBORN_API.getToken()) });
    toast(`Login failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

async function handleLogout() {
  setBusy(true);
  try {
    if (S.auth.status === 'authenticated') await window.REBORN_API.logout();
    else window.REBORN_API.setToken(null);
    S.setAuth({ status: 'guest', user: null, tokenStored: false, lastLoginAt: null });
    S.setApi({ dashboard: null, roleDashboards: {}, repairCases: [], repairCase: null, repairPaths: [] });
    toast('Logged out.');
    location.hash = '#/login';
  } catch (error) {
    window.REBORN_API.setToken(null);
    S.setAuth({ status: 'guest', user: null, tokenStored: false });
    toast(`Session cleared after logout error: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

async function loadMyDashboard(options = {}) {
  if (S.auth.status !== 'authenticated') {
    if (!options.silent) toast('Login required.');
    return null;
  }

  if (!options.silent) setBusy(true);
  try {
    const payload = await window.REBORN_API.dashboard();
    S.setApi({ dashboard: payload.dashboard, lastSyncAt: new Date().toISOString() });
    if (!options.silent) toast('Dashboard refreshed.');
    return payload.dashboard;
  } catch (error) {
    if (!options.silent) toast(`Dashboard unavailable: ${error.message}`);
    return null;
  } finally {
    if (!options.silent) {
      setBusy(false);
      render();
    }
  }
}

async function loadRoleDashboard(role) {
  if (S.auth.status !== 'authenticated') {
    toast('Login required.');
    location.hash = '#/login';
    return null;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.roleDashboard(roleSlug(role));
    S.setApi({
      roleDashboards: { ...S.api.roleDashboards, [role]: payload.dashboard },
      lastSyncAt: new Date().toISOString()
    });
    toast(`${humanRole(role)} dashboard refreshed.`);
    return payload.dashboard;
  } catch (error) {
    toast(`Role dashboard unavailable: ${error.message}`);
    return null;
  } finally {
    setBusy(false);
    render();
  }
}

window.refreshApiData = refreshApiData;
window.createDemoRepairCase = createDemoRepairCase;
window.runLiveDiagnosis = runLiveDiagnosis;
window.submitIntakeFromPrototype = submitIntakeFromPrototype;
window.handleRepairFilesSelected = handleRepairFilesSelected;
window.uploadSelectedRepairFiles = uploadSelectedRepairFiles;
window.runAIRecognition = runAIRecognition;
window.runMockRecognition = runMockRecognition;
window.handleLogin = handleLogin;
window.loginAsDemo = loginAsDemo;
window.handleLogout = handleLogout;
window.loadMyDashboard = loadMyDashboard;
window.loadRoleDashboard = loadRoleDashboard;
window.render = render;

window.addEventListener('hashchange', render);
window.addEventListener('reborn:state', () => {});
menuButton?.addEventListener('click', () => {
  const open = nav.classList.toggle('is-open');
  menuButton.setAttribute('aria-expanded', String(open));
});

bootApi();
