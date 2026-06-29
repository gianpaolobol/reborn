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
    ['checkout', '05', 'Order'],
    ['fulfilment', '06', 'Fulfil'],
    ['learning', '07', 'Learn'],
    ['trust', '08', 'Trust']
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
  const match = activeProviderMatch();
  const matchedProviders = match?.result_json?.ranked_providers;
  if (Array.isArray(matchedProviders) && matchedProviders.length) {
    return matchedProviders.map(provider => ({
      id: provider.provider_id,
      name: provider.name,
      type: (provider.matched_capabilities || [])[0] || 'Repair provider',
      distance: `${provider.city || 'Local'}, ${provider.country || ''}`,
      rating: Number(provider.rating || 0).toFixed(1),
      jobs: 'matched',
      price: formatEuro(Number(provider.estimated_quote_cents || 0)),
      eta: `${provider.estimated_days || provider.average_lead_time_days || '?'} days`,
      trust: Math.round(Number(provider.match_score || 0) * 100),
      material: (provider.matched_capabilities || provider.capabilities || []).slice(0, 3).join(' / ') || 'Mixed capabilities',
      matchScore: Number(provider.match_score || 0),
      providerId: provider.provider_id,
      quoteCents: Number(provider.estimated_quote_cents || 0),
      qualityChecks: provider.quality_checks || []
    }));
  }

  if (!S.api.providers.length) return D.providers;

  return S.api.providers.map(provider => {
    const capabilities = Array.isArray(provider.capabilities) ? provider.capabilities : [];
    return {
      id: provider.id,
      name: provider.name,
      type: capabilities[0] || 'Repair provider',
      distance: `${provider.city || 'Local'}, ${provider.country || ''}`,
      rating: Number(provider.rating || 0).toFixed(1),
      jobs: 'API',
      price: 'Quote after validation',
      eta: `${provider.average_lead_time_days || '?'} days`,
      trust: Math.round((Number(provider.rating || 0) / 5) * 100),
      material: capabilities.slice(0, 3).join(' / ') || 'Mixed capabilities',
      providerId: provider.id
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

function activeRepairPathDecisions() {
  return Array.isArray(S.api.repairPathDecisions) ? S.api.repairPathDecisions : [];
}

function activeRepairPathDecision() {
  return S.api.repairPathDecision || activeRepairPathDecisions()[0] || null;
}

function activeProviderMatches() {
  return Array.isArray(S.api.providerMatches) ? S.api.providerMatches : [];
}

function activeProviderMatch() {
  return S.api.providerMatch || activeProviderMatches()[0] || null;
}

function activeQuoteRequests() {
  return Array.isArray(S.api.quoteRequests) ? S.api.quoteRequests : [];
}

function activeQuoteRequest() {
  return S.api.quoteRequest || activeQuoteRequests()[0] || null;
}

function activeRepairOrders() {
  return Array.isArray(S.api.repairOrders) ? S.api.repairOrders : [];
}

function activeRepairOrder() {
  return S.api.repairOrder || activeRepairOrders()[0] || null;
}

function activePaymentIntents() {
  return Array.isArray(S.api.paymentIntents) ? S.api.paymentIntents : [];
}

function activePaymentIntent() {
  return S.api.paymentIntent || activePaymentIntents()[0] || null;
}

function activeFulfilments() {
  return Array.isArray(S.api.fulfilments) ? S.api.fulfilments : [];
}

function activeFulfilment() {
  return S.api.fulfilment || activeFulfilments()[0] || null;
}

function activeCompletionReports() {
  return Array.isArray(S.api.completionReports) ? S.api.completionReports : [];
}

function activeCompletionReport() {
  return S.api.completionReport || activeCompletionReports()[0] || null;
}

function activeLearningEvents() {
  return Array.isArray(S.api.learningEvents) ? S.api.learningEvents : [];
}

function activeLearningEvent() {
  return S.api.learningEvent || activeLearningEvents()[0] || null;
}

function activeTrustReviews() {
  return Array.isArray(S.api.trustReviews) ? S.api.trustReviews : [];
}

function activeTrustReview() {
  return S.api.trustReview || activeTrustReviews()[0] || null;
}

function activeProviderQualityScore() {
  return S.api.providerQualityScore || (Array.isArray(S.api.providerQualityScores) ? S.api.providerQualityScores[0] : null) || null;
}

function activeProviderTrustSignals() {
  return Array.isArray(S.api.providerTrustSignals) ? S.api.providerTrustSignals : [];
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

function mockRepairPathDecisionResult() {
  return {
    decision_factors: { recognition_confidence: 0.72, repairability_score: 0.78, damage_type: 'broken_part', severity: 'medium', category: 'consumer_electronics', dimensional_risk: false },
    recommended_path: 'generate_part',
    ranked_paths: [
      { type: 'generate_part', title: 'Generate a repair model with AI fallback', description: 'Use AI-assisted CAD after dimensional validation.', score: 0.82, estimated_price_cents: 2490, estimated_days: 5, next_actions: ['Create constrained CAD draft', 'Check wall thickness and tolerances', 'Require provider validation'], risk_flags: { human_validation_required: true } },
      { type: 'find_provider', title: 'Find a local repair provider', description: 'Match the case to a local provider for inspection, material choice and production.', score: 0.79, estimated_price_cents: 2990, estimated_days: 4, next_actions: ['Select provider by capability', 'Validate material constraints', 'Quote repair order'], risk_flags: { provider_quote_required: true } },
      { type: 'identify_part', title: 'Find an existing verified part', description: 'Search verified sources before creating new geometry.', score: 0.74, estimated_price_cents: 1200, estimated_days: 3, next_actions: ['Check graph match', 'Compare dimensions'], risk_flags: { fit_unknown: true } }
    ],
    guardrails: ['Do not sell a file before the repair path is validated.', 'AI geometry is a draft until checks confirm dimensions.']
  };
}

function mockProviderMatchResult() {
  return {
    repair_context: { repair_case_id: S.api.repairCase?.id || 'mock-case', recommended_path: activeRepairPathDecision()?.result_json?.recommended_path || 'find_provider', selected_path_title: 'Provider-assisted repair validation', estimated_price_cents: 2990, estimated_days: 4, repairability_score: 0.78, dimensional_risk: true, requires_provider_validation: true },
    ranked_providers: [
      { provider_id: 'provider-bologna-lab', name: 'Bologna Repair Lab', city: 'Bologna', country: 'IT', capabilities: ['FDM', 'PETG', 'TPU', 'CAD validation'], matched_capabilities: ['CAD validation', 'FDM', 'PETG'], rating: 4.8, average_lead_time_days: 3, match_score: 0.91, estimated_quote_cents: 3970, estimated_days: 4, match_reason: 'Best local validation fit for repair-first fulfilment.', quality_checks: ['confirm_dimensions_before_production', 'validate_material_and_tolerance'] },
      { provider_id: 'provider-milan-maker', name: 'Milano Distributed Manufacturing', city: 'Milano', country: 'IT', capabilities: ['FDM', 'SLA', 'ASA', 'small batch'], matched_capabilities: ['FDM', 'SLA'], rating: 4.7, average_lead_time_days: 4, match_score: 0.84, estimated_quote_cents: 4290, estimated_days: 5, match_reason: 'Strong additive manufacturing capacity and backup material choices.', quality_checks: ['confirm_dimensions_before_production', 'validate_material_and_tolerance'] },
      { provider_id: 'provider-barcelona-circular', name: 'Barcelona Circular Fab', city: 'Barcelona', country: 'ES', capabilities: ['FDM', 'SLS partner', 'repair validation'], matched_capabilities: ['repair validation', 'SLS partner'], rating: 4.6, average_lead_time_days: 5, match_score: 0.81, estimated_quote_cents: 4590, estimated_days: 6, match_reason: 'Best fallback for professional validation and SLS partner routing.', quality_checks: ['provider_quote_required', 'validate_material_and_tolerance'] }
    ],
    guardrails: ['Provider matching is a repair fulfilment step, not a generic print marketplace search.', 'Quotes remain preliminary until provider validates geometry, material and tolerance constraints.']
  };
}

function mockQuoteRequest(providerId = 'provider-bologna-lab') {
  const match = activeProviderMatch();
  const provider = (match?.result_json?.ranked_providers || mockProviderMatchResult().ranked_providers).find(p => p.provider_id === providerId) || mockProviderMatchResult().ranked_providers[0];
  const production = Number(provider.estimated_quote_cents || 2990);
  const validation = 900;
  const platformFee = Math.round((production + validation) * 0.12);
  return {
    id: 'mock-quote-request',
    provider_match_id: match?.id || 'mock-provider-match',
    repair_case_id: S.api.repairCase?.id || 'mock-case',
    provider_id: provider.provider_id,
    requested_by: S.auth.user?.id || 'mock-user',
    status: 'estimated',
    quote_json: { currency: 'EUR', provider: { id: provider.provider_id, name: provider.name, city: provider.city, country: provider.country, match_score: provider.match_score }, repair_scope: { recommended_path: match?.result_json?.repair_context?.recommended_path || 'find_provider', selected_path_title: 'Provider-assisted repair validation' }, line_items: [{ label: 'Provider validation and repair planning', amount_cents: validation }, { label: 'Local repair production / fulfilment estimate', amount_cents: production }, { label: 'Re-born platform fee', amount_cents: platformFee }], subtotal_cents: production + validation, platform_fee_cents: platformFee, provider_payout_cents: production + validation, total_cents: production + validation + platformFee, estimated_days: provider.estimated_days, assumptions: ['Quote is preliminary until provider reviews photos, dimensions and material constraints.'] },
    created_at: new Date().toISOString(),
    expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString(),
    accepted_at: null
  };
}

function mockRepairOrder() {
  const quote = activeQuoteRequest() || mockQuoteRequest();
  const q = quote.quote_json || {};
  return {
    id: 'mock-repair-order',
    quote_request_id: quote.id,
    provider_match_id: quote.provider_match_id,
    repair_case_id: quote.repair_case_id,
    provider_id: quote.provider_id,
    ordered_by: S.auth.user?.id || 'mock-user',
    status: 'created',
    currency: q.currency || 'EUR',
    subtotal_cents: Number(q.subtotal_cents || 0),
    platform_fee_cents: Number(q.platform_fee_cents || 0),
    provider_payout_cents: Number(q.provider_payout_cents || 0),
    total_cents: Number(q.total_cents || 0),
    order_json: {
      source: 'mock_quote_request',
      provider: q.provider || { id: quote.provider_id, name: 'Mock repair provider' },
      repair_scope: q.repair_scope || { selected_path_title: 'Provider-assisted repair validation' },
      line_items: q.line_items || [],
      fulfilment: { estimated_days: Number(q.estimated_days || 4), provider_validation_required: true, repair_success_definition: 'The object returns to function.' },
      quality_gate: ['confirm_dimensions_before_production', 'validate_material_and_tolerance', 'close order only after repair outcome is confirmed'],
      payment: { provider: 'mock', real_money_movement: false }
    },
    created_at: new Date().toISOString(),
    confirmed_at: null,
    cancelled_at: null
  };
}

function mockPaymentIntent() {
  const order = activeRepairOrder() || mockRepairOrder();
  return {
    id: 'mock-payment-intent',
    repair_order_id: order.id,
    quote_request_id: order.quote_request_id,
    repair_case_id: order.repair_case_id,
    requested_by: S.auth.user?.id || 'mock-user',
    provider: 'mock',
    status: 'requires_mock_confirmation',
    currency: order.currency || 'EUR',
    amount_cents: Number(order.total_cents || 0),
    client_secret: 'rbn_pi_mock_client_secret',
    payment_url: '/prototype/index.html#/checkout?payment_intent=mock-payment-intent',
    metadata_json: { mvp_note: 'Mock payment intent only. No real money movement occurs in Step 14.', platform_fee_cents: Number(order.platform_fee_cents || 0), provider_payout_cents: Number(order.provider_payout_cents || 0) },
    created_at: new Date().toISOString(),
    expires_at: new Date(Date.now() + 30 * 60 * 1000).toISOString(),
    confirmed_at: null,
    cancelled_at: null
  };
}

function mockFulfilment() {
  const order = activeRepairOrder() || mockRepairOrder();
  return {
    id: 'mock-fulfilment',
    repair_order_id: order.id,
    quote_request_id: order.quote_request_id,
    repair_case_id: order.repair_case_id,
    provider_id: order.provider_id,
    requested_by: S.auth.user?.id || 'mock-user',
    accepted_by: null,
    status: 'awaiting_provider_acceptance',
    provider_notes: null,
    tracking_reference: null,
    timeline_json: [
      { event: 'fulfilment_requested', status: 'awaiting_provider_acceptance', actor_id: S.auth.user?.id || 'mock-user', note: 'Mock fulfilment created after payment authorization.', occurred_at: new Date().toISOString() }
    ],
    created_at: new Date().toISOString(),
    accepted_at: null,
    started_at: null,
    quality_checked_at: null,
    ready_at: null,
    completed_at: null,
    rejected_at: null,
    updated_at: new Date().toISOString()
  };
}

function mockCompletionReport() {
  const fulfilment = activeFulfilment() || mockFulfilment();
  return {
    id: 'mock-completion-report',
    fulfilment_id: fulfilment.id,
    repair_order_id: fulfilment.repair_order_id,
    repair_case_id: fulfilment.repair_case_id,
    provider_id: fulfilment.provider_id,
    reported_by: S.auth.user?.id || 'mock-provider',
    status: 'recorded',
    outcome_status: 'successful',
    functional_result: 'object_returned_to_function',
    customer_confirmed: true,
    object_saved: true,
    co2_avoided_grams: 1350,
    evidence_attachment_ids: [],
    outcome_json: { summary: 'Mock repair completed and object returned to function.', repair_method: 'provider_validated_replacement_part', material_used: 'PETG', quality_checks: ['fit_checked', 'function_checked'] },
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  };
}

function mockLearningEvent(report = null) {
  const completionReport = report || activeCompletionReport() || mockCompletionReport();
  return {
    id: 'mock-learning-event',
    completion_report_id: completionReport.id,
    fulfilment_id: completionReport.fulfilment_id,
    repair_case_id: completionReport.repair_case_id,
    provider_id: completionReport.provider_id,
    event_type: 'repair_outcome_confirmed',
    signal_json: { source: 'mock_completion_report', outcome_status: completionReport.outcome_status, functional_result: completionReport.functional_result, object_saved: completionReport.object_saved, co2_avoided_grams: completionReport.co2_avoided_grams },
    confidence_delta: 0.08,
    created_at: new Date().toISOString()
  };
}

function mockTrustReview(report = null) {
  const completionReport = report || activeCompletionReport() || mockCompletionReport();
  return {
    id: 'mock-trust-review',
    completion_report_id: completionReport.id,
    fulfilment_id: completionReport.fulfilment_id,
    repair_case_id: completionReport.repair_case_id,
    provider_id: completionReport.provider_id,
    reviewer_id: S.auth.user?.id || 'mock-repair-user',
    reviewer_role: S.auth.user?.role || 'repair_user',
    status: 'published',
    rating_overall: 5,
    rating_quality: 5,
    rating_communication: 4,
    rating_timeliness: 5,
    would_recommend: true,
    issue_resolved: true,
    comment: 'Mock trust signal: repair outcome confirmed and provider quality validated.',
    signals_json: { source: 'mock_trust_review', outcome_status: completionReport.outcome_status, object_saved: completionReport.object_saved, issue_resolved: true },
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  };
}

function mockProviderQualityScore(review = null) {
  const trustReview = review || activeTrustReview() || mockTrustReview();
  return {
    provider_id: trustReview.provider_id,
    review_count: 1,
    completed_repairs_count: 1,
    successful_repairs_count: 1,
    average_rating: 5,
    quality_score: 100,
    reliability_score: 100,
    communication_score: 80,
    timeliness_score: 100,
    overall_score: 97,
    trust_tier: 'trusted',
    last_review_id: trustReview.id,
    score_json: { formula_version: 'trust_v1', interpretation: 'Mock provider has a confirmed repair outcome and high trust signal.' },
    updated_at: new Date().toISOString()
  };
}

function mockProviderTrustSignal(review = null) {
  const trustReview = review || activeTrustReview() || mockTrustReview();
  return {
    id: 'mock-provider-trust-signal',
    provider_id: trustReview.provider_id,
    repair_case_id: trustReview.repair_case_id,
    completion_report_id: trustReview.completion_report_id,
    trust_review_id: trustReview.id,
    event_type: 'completion_review_scored',
    signal_json: { source: 'mock_provider_trust_review', rating_overall: trustReview.rating_overall, issue_resolved: trustReview.issue_resolved },
    score_delta: 0.15,
    created_at: new Date().toISOString()
  };
}

function fulfilmentTimeline(fulfilment) {
  const rows = Array.isArray(fulfilment?.timeline_json) ? fulfilment.timeline_json : [];
  if (!rows.length) return '<p class="muted small">No fulfilment events yet.</p>';
  return `<div class="timeline">${rows.map((row, index) => `<div class="timeline-row"><div class="timeline-time">${index + 1}</div><div class="timeline-content"><strong>${safe(String(row.status || row.event || 'event').replaceAll('_', ' '))}</strong><p class="muted small">${safe(row.note || row.event || '')}</p><p class="muted small">${safe(row.occurred_at ? new Date(row.occurred_at).toLocaleString('it-IT') : '')}</p></div></div>`).join('')}</div>`;
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

function repairPathDecisionPanel() {
  const decision = activeRepairPathDecision();
  const result = decision?.result_json;
  if (!decision || !result) {
    return html`<div class="panel stack"><h3>Repair Path Decision Engine</h3><p class="muted">After AI recognition, ask Re-born to rank concrete repair paths by feasibility, cost, risk, ETA and learning value.</p><div class="actions"><button class="btn green" onclick="runRepairPathDecision()" ${S.busy || !activeRecognitionJob() ? 'disabled' : ''}>Generate repair paths</button><a class="btn secondary" href="#/repair-paths">Open repair paths</a></div></div>`;
  }

  const paths = Array.isArray(result.ranked_paths) ? result.ranked_paths : [];
  const top = paths[0] || {};
  return html`<div class="panel stack decision-result">
    <div class="section-head"><div><p class="eyebrow">Decision Engine v1</p><h3>${safe(top.title || result.recommended_path || 'Recommended repair path')}</h3></div><span class="badge green">Score ${Math.round(Number(top.score || 0) * 100)}</span></div>
    <p class="muted">Recommended path: <strong>${safe(result.recommended_path || 'review')}</strong>. Re-born ranked this as a repair action, not as a file purchase.</p>
    <div class="grid three">
      ${metric(result.decision_factors?.damage_type || 'unknown', 'Damage')}
      ${metric(result.decision_factors?.severity || 'review', 'Severity')}
      ${metric(result.decision_factors?.repairability_score || '-', 'Repairability')}
    </div>
    <div class="path-mini-list">${paths.slice(0, 4).map(path => `<div class="attachment-row"><div><strong>${safe(path.title)}</strong><span>${safe(path.description)}</span></div><code>${Math.round(Number(path.score || 0) * 100)}</code></div>`).join('')}</div>
    <div class="actions"><a class="btn green" href="#/repair-paths">Review ranked paths</a><button class="btn secondary" onclick="runRepairPathDecision()" ${S.busy ? 'disabled' : ''}>Re-run decision</button></div>
  </div>`;
}

function diagnosisTimeline() {
  const repairCase = S.api.repairCase;
  const attachments = activeAttachments();
  const job = activeRecognitionJob();
  const result = job?.result_json;
  const decision = activeRepairPathDecision();
  const decisionResult = decision?.result_json;
  const rows = [
    ['1', 'Case created', repairCase ? `Repair DNA draft ${String(repairCase.id).slice(0, 8)}` : 'Create or select a repair case first.', repairCase ? 'done' : ''],
    ['2', 'Files uploaded', attachments.length ? `${attachments.length} attachment(s) linked to the repair case.` : 'Add photos, manuals or CAD files.', attachments.length ? 'done' : ''],
    ['3', 'AI recognition requested', job ? `Job ${String(job.id).slice(0, 8)} is ${job.status}.` : 'Run recognition from uploaded evidence.', job ? 'done' : ''],
    ['4', 'Preliminary diagnosis completed', result ? `${result.object_guess?.label || 'Object guessed'} with repairability score ${result.damage_assessment?.repairability_score || '-'}.` : 'Waiting for recognition result.', result ? 'done' : ''],
    ['5', 'Next repair action suggested', result ? `${result.recommended_next_step?.path}: ${result.recommended_next_step?.reason}` : 'Re-born will suggest the next action after diagnosis.', result ? 'done' : ''],
    ['6', 'Repair paths ranked', decisionResult ? `Recommended: ${decisionResult.recommended_path}` : 'Run the Decision Engine to rank repair options.', decisionResult ? 'done' : '']
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
      <section class="section grid two">${recognitionResultPanel()}${repairPathDecisionPanel()}</section>
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
      ${repairPathDecisionPanel()}
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
  const decision = activeRepairPathDecision();
  const decisionResult = decision?.result_json;
  return layout('Repair paths', html`
    <section class="section-head"><div><p class="eyebrow">Decision Engine v1</p><h2>Choose the best way to make it work again.</h2></div><p class="muted">Re-born ranks options by feasibility, price, ETA, trust and learning value. It is ranking a repair journey, not a file catalogue.</p></section>
    ${decisionResult ? `<section class="panel stack"><div class="section-head"><div><p class="eyebrow">Latest decision</p><h3>Recommended: ${safe(decisionResult.recommended_path)}</h3></div><span class="badge green">${safe(String((decisionResult.ranked_paths || []).length))} paths ranked</span></div><p class="muted">Decision ${safe(String(decision.id || '').slice(0, 8))} was generated from ${safe(decision.recognition_job_id ? 'AI recognition evidence' : 'repair case intake evidence')}.</p></section>` : `<section class="panel stack"><h3>Generate ranked repair paths</h3><p class="muted">Run the Step 12 Decision Engine after AI recognition to create persisted repair paths for the active case.</p><div class="actions"><button class="btn green" onclick="runRepairPathDecision()" ${S.busy || !activeRecognitionJob() ? 'disabled' : ''}>Generate repair paths</button><a class="btn secondary" href="#/capture">Back to evidence</a></div></section>`}
    <section class="grid three">
      ${paths.map(path => `<article class="card interactive ${S.selectedPath === path.id ? 'selected' : ''}" onclick="REBORN_STATE.set('selectedPath', '${safe(path.id)}'); toast('${safe(path.title)} selected.'); render();"><div class="section-head"><h3>${safe(path.title)}</h3><span class="badge ${path.id === 'find_provider' || path.id === 'print' || path.id === 'provider_assisted_repair' ? 'green' : path.id === 'generate_part' || path.id === 'ai' || path.id === 'ai_generated_cad' ? 'orange' : 'blue'}">Score ${safe(path.score)}</span></div><p class="muted">${safe(path.recommendation)}</p><table class="table"><tr><th>Cost</th><td>${safe(path.cost)}</td></tr><tr><th>ETA</th><td>${safe(path.eta)}</td></tr><tr><th>Impact</th><td>${safe(path.impact)}</td></tr></table></article>`).join('')}
    </section>
    <section class="section panel stack"><h3>Recommended plan</h3><p class="muted">The MVP path should move from evidence to validation to fulfilment. Existing parts are preferred when verified; AI generation and maker work remain repair fallbacks with explicit validation.</p><div class="actions"><button class="btn green" onclick="runProviderMatch()" ${S.busy || !S.api.repairCase ? 'disabled' : ''}>Match providers</button><a class="btn secondary" href="#/part-detail">Continue with repair model</a><a class="btn secondary" href="#/ai-generation">Generate with AI instead</a><button class="btn secondary" onclick="runRepairPathDecision()" ${S.busy || !activeRecognitionJob() ? 'disabled' : ''}>Re-run decision</button></div></section>
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

function providerMatchPanel() {
  const match = activeProviderMatch();
  const quote = activeQuoteRequest();
  const context = match?.result_json?.repair_context;
  return html`<section class="grid two">
    <div class="panel stack">
      <p class="eyebrow">Step 13 · Provider Match Engine</p>
      <h2>Route the repair to the best fulfilment provider.</h2>
      <p class="muted">Matching uses the active repair case, Step 12 decision and provider capabilities. It is not a generic print-service search.</p>
      ${context ? `<div class="notice"><strong>Repair context</strong><span>${safe(context.selected_path_title || context.recommended_path)} · ${formatEuro(Number(context.estimated_price_cents || 0))} · ${safe(String(context.estimated_days || '?'))} days baseline</span></div>` : '<p class="muted small">Run provider matching after repair paths have been ranked.</p>'}
      <div class="actions"><button class="btn green" onclick="runProviderMatch()" ${S.busy || !S.api.repairCase ? 'disabled' : ''}>Match providers</button><a class="btn secondary" href="#/repair-paths">Back to repair paths</a></div>
    </div>
    <aside class="panel dark-panel stack">
      <h3>Quote Engine v1</h3>
      ${quote ? `<div class="price">${formatEuro(Number(quote.quote_json?.total_cents || 0))}</div><p class="muted">${safe(quote.quote_json?.provider?.name || quote.provider_id)} · ${safe(String(quote.quote_json?.estimated_days || '?'))} days · expires ${safe(new Date(quote.expires_at).toLocaleDateString('it-IT'))}</p>${badges([[quote.status, 'green'], ['platform fee included', 'blue'], ['validation required', 'orange']])}` : '<p class="muted">Select a matched provider and request a quote to create a persistent quote estimate.</p>'}
    </aside>
  </section>`;
}

function providerNetwork() {
  setActiveNav('provider-network');
  const providers = getActiveProviders();
  const match = activeProviderMatch();
  return layout('Providers', html`
    <section class="section-head"><div><p class="eyebrow">Provider Match & Quote Engine v1</p><h2>Local providers ranked by trust and repair fit.</h2></div><p class="muted">Professional services and independent makers can both compete when quality, constraints and trust are explicit.</p></section>
    ${providerMatchPanel()}
    <section class="stack section">
      ${providers.map(p => `<article class="card provider-card interactive ${S.selectedProvider === p.name ? 'selected' : ''}" onclick="REBORN_STATE.set('selectedProvider', '${safe(p.name)}'); toast('${safe(p.name)} selected.'); render();"><div class="stack"><div><h3>${safe(p.name)}</h3><p class="muted">${safe(p.type)} · ${safe(p.distance)} · ${safe(p.jobs)} · ${safe(p.matchScore ? 'match score' : 'provider record')}</p></div>${badges([[`Rating ${p.rating}`, 'green'], [`Trust ${p.trust}`, 'blue'], [p.material, 'orange'], [p.eta, '']])}</div><div><div class="price">${safe(p.price)}</div><p class="muted small">estimated total</p><div class="actions"><button class="btn green" onclick="event.stopPropagation(); requestProviderQuote('${safe(p.providerId || p.id || p.name)}')" ${S.busy || !match ? 'disabled' : ''}>Request quote</button></div></div></article>`).join('')}
    </section>
    <section class="section panel stack"><h3>Provider agreement preview</h3><p class="muted">Re-born collects a platform fee on every fulfilled repair. Provider receives clear model constraints, quality checks and delivery expectations before accepting.</p><div class="actions"><a class="btn green" href="#/checkout">Continue to repair order</a><a class="btn secondary" href="#/provider">Open provider view</a><button class="btn secondary" onclick="runProviderMatch()" ${S.busy || !S.api.repairCase ? 'disabled' : ''}>Re-run match</button></div></section>
  `, { currentStep: 'repair-paths' });
}

function checkout() {
  const p = getActiveProduct();
  const quote = activeQuoteRequest();
  const order = activeRepairOrder();
  const intent = activePaymentIntent();
  const quoteJson = quote?.quote_json || {};
  const orderJson = order?.order_json || {};
  const providerName = orderJson.provider?.name || quoteJson.provider?.name || quote?.provider_id || S.selectedProvider;
  const total = Number(order?.total_cents || quoteJson.total_cents || 0);
  const platformFee = Number(order?.platform_fee_cents || quoteJson.platform_fee_cents || 0);
  const payout = Number(order?.provider_payout_cents || quoteJson.provider_payout_cents || 0);
  const paymentStatus = intent ? intent.status : 'not_created';
  const fulfilment = activeFulfilment();

  return layout('Checkout', html`
    <section class="section-head"><div><p class="eyebrow">Step 14 → Step 15</p><h2>Confirm the repair, then start fulfilment.</h2></div><p class="muted">A repair order is created from a validated quote. Payment intent is mock-only: no real money movement happens in this MVP.</p></section>
    <section class="grid two">
      <div class="panel stack">
        <p class="eyebrow">Repair order</p>
        <h2>${order ? `Order ${safe(String(order.id).slice(0, 8))}` : 'Create an order from the quote'}</h2>
        <table class="table"><tr><th>Object</th><td>${safe(p.detectedName)}</td></tr><tr><th>Provider</th><td>${safe(providerName)}</td></tr><tr><th>Quote status</th><td>${safe(quote?.status || 'missing')}</td></tr><tr><th>Order status</th><td>${safe(order?.status || 'not_created')}</td></tr><tr><th>Total</th><td>${formatEuro(total)}</td></tr><tr><th>Platform fee</th><td>${formatEuro(platformFee)}</td></tr><tr><th>Provider payout</th><td>${formatEuro(payout)}</td></tr></table>
        <div class="actions"><button class="btn green" onclick="createRepairOrder()" ${S.busy || !quote || order ? 'disabled' : ''}>Create repair order</button><button class="btn orange" onclick="createPaymentIntent()" ${S.busy || !order || intent ? 'disabled' : ''}>Create payment intent</button><button class="btn green" onclick="confirmMockPaymentIntent()" ${S.busy || !intent || intent.status !== 'requires_mock_confirmation' ? 'disabled' : ''}>Mock authorize</button><button class="btn green" onclick="createRepairFulfilment()" ${S.busy || !order || !intent || intent.status !== 'mock_authorized' || fulfilment ? 'disabled' : ''}>Start fulfilment</button><a class="btn secondary" href="#/fulfilment">Fulfilment</a><a class="btn secondary" href="#/provider-network">Back</a></div>
      </div>
      <aside class="panel dark-panel stack"><h3>Payment intent</h3>${intent ? `<div class="price">${formatEuro(Number(intent.amount_cents || 0))}</div><p class="muted">${safe(intent.provider)} · ${safe(paymentStatus)} · expires ${safe(new Date(intent.expires_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }))}</p>${badges([[intent.status, intent.status === 'mock_authorized' ? 'green' : 'orange'], ['mock only', 'blue'], ['no real charge', '']])}<p class="muted small">Client secret: <code>${safe(String(intent.client_secret || '').slice(0, 18))}…</code></p>` : '<p class="muted">Create a payment intent after the repair order. This prepares checkout and audit trail without connecting a payment provider yet.</p>'}<div class="grid two">${metric(D.wallet.credits, 'Repair Credits')}${metric(D.wallet.savedObjects, 'Objects saved')}${metric(D.wallet.co2, 'CO₂ avoided')}${metric(formatEuro(platformFee), 'Platform fee')}</div><p class="muted">After completion, the repair updates provider trust, maker royalty, model reliability and Knowledge Graph confidence.</p></aside>
    </section>
    <section class="section panel stack"><h3>Order quality gates</h3><div class="timeline"><div class="timeline-row"><div class="timeline-time">1</div><div class="timeline-content"><strong>Quote estimated</strong><p class="muted small">Provider reviewed repair fit and platform fee is explicit.</p></div></div><div class="timeline-row"><div class="timeline-time">2</div><div class="timeline-content"><strong>Repair order created</strong><p class="muted small">The quote becomes a fulfilment contract for a real repair outcome.</p></div></div><div class="timeline-row"><div class="timeline-time">3</div><div class="timeline-content"><strong>Payment intent prepared</strong><p class="muted small">Mock checkout is auditable; real Stripe/PayPal adapter can be added later.</p></div></div><div class="timeline-row"><div class="timeline-time">4</div><div class="timeline-content"><strong>Fulfilment requested</strong><p class="muted small">Provider receives an operational workflow to accept, execute and close.</p></div></div><div class="timeline-row"><div class="timeline-time">5</div><div class="timeline-content"><strong>Repair outcome confirmed</strong><p class="muted small">Order closes only when the object returns to function.</p></div></div></div></section>
  `, { currentStep: 'checkout' });
}

function fulfilment() {
  const order = activeRepairOrder();
  const intent = activePaymentIntent();
  const fulfilment = activeFulfilment();
  const providerCanOperate = ['provider', 'admin'].includes(S.auth.user?.role || '');
  const timeline = fulfilment ? fulfilmentTimeline(fulfilment) : '<p class="muted">Create fulfilment after mock payment authorization. The provider then accepts and updates execution status.</p>';
  return layout('Fulfilment', html`
    <section class="section-head"><div><p class="eyebrow">Step 15 · Repair Fulfilment Workflow</p><h2>The repair now becomes operational work.</h2></div><p class="muted">Provider acceptance and status updates make fulfilment auditable without turning Re-born into a generic print marketplace.</p></section>
    <section class="grid two">
      <div class="panel stack"><h3>Fulfilment state</h3><table class="table"><tr><th>Order</th><td>${safe(order?.id ? String(order.id).slice(0, 8) : 'missing')}</td></tr><tr><th>Payment</th><td>${safe(intent?.status || 'not_authorized')}</td></tr><tr><th>Provider</th><td>${safe(fulfilment?.provider_id || order?.provider_id || 'pending')}</td></tr><tr><th>Status</th><td>${safe(fulfilment?.status || 'not_created')}</td></tr><tr><th>Accepted by</th><td>${safe(fulfilment?.accepted_by || 'pending')}</td></tr></table><div class="actions"><button class="btn green" onclick="createRepairFulfilment()" ${S.busy || !order || !intent || intent.status !== 'mock_authorized' || fulfilment ? 'disabled' : ''}>Create fulfilment</button><button class="btn orange" onclick="acceptProviderFulfilment()" ${S.busy || !fulfilment || !providerCanOperate || fulfilment.status !== 'awaiting_provider_acceptance' ? 'disabled' : ''}>Provider accept</button><button class="btn secondary" onclick="updateFulfilmentStatus('in_progress')" ${S.busy || !fulfilment || !providerCanOperate || fulfilment.status === 'awaiting_provider_acceptance' ? 'disabled' : ''}>Start work</button><button class="btn secondary" onclick="updateFulfilmentStatus('quality_check')" ${S.busy || !fulfilment || !providerCanOperate ? 'disabled' : ''}>Quality check</button><button class="btn green" onclick="updateFulfilmentStatus('completed')" ${S.busy || !fulfilment || !providerCanOperate ? 'disabled' : ''}>Complete</button><a class="btn secondary" href="#/learning">Learning</a></div><p class="muted small">Provider controls operational updates; repair user keeps visibility of the timeline.</p></div>
      <aside class="panel dark-panel stack"><h3>Repair outcome contract</h3>${badges([[fulfilment?.status || 'not_created', fulfilment?.status === 'completed' ? 'green' : 'orange'], ['provider acceptance', 'blue'], ['real object repair', 'green']])}<p class="muted">The order is not “done” because a file was delivered. It is done when the object is functionally repaired and the outcome is captured as learning data.</p></aside>
    </section>
    <section class="section panel stack"><h3>Fulfilment timeline</h3>${timeline}</section>
  `, { currentStep: 'fulfilment' });
}


function learning() {
  setActiveNav('learning');
  const fulfilment = activeFulfilment();
  const report = activeCompletionReport();
  const learningEvent = activeLearningEvent();
  const canReport = ['provider', 'admin'].includes(S.auth.user?.role || '');
  const canCreate = fulfilment && fulfilment.status === 'completed' && canReport && !report;
  const feedback = report ? [
    ['completion recorded', 'green'],
    [report.functional_result || 'object_returned_to_function', 'blue'],
    [report.object_saved ? 'object saved' : 'review needed', report.object_saved ? 'green' : 'orange']
  ] : [['waiting outcome', 'orange'], ['learning gate', 'blue']];

  const learningRows = activeLearningEvents().length ? activeLearningEvents().map(event => `<div class="timeline-row"><div class="timeline-time">${safe(String(event.confidence_delta || 0))}</div><div class="timeline-content"><strong>${safe(String(event.event_type || '').replaceAll('_', ' '))}</strong><p class="muted small">Report ${safe(String(event.completion_report_id || '').slice(0, 8))} · case ${safe(String(event.repair_case_id || '').slice(0, 8))}</p><p class="muted small">${safe(event.created_at ? new Date(event.created_at).toLocaleString('it-IT') : '')}</p></div></div>`).join('') : '<p class="muted small">No learning events recorded yet.</p>';

  return layout('Learning', html`
    <section class="section-head"><div><p class="eyebrow">Step 16 · Completion Learning</p><h2>Close the loop: repair outcome becomes reusable intelligence.</h2></div><p class="muted">Re-born learns from completed repairs only after a provider confirms the object returned to function. This strengthens the Knowledge Graph without becoming an STL marketplace.</p></section>
    <section class="grid two">
      <div class="panel stack"><h3>Completion report</h3><table class="table"><tr><th>Fulfilment</th><td>${safe(fulfilment?.id ? String(fulfilment.id).slice(0, 8) : 'missing')}</td></tr><tr><th>Fulfilment status</th><td>${safe(fulfilment?.status || 'not_completed')}</td></tr><tr><th>Report</th><td>${safe(report?.status || 'not_recorded')}</td></tr><tr><th>Outcome</th><td>${safe(report?.outcome_status || 'pending')}</td></tr><tr><th>CO₂ avoided</th><td>${safe(report ? `${report.co2_avoided_grams || 0} g` : 'pending')}</td></tr></table><div class="actions"><button class="btn green" onclick="recordCompletionLearning()" ${S.busy || !canCreate ? 'disabled' : ''}>Record completion learning</button><a class="btn secondary" href="#/trust">Trust score</a><a class="btn secondary" href="#/fulfilment">Back to fulfilment</a><button class="btn secondary" onclick="refreshApiData()" ${S.busy ? 'disabled' : ''}>Refresh</button></div><p class="muted small">Provider/admin records outcome. Repair user can view the learning once it exists.</p></div>
      <aside class="panel dark-panel stack"><h3>Knowledge feedback</h3>${badges(feedback)}${report ? `<p class="muted">${safe(report.outcome_json?.summary || 'Repair outcome recorded.')}</p><div class="grid two">${metric(report.customer_confirmed ? 'Yes' : 'No', 'Customer confirmed')}${metric(String(report.co2_avoided_grams || 0), 'CO₂ grams avoided')}${metric(learningEvent?.event_type || 'learning event', 'Learning signal')}${metric(String(learningEvent?.confidence_delta || 0), 'Confidence delta')}</div>` : '<p class="muted">Complete the fulfilment, then record the outcome to create a learning event and Knowledge Graph node.</p>'}</aside>
    </section>
    <section class="section panel stack"><h3>Learning events</h3><div class="timeline">${learningRows}</div></section>
    <section class="section panel stack"><h3>Repair Intelligence loop</h3><div class="timeline"><div class="timeline-row"><div class="timeline-time">1</div><div class="timeline-content"><strong>Outcome confirmed</strong><p class="muted small">Provider reports whether the object returned to function.</p></div></div><div class="timeline-row"><div class="timeline-time">2</div><div class="timeline-content"><strong>Learning event recorded</strong><p class="muted small">A structured signal captures method, result, object saved and quality checks.</p></div></div><div class="timeline-row"><div class="timeline-time">3</div><div class="timeline-content"><strong>Knowledge Graph feedback applied</strong><p class="muted small">Re-born creates repair outcome knowledge for future diagnosis, decisions and provider matching.</p></div></div></div></section>
  `, { currentStep: 'learning' });
}

function trust() {
  setActiveNav('trust');
  const report = activeCompletionReport();
  const review = activeTrustReview();
  const score = activeProviderQualityScore();
  const signals = activeProviderTrustSignals();
  const canReview = ['repair_user', 'enterprise', 'admin'].includes(S.auth.user?.role || '');
  const canCreate = report && canReview && !review;
  const scoreBadges = score ? [[score.trust_tier || 'unrated', score.overall_score >= 75 ? 'green' : 'orange'], [`${score.overall_score || 0}/100`, 'blue'], [`${score.review_count || 0} reviews`, '']] : [['unrated', 'orange'], ['waiting review', 'blue']];
  const signalRows = signals.length ? signals.map((signal, index) => `<div class="timeline-row"><div class="timeline-time">${index + 1}</div><div class="timeline-content"><strong>${safe(String(signal.event_type || '').replaceAll('_', ' '))}</strong><p class="muted small">Delta ${safe(String(signal.score_delta || 0))} · provider ${safe(String(signal.provider_id || '').slice(0, 12))}</p><p class="muted small">${safe(signal.created_at ? new Date(signal.created_at).toLocaleString('it-IT') : '')}</p></div></div>`).join('') : '<p class="muted small">No provider trust signals recorded yet.</p>';

  return layout('Trust', html`
    <section class="section-head"><div><p class="eyebrow">Step 17 · Trust & Provider Quality</p><h2>Provider reputation is earned from completed repairs.</h2></div><p class="muted">Trust is not a generic star rating. It combines completion reports, customer confirmation, object saved signals, quality, communication and timeliness.</p></section>
    <section class="grid two">
      <div class="panel stack"><h3>Trust review</h3><table class="table"><tr><th>Completion report</th><td>${safe(report?.id ? String(report.id).slice(0, 8) : 'missing')}</td></tr><tr><th>Provider</th><td>${safe(report?.provider_id || review?.provider_id || 'pending')}</td></tr><tr><th>Review</th><td>${safe(review?.status || 'not_recorded')}</td></tr><tr><th>Overall rating</th><td>${safe(review ? `${review.rating_overall}/5` : 'pending')}</td></tr><tr><th>Issue resolved</th><td>${safe(review ? (review.issue_resolved ? 'yes' : 'no') : 'pending')}</td></tr></table><div class="actions"><button class="btn green" onclick="recordProviderTrustReview()" ${S.busy || !canCreate ? 'disabled' : ''}>Record trust review</button><button class="btn secondary" onclick="refreshApiData()" ${S.busy ? 'disabled' : ''}>Refresh</button><a class="btn secondary" href="#/learning">Back to learning</a></div><p class="muted small">Repair user/admin submits the review only after the provider records completion learning.</p></div>
      <aside class="panel dark-panel stack"><h3>Provider quality score</h3>${badges(scoreBadges)}${score ? `<div class="grid two">${metric(String(score.quality_score || 0), 'Quality')}${metric(String(score.reliability_score || 0), 'Reliability')}${metric(String(score.communication_score || 0), 'Communication')}${metric(String(score.timeliness_score || 0), 'Timeliness')}</div><p class="muted small">${safe(score.score_json?.interpretation || 'Trust formula v1 score calculated from completed repair signals.')}</p>` : '<p class="muted">Create a trust review after completion learning to score this provider.</p>'}</aside>
    </section>
    <section class="section panel stack"><h3>Trust signals</h3><div class="timeline">${signalRows}</div></section>
    <section class="section panel stack"><h3>Why this matters</h3><p class="muted">Future provider matching can weight not only proximity and capability, but real repair outcomes. This is a core Re-born asset: trust derived from objects saved.</p></section>
  `, { currentStep: 'trust' });
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
  '/fulfilment': fulfilment,
  '/learning': learning,
  '/trust': trust,
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
      repairPathDecisions: bootstrap.repair_path_decisions || [],
      repairPathDecision: (bootstrap.repair_path_decisions || [])[0] || S.api.repairPathDecision,
      providerMatches: bootstrap.provider_matches || [],
      providerMatch: (bootstrap.provider_matches || [])[0] || S.api.providerMatch,
      quoteRequests: bootstrap.quote_requests || [],
      quoteRequest: (bootstrap.quote_requests || [])[0] || S.api.quoteRequest,
      repairOrders: bootstrap.repair_orders || [],
      repairOrder: (bootstrap.repair_orders || [])[0] || S.api.repairOrder,
      paymentIntents: bootstrap.payment_intents || [],
      paymentIntent: (bootstrap.payment_intents || [])[0] || S.api.paymentIntent,
      fulfilments: bootstrap.fulfilments || [],
      fulfilment: (bootstrap.fulfilments || [])[0] || S.api.fulfilment,
      completionReports: bootstrap.completion_reports || [],
      completionReport: (bootstrap.completion_reports || [])[0] || S.api.completionReport,
      learningEvents: bootstrap.learning_events || [],
      learningEvent: (bootstrap.learning_events || [])[0] || S.api.learningEvent,
      trustReviews: bootstrap.trust_reviews || [],
      trustReview: (bootstrap.trust_reviews || [])[0] || S.api.trustReview,
      providerQualityScores: bootstrap.provider_quality_scores || [],
      providerQualityScore: bootstrap.provider_quality_score || (bootstrap.provider_quality_scores || [])[0] || S.api.providerQualityScore,
      providerTrustSignals: bootstrap.provider_trust_signals || [],
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
    S.setApi({ repairCase, repairCases: [repairCase, ...S.api.repairCases], repairPaths: [], repairAttachments: [], recognitionJobs: [], recognitionJob: null, repairPathDecisions: [], repairPathDecision: null, providerMatches: [], providerMatch: null, quoteRequests: [], quoteRequest: null, repairOrders: [], repairOrder: null, paymentIntents: [], paymentIntent: null, fulfilments: [], fulfilment: null, completionReports: [], completionReport: null, learningEvents: [], learningEvent: null, diagnosis: null, lastSyncAt: new Date().toISOString() });
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
      repairPathDecision: null,
      repairPathDecisions: [],
      repairPaths: [],
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


async function runRepairPathDecision() {
  if (S.api.status !== 'live') {
    runMockRepairPathDecision();
    return;
  }

  const repairCase = S.api.repairCase;
  const recognitionJob = activeRecognitionJob();
  if (!repairCase || !recognitionJob) {
    toast('Run AI recognition before generating repair paths.');
    location.hash = '#/capture';
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.requestRepairPathDecision(repairCase.id, recognitionJob.id);
    const decisions = await window.REBORN_API.getRepairPathDecisions(repairCase.id).catch(() => ({ repair_path_decisions: [payload.decision] }));
    const paths = await window.REBORN_API.listRepairPaths(repairCase.id).catch(() => ({ repair_paths: payload.repair_paths || [] }));
    S.setApi({
      repairPathDecision: payload.decision,
      repairPathDecisions: decisions.repair_path_decisions || [payload.decision],
      repairPaths: paths.repair_paths || payload.repair_paths || [],
      providerMatches: [],
      providerMatch: null,
      quoteRequests: [],
      quoteRequest: null,
      message: 'Repair Path Decision Engine ranked concrete repair paths.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Repair paths ranked.');
    location.hash = '#/repair-paths';
  } catch (error) {
    S.setApi({ status: 'error', message: `Repair path decision failed: ${error.message}`, lastError: error.message });
    toast(`Decision failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

function runMockRepairPathDecision() {
  const result = mockRepairPathDecisionResult();
  const decision = {
    id: 'mock-path-decision',
    repair_case_id: S.api.repairCase?.id || 'mock-case',
    recognition_job_id: activeRecognitionJob()?.id || 'mock-recognition-job',
    requested_by: S.auth.user?.id || 'mock-user',
    status: 'completed',
    result_json: result,
    created_at: new Date().toISOString(),
    completed_at: new Date().toISOString()
  };
  const paths = result.ranked_paths.map(path => ({
    id: path.type,
    repair_case_id: decision.repair_case_id,
    type: path.type,
    title: path.title,
    description: path.description,
    confidence_score: path.score,
    estimated_price_cents: path.estimated_price_cents,
    estimated_days: path.estimated_days,
    created_at: new Date().toISOString()
  }));
  S.setApi({ repairPathDecision: decision, repairPathDecisions: [decision], repairPaths: paths });
  toast('Mock repair paths ranked.');
  location.hash = '#/repair-paths';
  render();
}

async function runProviderMatch() {
  if (S.api.status !== 'live') {
    runMockProviderMatch();
    return;
  }

  const repairCase = S.api.repairCase;
  if (!repairCase) {
    toast('Create a repair case before matching providers.');
    location.hash = '#/start';
    return;
  }

  setBusy(true);
  try {
    const decision = activeRepairPathDecision();
    const payload = await window.REBORN_API.requestProviderMatch(repairCase.id, decision?.id || null);
    const matches = await window.REBORN_API.getProviderMatches(repairCase.id).catch(() => ({ provider_matches: [payload.provider_match] }));
    S.setApi({
      providerMatch: payload.provider_match,
      providerMatches: matches.provider_matches || [payload.provider_match],
      quoteRequests: [],
      quoteRequest: null,
      repairOrders: [],
      repairOrder: null,
      paymentIntents: [],
      paymentIntent: null,
      message: 'Provider Match Engine ranked fulfilment options for the repair.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Providers matched.');
    location.hash = '#/provider-network';
  } catch (error) {
    S.setApi({ status: 'error', message: `Provider matching failed: ${error.message}`, lastError: error.message });
    toast(`Provider matching failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

function runMockProviderMatch() {
  const result = mockProviderMatchResult();
  const match = {
    id: 'mock-provider-match',
    repair_case_id: S.api.repairCase?.id || 'mock-case',
    repair_path_decision_id: activeRepairPathDecision()?.id || 'mock-path-decision',
    requested_by: S.auth.user?.id || 'mock-user',
    status: 'completed',
    result_json: result,
    created_at: new Date().toISOString(),
    completed_at: new Date().toISOString()
  };
  S.setApi({ providerMatch: match, providerMatches: [match] });
  toast('Mock providers matched.');
  location.hash = '#/provider-network';
  render();
}

async function requestProviderQuote(providerId) {
  if (S.api.status !== 'live') {
    runMockProviderQuote(providerId);
    return;
  }

  const repairCase = S.api.repairCase;
  const match = activeProviderMatch();
  if (!repairCase || !match) {
    toast('Run provider matching before requesting a quote.');
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.requestProviderQuote(match.id, providerId);
    const quotes = await window.REBORN_API.getQuoteRequests(repairCase.id).catch(() => ({ quote_requests: [payload.quote_request] }));
    S.setApi({
      quoteRequest: payload.quote_request,
      quoteRequests: quotes.quote_requests || [payload.quote_request],
      message: 'Quote Engine created a preliminary repair quote.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Repair quote estimated.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Quote request failed: ${error.message}`, lastError: error.message });
    toast(`Quote failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

function runMockProviderQuote(providerId) {
  const quote = mockQuoteRequest(providerId);
  S.setApi({ quoteRequest: quote, quoteRequests: [quote] });
  toast('Mock quote estimated.');
  render();
}

async function createRepairOrder() {
  if (S.api.status !== 'live') {
    runMockRepairOrder();
    return;
  }

  const quote = activeQuoteRequest();
  if (!quote) {
    toast('Request a quote before creating a repair order.');
    location.hash = '#/provider-network';
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.createRepairOrder(quote.id);
    const repairCase = S.api.repairCase;
    const orders = repairCase ? await window.REBORN_API.getRepairOrders(repairCase.id).catch(() => ({ repair_orders: [payload.repair_order] })) : { repair_orders: [payload.repair_order] };
    S.setApi({
      repairOrder: payload.repair_order,
      repairOrders: orders.repair_orders || [payload.repair_order],
      paymentIntent: null,
      paymentIntents: [],
      message: 'Repair Order Engine created an order from the quote.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Repair order created.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Repair order failed: ${error.message}`, lastError: error.message });
    toast(`Order failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

function runMockRepairOrder() {
  const order = mockRepairOrder();
  S.setApi({ repairOrder: order, repairOrders: [order], paymentIntent: null, paymentIntents: [] });
  toast('Mock repair order created.');
  render();
}

async function createPaymentIntent() {
  if (S.api.status !== 'live') {
    runMockPaymentIntent();
    return;
  }

  const order = activeRepairOrder();
  if (!order) {
    toast('Create a repair order before preparing payment.');
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.createPaymentIntent(order.id);
    const intents = await window.REBORN_API.getPaymentIntents(order.id).catch(() => ({ payment_intents: [payload.payment_intent] }));
    S.setApi({
      paymentIntent: payload.payment_intent,
      paymentIntents: intents.payment_intents || [payload.payment_intent],
      message: 'Mock payment intent created. No real money movement occurred.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Payment intent created.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Payment intent failed: ${error.message}`, lastError: error.message });
    toast(`Payment intent failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

function runMockPaymentIntent() {
  const intent = mockPaymentIntent();
  S.setApi({ paymentIntent: intent, paymentIntents: [intent] });
  toast('Mock payment intent created.');
  render();
}

async function confirmMockPaymentIntent() {
  if (S.api.status !== 'live') {
    const intent = activePaymentIntent() || mockPaymentIntent();
    const updated = { ...intent, status: 'mock_authorized', confirmed_at: new Date().toISOString() };
    S.setApi({ paymentIntent: updated, paymentIntents: [updated] });
    toast('Mock payment authorized.');
    render();
    return;
  }

  const intent = activePaymentIntent();
  if (!intent) {
    toast('Create a payment intent first.');
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.confirmMockPaymentIntent(intent.id);
    S.setApi({
      paymentIntent: payload.payment_intent,
      paymentIntents: [payload.payment_intent, ...activePaymentIntents().filter(item => item.id !== payload.payment_intent.id)],
      message: 'Mock payment intent authorized. No real charge occurred.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Mock payment authorized.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Mock payment confirmation failed: ${error.message}`, lastError: error.message });
    toast(`Mock authorization failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

async function createRepairFulfilment() {
  if (S.api.status !== 'live') {
    const fulfilment = mockFulfilment();
    S.setApi({ fulfilment, fulfilments: [fulfilment] });
    toast('Mock fulfilment created.');
    location.hash = '#/fulfilment';
    render();
    return;
  }

  const order = activeRepairOrder();
  if (!order) {
    toast('Create a repair order first.');
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.createRepairFulfilment(order.id);
    const list = await window.REBORN_API.getRepairFulfilments(order.id).catch(() => ({ fulfilments: [payload.fulfilment] }));
    S.setApi({
      fulfilment: payload.fulfilment,
      fulfilments: list.fulfilments || [payload.fulfilment],
      message: 'Repair fulfilment workflow created and awaiting provider acceptance.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Fulfilment created.');
    location.hash = '#/fulfilment';
  } catch (error) {
    S.setApi({ status: 'error', message: `Fulfilment creation failed: ${error.message}`, lastError: error.message });
    toast(`Fulfilment failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

async function acceptProviderFulfilment() {
  const fulfilment = activeFulfilment();
  if (!fulfilment) {
    toast('Create fulfilment first.');
    return;
  }

  if (S.api.status !== 'live') {
    const updated = { ...fulfilment, status: 'accepted', accepted_by: S.auth.user?.id || 'mock-provider', accepted_at: new Date().toISOString(), timeline_json: [...(fulfilment.timeline_json || []), { event: 'provider_accepted', status: 'accepted', actor_id: S.auth.user?.id || 'mock-provider', note: 'Mock provider accepted fulfilment.', occurred_at: new Date().toISOString() }] };
    S.setApi({ fulfilment: updated, fulfilments: [updated] });
    toast('Provider accepted fulfilment.');
    render();
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.acceptProviderFulfilment(fulfilment.id, 'Provider accepts geometry validation, material checks and repair outcome responsibility.');
    S.setApi({ fulfilment: payload.fulfilment, fulfilments: [payload.fulfilment, ...activeFulfilments().filter(item => item.id !== payload.fulfilment.id)], message: 'Provider accepted the repair fulfilment workflow.', status: 'live', lastError: null, lastSyncAt: new Date().toISOString() });
    toast('Provider accepted.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Provider acceptance failed: ${error.message}`, lastError: error.message });
    toast(`Acceptance failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

async function updateFulfilmentStatus(status) {
  const fulfilment = activeFulfilment();
  if (!fulfilment) {
    toast('Create fulfilment first.');
    return;
  }

  if (S.api.status !== 'live') {
    const updated = { ...fulfilment, status, timeline_json: [...(fulfilment.timeline_json || []), { event: 'status_updated', status, actor_id: S.auth.user?.id || 'mock-provider', note: `Mock status set to ${status}.`, occurred_at: new Date().toISOString() }], updated_at: new Date().toISOString() };
    S.setApi({ fulfilment: updated, fulfilments: [updated] });
    toast(`Fulfilment ${status.replaceAll('_', ' ')}.`);
    render();
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.updateFulfilmentStatus(fulfilment.id, status, `Provider updated fulfilment to ${status}.`);
    S.setApi({ fulfilment: payload.fulfilment, fulfilments: [payload.fulfilment, ...activeFulfilments().filter(item => item.id !== payload.fulfilment.id)], message: `Fulfilment status updated to ${status}.`, status: 'live', lastError: null, lastSyncAt: new Date().toISOString() });
    toast(`Fulfilment ${status.replaceAll('_', ' ')}.`);
  } catch (error) {
    S.setApi({ status: 'error', message: `Fulfilment update failed: ${error.message}`, lastError: error.message });
    toast(`Update failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}


async function recordCompletionLearning() {
  const fulfilment = activeFulfilment();
  if (!fulfilment) {
    toast('Create fulfilment first.');
    return;
  }
  if (fulfilment.status !== 'completed') {
    toast('Complete fulfilment before recording learning.');
    return;
  }

  const data = {
    outcome_status: 'successful',
    functional_result: 'object_returned_to_function',
    customer_confirmed: true,
    object_saved: true,
    co2_avoided_grams: 1350,
    summary: 'The repaired object returned to function after provider validation and final fit check.',
    repair_method: 'provider_validated_replacement_part',
    material_used: 'PETG',
    quality_checks: ['fit_checked', 'function_checked', 'visual_inspection'],
    notes: 'Prototype Step 16 completion feedback.',
    evidence_attachment_ids: []
  };

  if (S.api.status !== 'live') {
    const report = mockCompletionReport();
    const learningEvent = mockLearningEvent(report);
    S.setApi({ completionReport: report, completionReports: [report], learningEvent, learningEvents: [learningEvent] });
    toast('Mock completion learning recorded.');
    render();
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.createCompletionReport(fulfilment.id, data);
    const reports = await window.REBORN_API.getCompletionReports(fulfilment.id).catch(() => ({ completion_reports: [payload.completion_report] }));
    const events = await window.REBORN_API.getLearningEvents(payload.completion_report.repair_case_id).catch(() => ({ learning_events: [payload.learning_event] }));
    S.setApi({
      completionReport: payload.completion_report,
      completionReports: reports.completion_reports || [payload.completion_report],
      learningEvent: payload.learning_event,
      learningEvents: events.learning_events || [payload.learning_event],
      message: 'Repair completion converted into Learning Event and Knowledge Graph feedback.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Completion learning recorded.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Completion learning failed: ${error.message}`, lastError: error.message });
    toast(`Learning failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
}

async function recordProviderTrustReview() {
  const report = activeCompletionReport();
  if (!report) {
    toast('Record completion learning first.');
    return;
  }

  const data = {
    rating_overall: 5,
    rating_quality: 5,
    rating_communication: 4,
    rating_timeliness: 5,
    would_recommend: true,
    issue_resolved: true,
    comment: 'Repair outcome confirmed: object returned to function and provider quality was validated.'
  };

  if (S.api.status !== 'live') {
    const review = mockTrustReview(report);
    const qualityScore = mockProviderQualityScore(review);
    const signal = mockProviderTrustSignal(review);
    S.setApi({ trustReview: review, trustReviews: [review], providerQualityScore: qualityScore, providerQualityScores: [qualityScore], providerTrustSignals: [signal] });
    toast('Mock provider trust review recorded.');
    render();
    return;
  }

  setBusy(true);
  try {
    const payload = await window.REBORN_API.createTrustReview(report.id, data);
    const reviews = await window.REBORN_API.getTrustReviews(report.id).catch(() => ({ trust_reviews: [payload.trust_review] }));
    const score = await window.REBORN_API.getProviderQualityScore(payload.trust_review.provider_id).catch(() => ({ quality_score: payload.quality_score }));
    const signals = await window.REBORN_API.getProviderTrustSignals(payload.trust_review.provider_id).catch(() => ({ trust_signals: [payload.trust_signal] }));
    const allScores = await window.REBORN_API.getProviderQualityScores().catch(() => ({ quality_scores: [payload.quality_score] }));
    S.setApi({
      trustReview: payload.trust_review,
      trustReviews: reviews.trust_reviews || [payload.trust_review],
      providerQualityScore: score.quality_score || payload.quality_score,
      providerQualityScores: allScores.quality_scores || [payload.quality_score],
      providerTrustSignals: signals.trust_signals || [payload.trust_signal],
      message: 'Provider quality score updated from completion trust review.',
      status: 'live',
      lastError: null,
      lastSyncAt: new Date().toISOString()
    });
    toast('Provider trust review recorded.');
  } catch (error) {
    S.setApi({ status: 'error', message: `Trust review failed: ${error.message}`, lastError: error.message });
    toast(`Trust review failed: ${error.message}`);
  } finally {
    setBusy(false);
    render();
  }
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
    S.setApi({ dashboard: null, roleDashboards: {}, repairCases: [], repairCase: null, repairPaths: [], repairPathDecisions: [], repairPathDecision: null, providerMatches: [], providerMatch: null, quoteRequests: [], quoteRequest: null, repairOrders: [], repairOrder: null, paymentIntents: [], paymentIntent: null, fulfilments: [], fulfilment: null, completionReports: [], completionReport: null, learningEvents: [], learningEvent: null, trustReviews: [], trustReview: null, providerQualityScores: [], providerQualityScore: null, providerTrustSignals: [] });
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
window.runRepairPathDecision = runRepairPathDecision;
window.runMockRepairPathDecision = runMockRepairPathDecision;
window.runProviderMatch = runProviderMatch;
window.requestProviderQuote = requestProviderQuote;
window.createRepairOrder = createRepairOrder;
window.runMockRepairOrder = runMockRepairOrder;
window.createPaymentIntent = createPaymentIntent;
window.runMockPaymentIntent = runMockPaymentIntent;
window.confirmMockPaymentIntent = confirmMockPaymentIntent;
window.recordCompletionLearning = recordCompletionLearning;
window.recordProviderTrustReview = recordProviderTrustReview;
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
