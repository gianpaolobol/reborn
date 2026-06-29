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


    async createCompletionReport(fulfilmentId, data = {}) {
      return this.request(`/api/v1/fulfilments/${encodeURIComponent(fulfilmentId)}/completion-reports`, {
        method: 'POST',
        body: data
      });
    }

    async getCompletionReports(fulfilmentId) {
      return this.request(`/api/v1/fulfilments/${encodeURIComponent(fulfilmentId)}/completion-reports`);
    }

    async getCompletionReport(completionReportId) {
      return this.request(`/api/v1/completion-reports/${encodeURIComponent(completionReportId)}`);
    }

    async getLearningEvents(caseId) {
      return this.request(`/api/v1/repair-cases/${encodeURIComponent(caseId)}/learning-events`);
    }

    async getLearningEvent(learningEventId) {
      return this.request(`/api/v1/learning-events/${encodeURIComponent(learningEventId)}`);
    }

    async createTrustReview(completionReportId, data = {}) {
      return this.request(`/api/v1/completion-reports/${encodeURIComponent(completionReportId)}/trust-reviews`, {
        method: 'POST',
        body: data
      });
    }

    async getTrustReviews(completionReportId) {
      return this.request(`/api/v1/completion-reports/${encodeURIComponent(completionReportId)}/trust-reviews`);
    }

    async getProviderQualityScore(providerId) {
      return this.request(`/api/v1/providers/${encodeURIComponent(providerId)}/quality-score`);
    }

    async getProviderQualityScores() {
      return this.request('/api/v1/provider-quality-scores');
    }

    async getProviderTrustSignals(providerId) {
      return this.request(`/api/v1/providers/${encodeURIComponent(providerId)}/trust-signals`);
    }

    async getProviderTrustReviews(providerId) {
      return this.request(`/api/v1/providers/${encodeURIComponent(providerId)}/trust-reviews`);
    }


    async createProviderRankingSnapshot() {
      return this.request('/api/v1/governance/ranking-snapshots', {
        method: 'POST'
      });
    }

    async getProviderRankings() {
      return this.request('/api/v1/governance/provider-rankings');
    }

    async getLatestProviderRankingSnapshot() {
      return this.request('/api/v1/governance/ranking-snapshots/latest');
    }

    async recordProviderGovernanceAction(providerId, data = {}) {
      return this.request(`/api/v1/providers/${encodeURIComponent(providerId)}/governance-actions`, {
        method: 'POST',
        body: data
      });
    }

    async getProviderGovernanceActions(providerId, activeOnly = false) {
      return this.request(`/api/v1/providers/${encodeURIComponent(providerId)}/governance-actions${activeOnly ? '?active_only=1' : ''}`);
    }

    async getGovernanceActions() {
      return this.request('/api/v1/governance/actions');
    }

    async getGovernanceSummary() {
      return this.request('/api/v1/governance/summary');
    }

    async getGovernancePolicies() {
      return this.request('/api/v1/governance/policies');
    }


    async createOpsReviewItem(data = {}) {
      return this.request('/api/v1/ops/review-items', {
        method: 'POST',
        body: data
      });
    }

    async getOpsReviewItems(status = null) {
      return this.request(`/api/v1/ops/review-items${status ? `?status=${encodeURIComponent(status)}` : ''}`);
    }

    async getOpsReviewItem(reviewItemId) {
      return this.request(`/api/v1/ops/review-items/${encodeURIComponent(reviewItemId)}`);
    }

    async assignOpsReviewItem(reviewItemId, assignedTo = null) {
      return this.request(`/api/v1/ops/review-items/${encodeURIComponent(reviewItemId)}/assign`, {
        method: 'POST',
        body: assignedTo ? { assigned_to: assignedTo } : {}
      });
    }

    async recordOpsModerationAction(reviewItemId, data = {}) {
      return this.request(`/api/v1/ops/review-items/${encodeURIComponent(reviewItemId)}/moderation-actions`, {
        method: 'POST',
        body: data
      });
    }

    async createOpsEscalation(reviewItemId, data = {}) {
      return this.request(`/api/v1/ops/review-items/${encodeURIComponent(reviewItemId)}/escalations`, {
        method: 'POST',
        body: data
      });
    }

    async resolveOpsReviewItem(reviewItemId, data = {}) {
      return this.request(`/api/v1/ops/review-items/${encodeURIComponent(reviewItemId)}/resolve`, {
        method: 'POST',
        body: data
      });
    }

    async getOpsEscalations() {
      return this.request('/api/v1/ops/escalations');
    }

    async getOpsSummary() {
      return this.request('/api/v1/ops/summary');
    }

    async getOpsPolicies() {
      return this.request('/api/v1/ops/policies');
    }

    async getPlatformReadiness() {
      return this.request('/api/v1/platform/readiness');
    }

    async getSecurityPolicy() {
      return this.request('/api/v1/platform/security-policy');
    }

    async getRuntimeReport() {
      return this.request('/api/v1/platform/runtime');
    }

    async getDeployChecklist() {
      return this.request('/api/v1/platform/deploy-checklist');
    }

    async createReadinessSnapshot() {
      return this.request('/api/v1/platform/readiness-snapshots', {
        method: 'POST'
      });
    }



    async getReadinessSnapshots(limit = 20) {
      return this.request(`/api/v1/platform/readiness-snapshots?limit=${encodeURIComponent(limit)}`);
    }

    async getObservability() {
      return this.request('/api/v1/platform/observability');
    }

    async getHttpMetrics(limit = 50) {
      return this.request(`/api/v1/platform/http-metrics?limit=${encodeURIComponent(limit)}`);
    }

    async getPlatformLogs(limit = 80) {
      return this.request(`/api/v1/platform/logs?limit=${encodeURIComponent(limit)}`);
    }

    async getBackups(limit = 20) {
      return this.request(`/api/v1/platform/backups?limit=${encodeURIComponent(limit)}`);
    }

    async createBackup() {
      return this.request('/api/v1/platform/backups', {
        method: 'POST'
      });
    }

    async getDeploymentRunbook() {
      return this.request('/api/v1/platform/deployment-runbook');
    }

    async getSmokeTestsSummary() {
      return this.request('/api/v1/platform/smoke-tests-summary');
    }

    async getStatusPage() {
      return this.request('/api/status');
    }

    async getIncidentResponse() {
      return this.request('/api/v1/platform/incident-response');
    }

    async getAlertRules() {
      return this.request('/api/v1/platform/alert-rules');
    }

    async evaluateAlerts() {
      return this.request('/api/v1/platform/alerts/evaluate', { method: 'POST' });
    }

    async getAlerts(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/alerts?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async acknowledgeAlert(id) {
      return this.request(`/api/v1/platform/alerts/${encodeURIComponent(id)}/acknowledge`, { method: 'POST' });
    }

    async resolveAlert(id, message = 'Resolved from prototype console.') {
      return this.request(`/api/v1/platform/alerts/${encodeURIComponent(id)}/resolve`, {
        method: 'POST',
        body: { message }
      });
    }

    async getIncidents(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/incidents?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createIncident(payload) {
      return this.request('/api/v1/platform/incidents', {
        method: 'POST',
        body: payload
      });
    }

    async updateIncidentStatus(id, payload) {
      return this.request(`/api/v1/platform/incidents/${encodeURIComponent(id)}/status`, {
        method: 'POST',
        body: payload
      });
    }

    async getStatusUpdates(limit = 20) {
      return this.request(`/api/v1/platform/status-updates?limit=${encodeURIComponent(limit)}`);
    }

    async createStatusUpdate(payload) {
      return this.request('/api/v1/platform/status-updates', {
        method: 'POST',
        body: payload
      });
    }

    async getMaintenanceWindows(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/maintenance-windows?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createMaintenanceWindow(payload) {
      return this.request('/api/v1/platform/maintenance-windows', {
        method: 'POST',
        body: payload
      });
    }

    async closeMaintenanceWindow(id) {
      return this.request(`/api/v1/platform/maintenance-windows/${encodeURIComponent(id)}/close`, { method: 'POST' });
    }


    async getNotificationCenter() {
      return this.request('/api/v1/platform/notification-center');
    }

    async getNotificationChannels() {
      return this.request('/api/v1/platform/notification-channels');
    }

    async createNotificationChannel(payload) {
      return this.request('/api/v1/platform/notification-channels', {
        method: 'POST',
        body: payload
      });
    }

    async getNotificationRules() {
      return this.request('/api/v1/platform/notification-rules');
    }

    async getNotificationDeliveries(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/notification-deliveries?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async dispatchNotifications(payload = {}) {
      return this.request('/api/v1/platform/notifications/dispatch', {
        method: 'POST',
        body: payload
      });
    }

    async markNotificationDelivery(id, status = 'sent', message = '') {
      return this.request(`/api/v1/platform/notification-deliveries/${encodeURIComponent(id)}/status`, {
        method: 'POST',
        body: { status, message }
      });
    }

    async getEscalationPolicies() {
      return this.request('/api/v1/platform/escalation-policies');
    }

    async getEscalationRuns(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/escalation-runs?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async escalateIncident(id, payload = {}) {
      return this.request(`/api/v1/platform/incidents/${encodeURIComponent(id)}/escalate`, {
        method: 'POST',
        body: payload
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
          fulfilments: [],
          completion_reports: [],
          learning_events: [],
          trust_reviews: [],
          provider_quality_scores: [],
          provider_quality_score: null,
          provider_trust_signals: [],
          governance_summary: null,
          governance_policy: null,
          provider_rankings: [],
          provider_ranking_snapshot: null,
          governance_actions: [],
          ops_summary: null,
          ops_policy: null,
          ops_review_items: [],
          ops_review_item: null,
          ops_escalations: [],
          platform_readiness: null,
          security_policy: null,
          runtime_report: null,
          deploy_checklist: null,
          observability: null,
          http_metrics: null,
          platform_logs: null,
          backup_status: null,
          backups: [],
          readiness_snapshots: [],
          deployment_runbook: null,
          smoke_tests: null,
          status_page: null,
          incident_response: null,
          alert_rules: [],
          alerts: [],
          incidents: [],
          status_updates: [],
          maintenance_windows: [],
          notification_center: null,
          notification_channels: [],
          notification_rules: [],
          notification_deliveries: [],
          escalation_policies: [],
          escalation_runs: []
        };
      }

      const [providers, knowledge] = await Promise.all([
        this.listProviders().catch(() => ({ providers: [] })),
        this.listKnowledgeNodes().catch(() => ({ nodes: [] }))
      ]);

      let cases = { repair_cases: [] };
      let platformReadiness = { readiness: null };
      let securityPolicy = { security_policy: null };
      let runtimeReport = { runtime: null };
      let deployChecklist = { deploy_checklist: null };
      let observability = { observability: null };
      let httpMetrics = { http_metrics: null };
      let platformLogs = { logs: null };
      let backups = { backup_status: null, backups: [] };
      let readinessSnapshots = { readiness_snapshots: [] };
      let deploymentRunbook = { deployment_runbook: null };
      let smokeTests = { smoke_tests: null };
      let statusPage = { status_page: null };
      let incidentResponse = { incident_response: null };
      let alertRules = { alert_rules: [] };
      let alerts = { alerts: [] };
      let incidents = { incidents: [] };
      let statusUpdates = { status_updates: [] };
      let maintenanceWindows = { maintenance_windows: [] };
      let notificationCenter = { notification_center: null };
      let notificationChannels = { notification_channels: [] };
      let notificationRules = { notification_rules: [] };
      let notificationDeliveries = { notification_deliveries: [] };
      let escalationPolicies = { escalation_policies: [] };
      let escalationRuns = { escalation_runs: [] };
      try {
        platformReadiness = await this.getPlatformReadiness();
      } catch (_error) {
        platformReadiness = { readiness: null };
      }
      try {
        securityPolicy = await this.getSecurityPolicy();
      } catch (_error) {
        securityPolicy = { security_policy: null };
      }
      try {
        statusPage = await this.getStatusPage();
      } catch (_error) {
        statusPage = { status_page: null };
      }

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
      let completionReports = { completion_reports: [] };
      let learningEvents = { learning_events: [] };
      let trustReviews = { trust_reviews: [] };
      let providerQualityScores = { quality_scores: [] };
      let providerQualityScore = { quality_score: null };
      let providerTrustSignals = { trust_signals: [] };
      let governanceSummary = { summary: null, policy: null };
      let providerRankings = { provider_rankings: [], ranking_snapshot: null };
      let governanceActions = { governance_actions: [] };
      let opsSummary = { summary: null, policy: null };
      let opsReviewItems = { review_items: [] };
      let opsEscalations = { escalations: [] };

      if (this.getToken()) {
        try {
          providerQualityScores = await this.getProviderQualityScores();
        } catch (_error) {
          providerQualityScores = { quality_scores: [] };
        }
        try {
          providerRankings = await this.getProviderRankings();
        } catch (_error) {
          providerRankings = { provider_rankings: [], ranking_snapshot: null };
        }
        try {
          governanceSummary = await this.getGovernanceSummary();
        } catch (_error) {
          governanceSummary = { summary: null, policy: null };
        }
        try {
          governanceActions = await this.getGovernanceActions();
        } catch (_error) {
          governanceActions = { governance_actions: [] };
        }
        try {
          opsSummary = await this.getOpsSummary();
        } catch (_error) {
          opsSummary = { summary: null, policy: null };
        }
        try {
          opsReviewItems = await this.getOpsReviewItems();
        } catch (_error) {
          opsReviewItems = { review_items: [] };
        }
        try {
          opsEscalations = await this.getOpsEscalations();
        } catch (_error) {
          opsEscalations = { escalations: [] };
        }
        try {
          runtimeReport = await this.getRuntimeReport();
        } catch (_error) {
          runtimeReport = { runtime: null };
        }
        try {
          deployChecklist = await this.getDeployChecklist();
        } catch (_error) {
          deployChecklist = { deploy_checklist: null };
        }
        try {
          observability = await this.getObservability();
        } catch (_error) {
          observability = { observability: null };
        }
        try {
          httpMetrics = await this.getHttpMetrics();
        } catch (_error) {
          httpMetrics = { http_metrics: null };
        }
        try {
          platformLogs = await this.getPlatformLogs(40);
        } catch (_error) {
          platformLogs = { logs: null };
        }
        try {
          backups = await this.getBackups();
        } catch (_error) {
          backups = { backup_status: null, backups: [] };
        }
        try {
          readinessSnapshots = await this.getReadinessSnapshots();
        } catch (_error) {
          readinessSnapshots = { readiness_snapshots: [] };
        }
        try {
          deploymentRunbook = await this.getDeploymentRunbook();
        } catch (_error) {
          deploymentRunbook = { deployment_runbook: null };
        }
        try {
          smokeTests = await this.getSmokeTestsSummary();
        } catch (_error) {
          smokeTests = { smoke_tests: null };
        }
        try {
          incidentResponse = await this.getIncidentResponse();
        } catch (_error) {
          incidentResponse = { incident_response: null };
        }
        try {
          alertRules = await this.getAlertRules();
        } catch (_error) {
          alertRules = { alert_rules: [] };
        }
        try {
          alerts = await this.getAlerts();
        } catch (_error) {
          alerts = { alerts: [] };
        }
        try {
          incidents = await this.getIncidents();
        } catch (_error) {
          incidents = { incidents: [] };
        }
        try {
          statusUpdates = await this.getStatusUpdates();
        } catch (_error) {
          statusUpdates = { status_updates: [] };
        }
        try {
          maintenanceWindows = await this.getMaintenanceWindows();
        } catch (_error) {
          maintenanceWindows = { maintenance_windows: [] };
        }
        try {
          notificationCenter = await this.getNotificationCenter();
        } catch (_error) {
          notificationCenter = { notification_center: null };
        }
        try {
          notificationChannels = await this.getNotificationChannels();
        } catch (_error) {
          notificationChannels = { notification_channels: [] };
        }
        try {
          notificationRules = await this.getNotificationRules();
        } catch (_error) {
          notificationRules = { notification_rules: [] };
        }
        try {
          notificationDeliveries = await this.getNotificationDeliveries('all', 30);
        } catch (_error) {
          notificationDeliveries = { notification_deliveries: [] };
        }
        try {
          escalationPolicies = await this.getEscalationPolicies();
        } catch (_error) {
          escalationPolicies = { escalation_policies: [] };
        }
        try {
          escalationRuns = await this.getEscalationRuns();
        } catch (_error) {
          escalationRuns = { escalation_runs: [] };
        }

      }

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
          const latestFulfilment = (fulfilments.fulfilments || [])[0] || null;
          if (latestFulfilment) {
            try {
              completionReports = await this.getCompletionReports(latestFulfilment.id);
            } catch (_error) {
              completionReports = { completion_reports: [] };
            }
            const latestReport = (completionReports.completion_reports || [])[0] || null;
            if (latestReport) {
              try {
                trustReviews = await this.getTrustReviews(latestReport.id);
              } catch (_error) {
                trustReviews = { trust_reviews: [] };
              }
              try {
                providerQualityScore = await this.getProviderQualityScore(latestReport.provider_id);
              } catch (_error) {
                providerQualityScore = { quality_score: null };
              }
              try {
                providerTrustSignals = await this.getProviderTrustSignals(latestReport.provider_id);
              } catch (_error) {
                providerTrustSignals = { trust_signals: [] };
              }
            }
          }
        }
        try {
          learningEvents = await this.getLearningEvents(latestCase.id);
        } catch (_error) {
          learningEvents = { learning_events: [] };
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
        fulfilments: fulfilments.fulfilments || [],
        completion_reports: completionReports.completion_reports || [],
        learning_events: learningEvents.learning_events || [],
        trust_reviews: trustReviews.trust_reviews || [],
        provider_quality_scores: providerQualityScores.quality_scores || [],
        provider_quality_score: providerQualityScore.quality_score || null,
        provider_trust_signals: providerTrustSignals.trust_signals || [],
        governance_summary: governanceSummary.summary || null,
        governance_policy: governanceSummary.policy || null,
        provider_rankings: providerRankings.provider_rankings || [],
        provider_ranking_snapshot: providerRankings.ranking_snapshot || null,
        governance_actions: governanceActions.governance_actions || [],
        ops_summary: opsSummary.summary || null,
        ops_policy: opsSummary.policy || null,
        ops_review_items: opsReviewItems.review_items || [],
        ops_review_item: (opsReviewItems.review_items || [])[0] || null,
        ops_escalations: opsEscalations.escalations || [],
        platform_readiness: platformReadiness.readiness || null,
        security_policy: securityPolicy.security_policy || null,
        runtime_report: runtimeReport.runtime || null,
        deploy_checklist: deployChecklist.deploy_checklist || null,
        observability: observability.observability || null,
        http_metrics: httpMetrics.http_metrics || null,
        platform_logs: platformLogs.logs || null,
        backup_status: backups.backup_status || null,
        backups: backups.backups || [],
        readiness_snapshots: readinessSnapshots.readiness_snapshots || [],
        deployment_runbook: deploymentRunbook.deployment_runbook || null,
        smoke_tests: smokeTests.smoke_tests || null,
        status_page: statusPage.status_page || null,
        incident_response: incidentResponse.incident_response || null,
        alert_rules: alertRules.alert_rules || [],
        alerts: alerts.alerts || [],
        incidents: incidents.incidents || [],
        status_updates: statusUpdates.status_updates || [],
        maintenance_windows: maintenanceWindows.maintenance_windows || [],
        notification_center: notificationCenter.notification_center || null,
        notification_channels: notificationChannels.notification_channels || [],
        notification_rules: notificationRules.notification_rules || [],
        notification_deliveries: notificationDeliveries.notification_deliveries || [],
        escalation_policies: escalationPolicies.escalation_policies || [],
        escalation_runs: escalationRuns.escalation_runs || []
      };
    }
  }

  window.REBORN_API = new RebornApiClient();
})();
