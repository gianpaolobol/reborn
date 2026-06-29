window.REBORN_STATE = {
  selectedPath: 'print',
  selectedProvider: 'Bologna 3D Lab',
  uploaded: false,
  selectedUploadFiles: [],
  role: 'customer',
  busy: false,
  auth: {
    status: 'guest',
    user: null,
    tokenStored: false,
    lastLoginAt: null
  },
  api: {
    status: 'checking',
    mode: 'unknown',
    message: 'Checking backend API...',
    lastError: null,
    repairCase: null,
    repairCases: [],
    diagnosis: null,
    repairPaths: [],
    repairAttachments: [],
    recognitionJobs: [],
    recognitionJob: null,
    repairPathDecisions: [],
    repairPathDecision: null,
    providerMatches: [],
    providerMatch: null,
    quoteRequests: [],
    quoteRequest: null,
    repairOrders: [],
    repairOrder: null,
    paymentIntents: [],
    paymentIntent: null,
    fulfilments: [],
    fulfilment: null,
    completionReports: [],
    completionReport: null,
    learningEvents: [],
    learningEvent: null,
    trustReviews: [],
    trustReview: null,
    providerQualityScores: [],
    providerQualityScore: null,
    providerTrustSignals: [],
    providers: [],
    knowledgeNodes: [],
    lastSyncAt: null,
    dashboard: null,
    roleDashboards: {}
  },
  set(key, value) {
    this[key] = value;
    window.dispatchEvent(new CustomEvent('reborn:state', { detail: { key, value } }));
  },
  setAuth(patch) {
    this.auth = { ...this.auth, ...patch };
    window.dispatchEvent(new CustomEvent('reborn:state', { detail: { key: 'auth', value: this.auth } }));
  },
  setApi(patch) {
    this.api = { ...this.api, ...patch };
    window.dispatchEvent(new CustomEvent('reborn:state', { detail: { key: 'api', value: this.api } }));
  }
};
