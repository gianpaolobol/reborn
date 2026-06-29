const app = document.getElementById('app');
const nav = document.querySelector('.topnav');
const menuButton = document.getElementById('menuButton');

const D = window.REBORN_DATA;
const S = window.REBORN_STATE;

function html(strings, ...values) {
  return strings.map((s, i) => s + (values[i] ?? '')).join('');
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

function layout(title, body, opts = {}) {
  const current = opts.currentStep ?? null;
  return html`
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
  return `<div class="metric"><strong>${value}</strong><span>${label}</span></div>`;
}

function badges(items) {
  return `<div class="badges">${items.map(([t, c]) => `<span class="badge ${c || ''}">${t}</span>`).join('')}</div>`;
}

function home() {
  setActiveNav('home');
  return layout('Home', html`
    <section class="hero">
      <div class="hero-panel">
        <p class="eyebrow">Allow anyone to repair anything</p>
        <h1>Repair Intelligence Platform</h1>
        <p class="lead">Re-born does not ask users to search for STL files. It guides them from a broken object to the best repair path: existing spare, verified model, AI-generated component or local production.</p>
        <div class="actions">
          <a class="btn green" href="#/start">Start a repair</a>
          <a class="btn secondary" href="#/provider-network">Explore provider network</a>
        </div>
      </div>
      <div class="panel dark-panel stack">
        <div class="section-head">
          <div>
            <p class="eyebrow">MVP simulation</p>
            <h2>One journey. Four engines.</h2>
          </div>
        </div>
        <div class="grid two">
          ${metric('87%', 'Recognition confidence')}
          ${metric('3', 'Repair paths found')}
          ${metric('24h', 'Fastest local production')}
          ${metric('1', 'Knowledge Graph update')}
        </div>
        <div class="timeline">
          ${D.events.map(e => `<div class="timeline-row"><div class="timeline-time">${e[0]}</div><div class="timeline-content"><strong>${e[1]}</strong><p class="muted small">${e[2]}</p></div></div>`).join('')}
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
  `);
}

function start() {
  setActiveNav('start');
  return layout('Start repair', html`
    <section class="hero">
      <div class="panel stack">
        <p class="eyebrow">Repair intake</p>
        <h2>Tell Re-born what stopped working.</h2>
        <p class="muted">This is not an STL upload form. It is a repair request that can later produce CAD, quotes and instructions.</p>
        <div class="form-grid">
          <div class="field"><label>Object type</label><input value="Dishwasher basket wheel" /></div>
          <div class="field"><label>Brand / model if known</label><input value="Bosch Series 4" /></div>
          <div class="field"><label>What happened?</label><select><option>Small part broken or missing</option><option>Mechanical wear</option><option>Cracked plastic</option><option>I am not sure</option></select></div>
          <div class="field"><label>Repair urgency</label><select><option>Fast, within 48 hours</option><option>Lowest cost</option><option>Best quality</option><option>Lowest environmental impact</option></select></div>
        </div>
        <div class="field"><label>Description</label><textarea>The lower basket wheel is broken. The dishwasher still works but the basket no longer slides correctly.</textarea></div>
        <div class="actions"><a class="btn green" href="#/capture">Continue to photos</a><button class="btn ghost" onclick="toast('Draft repair request saved locally in prototype state.')">Save draft</button></div>
      </div>
      <aside class="panel dark-panel stack">
        <h3>What Re-born will do next</h3>
        <p class="muted">Recognition Engine will identify the object, Knowledge Engine will search existing repair DNA, Decision Engine will rank options, Learning Engine will update the graph after completion.</p>
        ${badges([['Recognition Engine', 'green'], ['Knowledge Graph', 'blue'], ['Repair DNA', 'orange']])}
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
          <div><div class="dropzone-icon">▣</div><h3>Drop photos here</h3><p class="muted">Front, side, broken area, scale reference, optional STEP/STL.</p></div>
        </div>
        <div class="form-grid">
          <div class="field"><label>Approx. width</label><input value="36 mm" /></div>
          <div class="field"><label>Approx. thickness</label><input value="12 mm" /></div>
        </div>
        <div class="actions"><a class="btn green" href="#/diagnosis">Run diagnosis</a><a class="btn secondary" href="#/start">Back</a></div>
      </div>
      <div class="prototype-frame">
        <div class="device-header"><div class="dots"><span></span><span></span><span></span></div><strong>AI capture preview</strong></div>
        <div class="frame-body">
          <div class="scan-visual"><div class="part-shape"></div><div class="scan-box"></div><div class="scan-line"></div></div>
        </div>
      </div>
    </section>
  `, { currentStep: 'capture' });
}

function diagnosis() {
  const p = D.product;
  return layout('Diagnosis', html`
    <section class="grid two">
      <div class="panel stack">
        <p class="eyebrow">Recognition result</p>
        <h2>${p.detectedName}</h2>
        <p class="lead">The part is likely repairable and has at least one verified local production path.</p>
        ${badges([[`${Math.round(p.confidence * 100)}% confidence`, 'green'], [p.status, 'blue'], [`Risk: ${p.risk}`, 'orange'], [p.repairDna, '']])}
        <table class="table">
          <tr><th>Category</th><td>${p.category}</td></tr>
          <tr><th>Material suggestion</th><td>${p.material}</td></tr>
          <tr><th>Estimated dimensions</th><td>${p.dimensions}</td></tr>
          <tr><th>Expected life</th><td>${p.estimatedLife}</td></tr>
        </table>
        <div class="actions"><a class="btn green" href="#/repair-paths">See repair paths</a><a class="btn secondary" href="#/capture">Improve photos</a></div>
      </div>
      <aside class="panel stack">
        <h3>Knowledge Graph match</h3>
        <p class="muted">Matched with prior repairs from similar dishwasher wheels. The graph suggests material and provider constraints before purchase.</p>
        <div class="grid two">
          ${metric('14', 'Similar repairs')}
          ${metric('1', 'Verified CAD')}
          ${metric('3', 'Provider quotes')}
          ${metric('Low', 'Safety risk')}
        </div>
      </aside>
    </section>
  `, { currentStep: 'diagnosis' });
}

function repairPaths() {
  return layout('Repair paths', html`
    <section class="section-head"><div><p class="eyebrow">Decision Engine</p><h2>Choose the best way to make it work again.</h2></div><p class="muted">Re-born ranks options by feasibility, price, ETA, trust and learning value.</p></section>
    <section class="grid three">
      ${D.repairPaths.map(path => `<article class="card interactive ${S.selectedPath === path.id ? 'selected' : ''}" onclick="REBORN_STATE.set('selectedPath', '${path.id}'); toast('${path.title} selected.'); render();">
        <div class="section-head"><h3>${path.title}</h3><span class="badge ${path.id === 'print' ? 'green' : path.id === 'ai' ? 'orange' : 'blue'}">Score ${path.score}</span></div>
        <p class="muted">${path.recommendation}</p>
        <table class="table"><tr><th>Cost</th><td>${path.cost}</td></tr><tr><th>ETA</th><td>${path.eta}</td></tr><tr><th>Impact</th><td>${path.impact}</td></tr></table>
      </article>`).join('')}
    </section>
    <section class="section panel stack">
      <h3>Recommended plan</h3>
      <p class="muted">For the MVP journey, local production with a verified model creates marketplace liquidity, validates provider fulfilment and updates the Knowledge Graph after completion.</p>
      <div class="actions"><a class="btn green" href="#/part-detail">Continue with verified repair model</a><a class="btn secondary" href="#/ai-generation">Generate with AI instead</a></div>
    </section>
  `, { currentStep: 'repair-paths' });
}

function partDetail() {
  return layout('Part detail', html`
    <section class="grid two">
      <div class="panel stack">
        <p class="eyebrow">Verified repair model</p>
        <h2>Lower Basket Wheel v3</h2>
        <p class="lead">A verified CAD model exists. Re-born will route it to a provider with material and tolerance constraints.</p>
        ${badges([['Verified model', 'green'], ['Royalty enabled', 'blue'], ['Commercial use allowed', ''], ['Repair DNA linked', 'orange']])}
        <table class="table">
          <tr><th>Recommended material</th><td>PETG-CF for home FDM, PA12 for professional SLS</td></tr>
          <tr><th>Print constraints</th><td>0.2 mm layer, 4 walls, 45% infill, no PLA for warm cycles</td></tr>
          <tr><th>Maker royalty</th><td>€0.80 per fulfilled repair</td></tr>
          <tr><th>Validation status</th><td>14 completed repairs, 2 returns, no safety flags</td></tr>
        </table>
        <div class="actions"><a class="btn green" href="#/provider-network">Find local production</a><a class="btn secondary" href="#/repair-paths">Back</a></div>
      </div>
      <div class="prototype-frame"><div class="device-header"><div class="dots"><span></span><span></span><span></span></div><strong>CAD preview placeholder</strong></div><div class="frame-body"><div class="scan-visual"><div class="part-shape"></div></div></div></div>
    </section>
  `, { currentStep: 'repair-paths' });
}

function providerNetwork() {
  setActiveNav('provider-network');
  return layout('Providers', html`
    <section class="section-head"><div><p class="eyebrow">Distributed manufacturing</p><h2>Local providers ranked by trust and fit.</h2></div><p class="muted">Professional services and independent makers can both compete when quality, constraints and trust are explicit.</p></section>
    <section class="stack">
      ${D.providers.map(p => `<article class="card provider-card interactive ${S.selectedProvider === p.name ? 'selected' : ''}" onclick="REBORN_STATE.set('selectedProvider', '${p.name}'); toast('${p.name} selected.'); render();">
        <div class="stack">
          <div><h3>${p.name}</h3><p class="muted">${p.type} · ${p.distance} · ${p.jobs} completed jobs</p></div>
          ${badges([[`Rating ${p.rating}`, 'green'], [`Trust ${p.trust}`, 'blue'], [p.material, 'orange'], [p.eta, '']])}
        </div>
        <div><div class="price">${p.price}</div><p class="muted small">estimated total</p></div>
      </article>`).join('')}
    </section>
    <section class="section panel stack"><h3>Provider agreement preview</h3><p class="muted">Re-born collects a platform fee on every fulfilled repair. Provider receives clear model constraints, quality checks and delivery expectations before accepting.</p><div class="actions"><a class="btn green" href="#/checkout">Continue to repair order</a><a class="btn secondary" href="#/provider">Open provider view</a></div></section>
  `, { currentStep: 'repair-paths' });
}

function checkout() {
  return layout('Checkout', html`
    <section class="grid two">
      <div class="panel stack">
        <p class="eyebrow">Repair order</p>
        <h2>Confirm the repair, not just the purchase.</h2>
        <table class="table">
          <tr><th>Object</th><td>${D.product.detectedName}</td></tr>
          <tr><th>Path</th><td>Local production with verified model</td></tr>
          <tr><th>Provider</th><td>${S.selectedProvider}</td></tr>
          <tr><th>Material</th><td>PETG-CF / PA12 equivalent</td></tr>
          <tr><th>Platform fee</th><td>Included</td></tr>
          <tr><th>Maker royalty</th><td>Included</td></tr>
        </table>
        <div class="actions"><button class="btn green" onclick="toast('Prototype order created. In production this would create RepairOrder + Wallet events.')">Confirm repair order</button><a class="btn secondary" href="#/provider-network">Back</a></div>
      </div>
      <aside class="panel dark-panel stack">
        <h3>Wallet and impact</h3>
        <div class="grid two">
          ${metric(D.wallet.credits, 'Repair Credits')}
          ${metric(D.wallet.savedObjects, 'Objects saved')}
          ${metric(D.wallet.co2, 'CO₂ avoided')}
          ${metric('€2.10', 'Platform fee')}
        </div>
        <p class="muted">After completion, the repair updates provider trust, maker royalty, model reliability and Knowledge Graph confidence.</p>
      </aside>
    </section>
  `, { currentStep: 'checkout' });
}

function aiGeneration() {
  return layout('AI generation', html`
    <section class="grid two">
      <div class="panel stack">
        <p class="eyebrow">AI repair model</p>
        <h2>Generate only when repair knowledge is missing.</h2>
        <p class="muted">AI generation is positioned as a repair fallback, not as the product. Generated models need validation before becoming verified graph assets.</p>
        <div class="timeline">
          <div class="timeline-row"><div class="timeline-time">Step 1</div><div class="timeline-content"><strong>Geometry proposal</strong><p class="muted small">AI estimates shape from photos, dimensions and similar parts.</p></div></div>
          <div class="timeline-row"><div class="timeline-time">Step 2</div><div class="timeline-content"><strong>Constraint check</strong><p class="muted small">Wall thickness, tolerance, material and mechanical risk.</p></div></div>
          <div class="timeline-row"><div class="timeline-time">Step 3</div><div class="timeline-content"><strong>Provider validation</strong><p class="muted small">Provider flags printability before order confirmation.</p></div></div>
        </div>
        <div class="actions"><a class="btn orange" href="#/provider-network">Validate with provider</a><a class="btn secondary" href="#/repair-paths">Back to safer paths</a></div>
      </div>
      <aside class="panel stack"><h3>AI Premium trigger</h3><p class="muted">This flow consumes Repair Credits and creates potential marketplace assets if the model becomes verified.</p>${badges([['3 credits', 'orange'], ['Validation required', 'danger'], ['Learning event', 'blue']])}</aside>
    </section>
  `);
}

function account() {
  setActiveNav('account');
  return layout('Account', html`
    <section class="section-head"><div><p class="eyebrow">User dashboard</p><h2>Your repaired objects become intelligence.</h2></div><p class="muted">The dashboard turns repair history into trust, credits and sustainability impact.</p></section>
    <section class="grid four">
      ${metric(D.wallet.savedObjects, 'Objects saved')}
      ${metric(D.wallet.credits, 'Available credits')}
      ${metric(D.wallet.pendingRoyalties, 'Pending royalties')}
      ${metric(D.wallet.co2, 'CO₂ avoided')}
    </section>
    <section class="section grid two">
      <div class="panel stack"><h3>Active repair</h3><p class="status-line"><span class="status-dot"></span>${D.product.detectedName}</p><p class="muted">Provider accepted. Estimated completion: tomorrow 17:00.</p><div class="actions"><a class="btn green" href="#/checkout">Open order</a></div></div>
      <div class="panel stack"><h3>Knowledge contributions</h3><p class="muted">2 feedback events, 1 photo validation, 1 dimensional correction. These improve future repair recommendations.</p>${badges([['Graph contributor', 'blue'], ['Repair feedback', 'green']])}</div>
    </section>
  `);
}

function provider() {
  return layout('Provider view', html`
    <section class="section-head"><div><p class="eyebrow">Provider PRO</p><h2>Accept repairs with clear constraints.</h2></div><p class="muted">Providers should not receive vague STL jobs. They receive repair intent, constraints, quality checks and expected outcome.</p></section>
    <section class="grid two">
      <div class="panel stack"><h3>Incoming repair order</h3><table class="table"><tr><th>Part</th><td>Lower basket wheel</td></tr><tr><th>Material</th><td>PETG-CF or PA12</td></tr><tr><th>Deadline</th><td>Tomorrow 17:00</td></tr><tr><th>Quality check</th><td>Dimensional photo + fit confirmation</td></tr></table><div class="actions"><button class="btn green" onclick="toast('Provider accepted the job in prototype state.')">Accept job</button><button class="btn secondary" onclick="toast('Provider requested clarification.')">Ask question</button></div></div>
      <div class="panel stack"><h3>Provider score factors</h3>${badges([['On-time delivery', 'green'], ['Material compliance', 'blue'], ['Low return rate', 'green'], ['Local availability', 'orange']])}<p class="muted">Trust Engine will rank providers by repair success, not only by star reviews.</p></div>
    </section>
  `);
}

function maker() {
  setActiveNav('maker');
  return layout('Maker view', html`
    <section class="hero">
      <div class="panel stack"><p class="eyebrow">Maker CAD marketplace</p><h2>Upload models that repair real objects.</h2><p class="lead">Maker value is not measured only by downloads. It is measured by successful repairs, low returns and verified compatibility.</p><div class="form-grid"><div class="field"><label>Model name</label><input value="Bosch lower basket wheel replacement" /></div><div class="field"><label>License</label><select><option>Repair commercial license with royalty</option><option>Free community model</option><option>Enterprise restricted</option></select></div></div><div class="field"><label>Compatibility notes</label><textarea>Compatible with Bosch Series 4 lower basket. Avoid PLA. Validate axle diameter before production.</textarea></div><div class="actions"><button class="btn green" onclick="toast('Model submitted for verification in prototype state.')">Submit for verification</button></div></div>
      <aside class="panel dark-panel stack"><h3>Royalty logic</h3><p class="muted">Royalty is triggered by fulfilled repairs, not by speculative file views. Credits can be used for materials, prints or marketplace purchases.</p><div class="grid two">${metric('€0.80', 'royalty / repair')}${metric('14', 'verified repairs')}${metric('2.1%', 'return rate')}${metric('A-', 'model trust')}</div></aside>
    </section>
  `);
}

function enterprise() {
  return layout('Enterprise', html`
    <section class="grid two">
      <div class="panel stack"><p class="eyebrow">Enterprise Portal</p><h2>Repair intelligence for product fleets.</h2><p class="muted">Brands, facilities and circular economy operators can use Re-born as an intelligence layer for spare parts, maintenance, repairability and distributed fulfilment.</p>${badges([['Fleet repair analytics', 'blue'], ['White label', ''], ['API access', 'orange'], ['Compliance reporting', 'green']])}<div class="actions"><button class="btn blue" onclick="toast('Enterprise lead captured in prototype state.')">Request demo</button></div></div>
      <div class="panel stack"><h3>Enterprise metrics</h3><div class="grid two">${metric('1,284', 'fleet objects')}${metric('18%', 'parts recovered')}${metric('€42k', 'avoided replacement')}${metric('6.8t', 'CO₂ avoided')}</div></div>
    </section>
  `);
}

function adminOps() {
  return layout('Admin ops', html`
    <section class="section-head"><div><p class="eyebrow">Internal console</p><h2>Repair Intelligence operations.</h2></div><p class="muted">This prototype screen clarifies what internal teams will need to monitor before scaling.</p></section>
    <section class="grid three">
      <div class="panel stack"><h3>Graph queue</h3><p class="muted">12 pending validation events.</p>${badges([['5 dimensions', 'orange'], ['4 material reports', 'blue'], ['3 failed fits', 'danger']])}</div>
      <div class="panel stack"><h3>Provider risk</h3><p class="muted">2 providers require review due to delayed fulfilment.</p>${badges([['SLA review', 'orange'], ['Trust Engine', 'blue']])}</div>
      <div class="panel stack"><h3>AI moderation</h3><p class="muted">Generated models are quarantined until printability and safety checks pass.</p>${badges([['Validation gate', 'green'], ['Safety baseline', 'danger']])}</div>
    </section>
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

window.addEventListener('hashchange', render);
window.addEventListener('reborn:state', () => {});
menuButton?.addEventListener('click', () => {
  const open = nav.classList.toggle('is-open');
  menuButton.setAttribute('aria-expanded', String(open));
});
render();
