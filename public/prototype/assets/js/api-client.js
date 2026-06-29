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
          recognition_jobs: []
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
      }

      return {
        ok: true,
        mode: 'live',
        repair_cases: cases.repair_cases || [],
        providers: providers.providers || [],
        knowledge_nodes: knowledge.nodes || [],
        repair_paths: paths.repair_paths || [],
        repair_attachments: attachments.attachments || [],
        recognition_jobs: recognitionJobs.recognition_jobs || []
      };
    }
  }

  window.REBORN_API = new RebornApiClient();
})();
