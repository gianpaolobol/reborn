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
        const response = await fetch(`${this.baseUrl}${path}`, {
          method: options.method || 'GET',
          headers: {
            'Accept': 'application/json',
            ...(this.getToken() ? { 'Authorization': `Bearer ${this.getToken()}` } : {}),
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers || {})
          },
          body: options.body ? JSON.stringify(options.body) : undefined,
          signal: timeout.controller.signal
        });

        const text = await response.text();
        const payload = text ? JSON.parse(text) : {};

        if (!response.ok) {
          const error = new Error(payload.message || `API error ${response.status}`);
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
          repair_paths: []
        };
      }

      const [cases, providers, knowledge] = await Promise.all([
        this.listRepairCases(),
        this.listProviders(),
        this.listKnowledgeNodes()
      ]);

      const latestCase = (cases.repair_cases || [])[0] || null;
      let paths = { repair_paths: [] };

      if (latestCase) {
        try {
          paths = await this.listRepairPaths(latestCase.id);
        } catch (_error) {
          paths = { repair_paths: [] };
        }
      }

      return {
        ok: true,
        mode: 'live',
        repair_cases: cases.repair_cases || [],
        providers: providers.providers || [],
        knowledge_nodes: knowledge.nodes || [],
        repair_paths: paths.repair_paths || []
      };
    }
  }

  window.REBORN_API = new RebornApiClient();
})();
