(function () {
  const API_TIMEOUT_MS = 4200;

  function isHttpRuntime() {
    return window.location.protocol === 'http:' || window.location.protocol === 'https:';
  }

  function fallbackResponse(reason) {
    return {
      ok: false,
      mode: 'mock',
      reason,
      message: 'Backend API not available. Prototype is using local mock data.'
    };
  }

  function withTimeout(ms) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), ms);
    return { controller, done: () => window.clearTimeout(timer) };
  }

  class RebornApiClient {
    constructor() {
      this.baseUrl = isHttpRuntime() ? window.location.origin : null;
      this.tokenKey = 'reborn_access_token';
    }

    canUseApi() {
      return Boolean(this.baseUrl);
    }

    async request(path, options = {}) {
      if (!this.canUseApi()) {
        throw new Error('Static file runtime: API calls are disabled.');
      }

      const timeout = withTimeout(API_TIMEOUT_MS);
      try {
        const isFormData = options.formData instanceof FormData;
        const response = await fetch(`${this.baseUrl}${path}`, {
          method: options.method || 'GET',
          headers: {
            'Accept': 'application/json',
            ...(this.getToken() ? { 'Authorization': `Bearer ${this.getToken()}` } : {}),
            ...(options.body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers || {})
          },
          body: isFormData ? options.formData : (options.body ? JSON.stringify(options.body) : undefined),
          signal: timeout.controller.signal
        });

        const text = await response.text();
        let payload = {};
        try {
          payload = text ? JSON.parse(text) : {};
        } catch (_parseError) {
          payload = { success: false, error: { code: 'NON_JSON_RESPONSE', message: text || 'Empty non-JSON response' } };
        }

        if (!response.ok) {
          const apiMessage = payload.error && payload.error.message ? payload.error.message : payload.message;
          const error = new Error(apiMessage || `API error ${response.status}`);
          error.status = response.status;
          error.payload = payload;
          throw error;
        }

        return payload;
      } finally {
        timeout.done();
      }
    }


    getToken() {
      try { return window.localStorage.getItem(this.tokenKey); } catch (_error) { return null; }
    }

    setToken(token) {
      try {
        if (token) window.localStorage.setItem(this.tokenKey, token);
        else window.localStorage.removeItem(this.tokenKey);
      } catch (_error) {}
    }

    async login(email, password) {
      const payload = await this.request('/api/v1/auth/login', {
        method: 'POST',
        body: { email, password }
      });
      if (payload.token && payload.token.access_token) {
        this.setToken(payload.token.access_token);
      }
      return payload;
    }

    async register(data) {
      const payload = await this.request('/api/v1/auth/register', {
        method: 'POST',
        body: data
      });
      if (payload.token && payload.token.access_token) {
        this.setToken(payload.token.access_token);
      }
      return payload;
    }

    async me() {
      return this.request('/api/v1/auth/me');
    }

    async logout() {
      try {
        return await this.request('/api/v1/auth/logout', { method: 'POST' });
      } finally {
        this.setToken(null);
      }
    }

    async health() {
      if (!this.canUseApi()) {
        return fallbackResponse('opened_from_file');
      }

      try {
        const payload = await this.request('/api/health');
        return {
          ok: true,
          mode: 'live',
          payload,
          message: `${payload.service || 'Re-born API'} is live.`
        };
      } catch (error) {
        return fallbackResponse(error.message || 'health_check_failed');
      }
    }

    async listRepairCases() {
      return this.request('/api/v1/repair-cases');
    }

    async createRepairCase(data) {
      return this.request('/api/v1/repair-cases', {
        method: 'POST',
        body: data
      });
    }

    async diagnoseRepairCase(id) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(id)}/diagnose`, {
        method: 'POST'
      });
    }

    async uploadRepairAttachment(caseId, file, kind = 'diagnostic_photo') {
      const formData = new FormData();
      formData.append('file', file, file.name || 'repair-upload.bin');
      formData.append('kind', kind);
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/attachments`, {
        method: 'POST',
        formData
      });
    }

    async getRepairAttachments(caseId) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/attachments`);
    }

    async requestRecognition(caseId, attachmentIds) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/recognition-jobs`, {
        method: 'POST',
        body: { attachment_ids: attachmentIds }
      });
    }

    async getRecognitionJobs(caseId) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/recognition-jobs`);
    }

    async getRecognitionJob(jobId) {
      return this.request(`/api/v1/recognition-jobs/${encodeURIComponent(jobId)}`);
    }

    async requestRepairPathDecision(caseId, recognitionJobId = null) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/repair-path-decisions`, {
        method: 'POST',
        body: recognitionJobId ? { recognition_job_id: recognitionJobId } : {}
      });
    }

    async getRepairPathDecisions(caseId) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/repair-path-decisions`);
    }

    async getRepairPathDecision(decisionId) {
      return this.request(`/api/v1/repair-path-decisions/${encodeURIComponent(decisionId)}`);
    }

    async requestProviderMatch(caseId, repairPathDecisionId = null) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/provider-matches`, {
        method: 'POST',
        body: repairPathDecisionId ? { repair_path_decision_id: repairPathDecisionId } : {}
      });
    }

    async getProviderMatches(caseId) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/provider-matches`);
    }

    async getProviderMatch(providerMatchId) {
      return this.request(`/api/v1/provider-matches/${encodeURIComponent(providerMatchId)}`);
    }

    async requestProviderQuote(providerMatchId, providerId) {
      return this.request(`/api/v1/provider-matches/${encodeURIComponent(providerMatchId)}/quote-requests`, {
        method: 'POST',
        body: { provider_id: providerId }
      });
    }

    async getQuoteRequests(caseId) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/quote-requests`);
    }

    async getQuoteRequest(quoteRequestId) {
      return this.request(`/api/v1/quote-requests/${encodeURIComponent(quoteRequestId)}`);
    }

    async createRepairOrder(quoteRequestId) {
      return this.request(`/api/v1/quote-requests/${encodeURIComponent(quoteRequestId)}/repair-orders`, {
        method: 'POST'
      });
    }

    async getRepairOrders(caseId) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/repair-orders`);
    }

    async getRepairOrder(orderId) {
      return this.request(`/api/v1/repair-orders/${encodeURIComponent(orderId)}`);
    }

    async createPaymentIntent(orderId) {
      return this.request(`/api/v1/repair-orders/${encodeURIComponent(orderId)}/payment-intents`, {
        method: 'POST'
      });
    }

    async getPaymentIntents(orderId) {
      return this.request(`/api/v1/repair-orders/${encodeURIComponent(orderId)}/payment-intents`);
    }

    async getPaymentIntent(paymentIntentId) {
      return this.request(`/api/v1/payment-intents/${encodeURIComponent(paymentIntentId)}`);
    }

    async confirmMockPaymentIntent(paymentIntentId) {
      return this.request(`/api/v1/payment-intents/${encodeURIComponent(paymentIntentId)}/confirm-mock`, {
        method: 'POST'
      });
    }


    async createRepairFulfilment(orderId) {
      return this.request(`/api/v1/repair-orders/${encodeURIComponent(orderId)}/fulfilments`, {
        method: 'POST'
      });
    }

    async getRepairFulfilments(orderId) {
      return this.request(`/api/v1/repair-orders/${encodeURIComponent(orderId)}/fulfilments`);
    }

    async getRepairFulfilment(fulfilmentId) {
      return this.request(`/api/v1/fulfilments/${encodeURIComponent(fulfilmentId)}`);
    }

    async acceptProviderFulfilment(fulfilmentId, providerNotes = '') {
      return this.request(`/api/v1/fulfilments/${encodeURIComponent(fulfilmentId)}/accept-provider`, {
        method: 'POST',
        body: { provider_notes: providerNotes }
      });
    }

    async updateFulfilmentStatus(fulfilmentId, status, note = '') {
      return this.request(`/api/v1/fulfilments/${encodeURIComponent(fulfilmentId)}/status`, {
        method: 'POST',
        body: { status, note }
      });
    }

    async listRepairPaths(caseId) {
      return this.request(`/api/v1/repair-paths?case_id=${encodeURIComponent(caseId)}`);
    }

    async listProviders() {
      return this.request('/api/v1/providers');
    }

    async listKnowledgeNodes() {
      return this.request('/api/v1/knowledge/nodes');
    }

    async dashboard() {
      return this.request('/api/v1/dashboard');
    }

    async roleDashboard(role) {
      return this.request(`/api/v1/dashboards/${encodeURIComponent(role)}`);
    }


    async bootstrap() {
      if (!this.canUseApi()) {
        return {
          ok: false,
          mode: 'mock',
          repair_cases: [],
          providers: [],
          knowledge_nodes: [],
          repair_paths: [],
          repair_attachments: [],
          recognition_jobs: [],
          repair_path_decisions: [],
          provider_matches: [],
          quote_requests: [],
          repair_orders: [],
          payment_intents: [],
          fulfilments: []
        };
      }

      const [providers, knowledge] = await Promise.all([
        this.listProviders().catch(() => ({ providers: [] })),
        this.listKnowledgeNodes().catch(() => ({ nodes: [] }))
      ]);

      let cases = { repair_cases: [] };
      if (this.getToken()) {
        cases = await this.listRepairCases().catch(() => ({ repair_cases: [] }));
      }

      const latestCase = (cases.repair_cases || [])[0] || null;
      let paths = { repair_paths: [] };
      let attachments = { attachments: [] };
      let recognitionJobs = { recognition_jobs: [] };
      let repairPathDecisions = { repair_path_decisions: [] };
      let providerMatches = { provider_matches: [] };
      let quoteRequests = { quote_requests: [] };
      let repairOrders = { repair_orders: [] };
      let paymentIntents = { payment_intents: [] };
      let fulfilments = { fulfilments: [] };

      if (latestCase && this.getToken()) {
        try {
          paths = await this.listRepairPaths(latestCase.id);
        } catch (_error) {
          paths = { repair_paths: [] };
        }
        try {
          attachments = await this.getRepairAttachments(latestCase.id);
        } catch (_error) {
          attachments = { attachments: [] };
        }
        try {
          recognitionJobs = await this.getRecognitionJobs(latestCase.id);
        } catch (_error) {
          recognitionJobs = { recognition_jobs: [] };
        }
        try {
          repairPathDecisions = await this.getRepairPathDecisions(latestCase.id);
        } catch (_error) {
          repairPathDecisions = { repair_path_decisions: [] };
        }
        try {
          providerMatches = await this.getProviderMatches(latestCase.id);
        } catch (_error) {
          providerMatches = { provider_matches: [] };
        }
        try {
          quoteRequests = await this.getQuoteRequests(latestCase.id);
        } catch (_error) {
          quoteRequests = { quote_requests: [] };
        }
        try {
          repairOrders = await this.getRepairOrders(latestCase.id);
        } catch (_error) {
          repairOrders = { repair_orders: [] };
        }
        const latestOrder = (repairOrders.repair_orders || [])[0] || null;
        if (latestOrder) {
          try {
            paymentIntents = await this.getPaymentIntents(latestOrder.id);
          } catch (_error) {
            paymentIntents = { payment_intents: [] };
          }
          try {
            fulfilments = await this.getRepairFulfilments(latestOrder.id);
          } catch (_error) {
            fulfilments = { fulfilments: [] };
          }
        }
      }

      return {
        ok: true,
        mode: 'live',
        repair_cases: cases.repair_cases || [],
        providers: providers.providers || [],
        knowledge_nodes: knowledge.nodes || [],
        repair_paths: paths.repair_paths || [],
        repair_attachments: attachments.attachments || [],
        recognition_jobs: recognitionJobs.recognition_jobs || [],
        repair_path_decisions: repairPathDecisions.repair_path_decisions || [],
        provider_matches: providerMatches.provider_matches || [],
        quote_requests: quoteRequests.quote_requests || [],
        repair_orders: repairOrders.repair_orders || [],
        payment_intents: paymentIntents.payment_intents || [],
        fulfilments: fulfilments.fulfilments || []
      };
    }
  }

  window.REBORN_API = new RebornApiClient();
})();
