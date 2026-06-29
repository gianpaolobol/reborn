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





    async getPrivacyGovernance() {
      return this.request('/api/v1/platform/privacy-governance');
    }

    async getPrivacyNotices(status = 'all') {
      return this.request(`/api/v1/platform/privacy-notices?status=${encodeURIComponent(status)}`);
    }

    async getConsentRecords(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/consent-records?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async recordConsent(data) {
      return this.request('/api/v1/platform/consent-records', {
        method: 'POST',
        body: data
      });
    }

    async withdrawConsent(id, note) {
      return this.request(`/api/v1/platform/consent-records/${encodeURIComponent(id)}/withdraw`, {
        method: 'POST',
        body: { note }
      });
    }

    async getDataProcessingRecords() {
      return this.request('/api/v1/platform/data-processing-records');
    }

    async getRetentionRules() {
      return this.request('/api/v1/platform/retention-rules');
    }

    async evaluateRetention() {
      return this.request('/api/v1/platform/retention/evaluate', { method: 'POST' });
    }

    async getRetentionEvaluations(limit = 50) {
      return this.request(`/api/v1/platform/retention-evaluations?limit=${encodeURIComponent(limit)}`);
    }

    async getDataSubjectRequests(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/data-subject-requests?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createDataSubjectRequest(data) {
      return this.request('/api/v1/platform/data-subject-requests', {
        method: 'POST',
        body: data
      });
    }

    async resolveDataSubjectRequest(id, status = 'fulfilled', resolutionNotes = 'Resolved from Step 25 prototype console.') {
      return this.request(`/api/v1/platform/data-subject-requests/${encodeURIComponent(id)}/resolve`, {
        method: 'POST',
        body: { status, resolution_notes: resolutionNotes }
      });
    }

    async generateDataExport(id) {
      return this.request(`/api/v1/platform/data-subject-requests/${encodeURIComponent(id)}/export`, { method: 'POST' });
    }

    async getDataExports(limit = 50) {
      return this.request(`/api/v1/platform/data-exports?limit=${encodeURIComponent(limit)}`);
    }

    async getReleaseManagement() {
      return this.request('/api/v1/platform/release-management');
    }

    async getBetaReadiness() {
      return this.request('/api/v1/platform/beta-readiness');
    }

    async getFeatureFlags(status = 'all') {
      return this.request(`/api/v1/platform/feature-flags?status=${encodeURIComponent(status)}`);
    }

    async updateFeatureFlag(id, payload = {}) {
      return this.request(`/api/v1/platform/feature-flags/${encodeURIComponent(id)}`, {
        method: 'POST',
        body: payload
      });
    }

    async getReleases(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/releases?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createRelease(payload = {}) {
      return this.request('/api/v1/platform/releases', {
        method: 'POST',
        body: payload
      });
    }

    async evaluateReleaseGates(id) {
      return this.request(`/api/v1/platform/releases/${encodeURIComponent(id)}/evaluate-gates`, { method: 'POST' });
    }

    async getReleaseGates(id) {
      return this.request(`/api/v1/platform/releases/${encodeURIComponent(id)}/gates`);
    }

    async decideRelease(id, decision = 'approve', rationale = 'Approved from Step 26 prototype console.') {
      return this.request(`/api/v1/platform/releases/${encodeURIComponent(id)}/decision`, {
        method: 'POST',
        body: { decision, rationale }
      });
    }

    async getReleaseDecisions(limit = 50) {
      return this.request(`/api/v1/platform/release-decisions?limit=${encodeURIComponent(limit)}`);
    }

    async getPilotCohorts(status = 'all') {
      return this.request(`/api/v1/platform/pilot-cohorts?status=${encodeURIComponent(status)}`);
    }

    async updatePilotCohort(id, payload = {}) {
      return this.request(`/api/v1/platform/pilot-cohorts/${encodeURIComponent(id)}`, {
        method: 'POST',
        body: payload
      });
    }

    async getPilotParticipants(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/pilot-participants?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async addPilotParticipant(payload = {}) {
      return this.request('/api/v1/platform/pilot-participants', {
        method: 'POST',
        body: payload
      });
    }

    async updatePilotParticipant(id, payload = {}) {
      return this.request(`/api/v1/platform/pilot-participants/${encodeURIComponent(id)}`, {
        method: 'POST',
        body: payload
      });
    }


    async getPartnerOnboarding() {
      return this.request('/api/v1/platform/partner-onboarding');
    }

    async getPartners(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/partners?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createPartner(payload = {}) {
      return this.request('/api/v1/platform/partners', {
        method: 'POST',
        body: payload
      });
    }

    async evaluatePartnerReadiness(id) {
      return this.request(`/api/v1/platform/partners/${encodeURIComponent(id)}/readiness/evaluate`, { method: 'POST' });
    }

    async getPartnerTasks(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/partner-tasks?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async updatePartnerTaskStatus(id, status = 'completed', evidence = 'Completed from Step 27 prototype console.') {
      return this.request(`/api/v1/platform/partner-tasks/${encodeURIComponent(id)}/status`, {
        method: 'POST',
        body: { status, evidence }
      });
    }

    async getPartnerAgreements(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/partner-agreements?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async updatePartnerAgreementStatus(id, status = 'accepted', notes = 'Accepted from Step 27 prototype console for local pilot governance evidence.') {
      return this.request(`/api/v1/platform/partner-agreements/${encodeURIComponent(id)}/status`, {
        method: 'POST',
        body: { status, notes }
      });
    }

    async getPartnerIntegrations(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/partner-integrations?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async updatePartnerIntegrationStatus(id, status = 'testing', notes = 'Checked from Step 27 prototype console.') {
      return this.request(`/api/v1/platform/partner-integrations/${encodeURIComponent(id)}/status`, {
        method: 'POST',
        body: { status, notes }
      });
    }

    async getPartnerReadinessReviews(limit = 50) {
      return this.request(`/api/v1/platform/partner-readiness-reviews?limit=${encodeURIComponent(limit)}`);
    }


    async getMarketplaceRevenue() {
      return this.request('/api/v1/platform/marketplace-revenue');
    }

    async getMarketplaceFeePolicies(status = 'all') {
      return this.request(`/api/v1/platform/marketplace-fee-policies?status=${encodeURIComponent(status)}`);
    }

    async getCreditAccounts(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/credit-accounts?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createCreditAccount(payload = {}) {
      return this.request('/api/v1/platform/credit-accounts', {
        method: 'POST',
        body: payload
      });
    }

    async getCreditTransactions(limit = 50) {
      return this.request(`/api/v1/platform/credit-transactions?limit=${encodeURIComponent(limit)}`);
    }

    async recordCreditTransaction(payload = {}) {
      return this.request('/api/v1/platform/credit-transactions', {
        method: 'POST',
        body: payload
      });
    }

    async getPayoutAccounts(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/payout-accounts?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createPayoutAccount(payload = {}) {
      return this.request('/api/v1/platform/payout-accounts', {
        method: 'POST',
        body: payload
      });
    }

    async getPayoutRuns(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/payout-runs?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async evaluatePayoutRun(payload = {}) {
      return this.request('/api/v1/platform/payout-runs/evaluate', {
        method: 'POST',
        body: payload
      });
    }

    async approvePayoutRun(id, notes = 'Approved from Step 28 prototype console.') {
      return this.request(`/api/v1/platform/payout-runs/${encodeURIComponent(id)}/approve`, {
        method: 'POST',
        body: { notes }
      });
    }

    async markPayoutRunPaid(id, notes = 'Marked paid from Step 28 prototype console. No real money moved.') {
      return this.request(`/api/v1/platform/payout-runs/${encodeURIComponent(id)}/paid`, {
        method: 'POST',
        body: { notes }
      });
    }

    async getPayoutItems(limit = 50) {
      return this.request(`/api/v1/platform/payout-items?limit=${encodeURIComponent(limit)}`);
    }

    async getRevenueAuditLog(limit = 50) {
      return this.request(`/api/v1/platform/revenue-audit-log?limit=${encodeURIComponent(limit)}`);
    }


    async getMakerEconomy() {
      return this.request('/api/v1/platform/maker-economy');
    }

    async getMakerProfiles(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/maker-profiles?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createMakerProfile(payload = {}) {
      return this.request('/api/v1/platform/maker-profiles', {
        method: 'POST',
        body: payload
      });
    }

    async updateMakerProfileStatus(id, status = 'active', trustTier = 'verified') {
      return this.request(`/api/v1/platform/maker-profiles/${encodeURIComponent(id)}/status`, {
        method: 'POST',
        body: { status, trust_tier: trustTier }
      });
    }

    async getModelAssets(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/model-assets?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async submitModelAsset(payload = {}) {
      return this.request('/api/v1/platform/model-assets', {
        method: 'POST',
        body: payload
      });
    }

    async reviewModelAsset(id, status = 'approved', qualityScore = 88) {
      return this.request(`/api/v1/platform/model-assets/${encodeURIComponent(id)}/review`, {
        method: 'POST',
        body: { status, quality_score: qualityScore, safety_notes: 'Reviewed from Step 29 prototype console. Pilot only.' }
      });
    }

    async getModelLicenses(status = 'all') {
      return this.request(`/api/v1/platform/model-licenses?status=${encodeURIComponent(status)}`);
    }

    async getModelDownloads(limit = 50) {
      return this.request(`/api/v1/platform/model-downloads?limit=${encodeURIComponent(limit)}`);
    }

    async recordModelDownload(payload = {}) {
      return this.request('/api/v1/platform/model-downloads', {
        method: 'POST',
        body: payload
      });
    }

    async getModelRoyaltyEvents(limit = 50) {
      return this.request(`/api/v1/platform/model-royalty-events?limit=${encodeURIComponent(limit)}`);
    }

    async getRepairBounties(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/repair-bounties?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createRepairBounty(payload = {}) {
      return this.request('/api/v1/platform/repair-bounties', {
        method: 'POST',
        body: payload
      });
    }

    async getBountySubmissions(limit = 50) {
      return this.request(`/api/v1/platform/bounty-submissions?limit=${encodeURIComponent(limit)}`);
    }

    async submitBounty(payload = {}) {
      return this.request('/api/v1/platform/bounty-submissions', {
        method: 'POST',
        body: payload
      });
    }

    async reviewBountySubmission(id, payload = {}) {
      return this.request(`/api/v1/platform/bounty-submissions/${encodeURIComponent(id)}/review`, {
        method: 'POST',
        body: payload
      });
    }

    async getMakerEconomyAuditLog(limit = 50) {
      return this.request(`/api/v1/platform/maker-economy-audit-log?limit=${encodeURIComponent(limit)}`);
    }


    async getAiGovernance() {
      return this.request('/api/v1/platform/ai-governance');
    }

    async getAiModelProviders(status = 'all') {
      return this.request(`/api/v1/platform/ai-model-providers?status=${encodeURIComponent(status)}`);
    }

    async getAiPipelineRuns(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/ai-pipeline-runs?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createAiPipelineRun(payload = {}) {
      return this.request('/api/v1/platform/ai-pipeline-runs', {
        method: 'POST',
        body: payload
      });
    }

    async reviewAiPipelineRun(id, payload = {}) {
      return this.request(`/api/v1/platform/ai-pipeline-runs/${encodeURIComponent(id)}/review`, {
        method: 'POST',
        body: payload
      });
    }

    async getAiHumanReviews(limit = 50) {
      return this.request(`/api/v1/platform/ai-human-reviews?limit=${encodeURIComponent(limit)}`);
    }

    async getAiDatasetItems(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/ai-dataset-items?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createAiDatasetItem(payload = {}) {
      return this.request('/api/v1/platform/ai-dataset-items', {
        method: 'POST',
        body: payload
      });
    }

    async getAiQualityEvaluations(limit = 50) {
      return this.request(`/api/v1/platform/ai-quality-evaluations?limit=${encodeURIComponent(limit)}`);
    }

    async evaluateAiQuality(payload = {}) {
      return this.request('/api/v1/platform/ai-quality-evaluations/evaluate', {
        method: 'POST',
        body: payload
      });
    }

    async getAiSafetyRules(status = 'active') {
      return this.request(`/api/v1/platform/ai-safety-rules?status=${encodeURIComponent(status)}`);
    }

    async getAiGovernanceAuditLog(limit = 50) {
      return this.request(`/api/v1/platform/ai-governance-audit-log?limit=${encodeURIComponent(limit)}`);
    }



    async getAiProviderSandbox() {
      return this.request('/api/v1/platform/ai-provider-sandbox');
    }

    async getAiProviderAdapters(status = 'all') {
      return this.request(`/api/v1/platform/ai-provider-adapters?status=${encodeURIComponent(status)}`);
    }

    async checkAiProviderAdapters() {
      return this.request('/api/v1/platform/ai-provider-adapters/health-check', { method: 'POST' });
    }

    async getAiOrchestrationJobs(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/ai-orchestration-jobs?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createAiOrchestrationJob(payload = {}) {
      return this.request('/api/v1/platform/ai-orchestration-jobs', {
        method: 'POST',
        body: payload
      });
    }

    async advanceAiOrchestrationJob(id, payload = {}) {
      return this.request(`/api/v1/platform/ai-orchestration-jobs/${encodeURIComponent(id)}/advance`, {
        method: 'POST',
        body: payload
      });
    }

    async retryAiOrchestrationJob(id) {
      return this.request(`/api/v1/platform/ai-orchestration-jobs/${encodeURIComponent(id)}/retry`, { method: 'POST' });
    }

    async cancelAiOrchestrationJob(id, reason = 'Cancelled from prototype console.') {
      return this.request(`/api/v1/platform/ai-orchestration-jobs/${encodeURIComponent(id)}/cancel`, {
        method: 'POST',
        body: { reason }
      });
    }

    async getAiJobEvents(limit = 50) {
      return this.request(`/api/v1/platform/ai-job-events?limit=${encodeURIComponent(limit)}`);
    }

    async getAiArtifactStubs(limit = 50) {
      return this.request(`/api/v1/platform/ai-artifact-stubs?limit=${encodeURIComponent(limit)}`);
    }

    async getAiProviderCostLedger(limit = 50) {
      return this.request(`/api/v1/platform/ai-provider-cost-ledger?limit=${encodeURIComponent(limit)}`);
    }

    async getAiProviderSandboxAuditLog(limit = 50) {
      return this.request(`/api/v1/platform/ai-provider-sandbox-audit-log?limit=${encodeURIComponent(limit)}`);
    }



    async getGeometryPrintability() {
      return this.request('/api/v1/platform/geometry-printability');
    }

    async getGeometryValidationProfiles(status = 'active') {
      return this.request(`/api/v1/platform/geometry-validation-profiles?status=${encodeURIComponent(status)}`);
    }

    async getPrintabilityRules(status = 'active') {
      return this.request(`/api/v1/platform/printability-rules?status=${encodeURIComponent(status)}`);
    }

    async getGeometryAssets(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/geometry-assets?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createGeometryAsset(payload = {}) {
      return this.request('/api/v1/platform/geometry-assets', { method: 'POST', body: payload });
    }

    async evaluateGeometryAsset(id, payload = {}) {
      return this.request(`/api/v1/platform/geometry-assets/${encodeURIComponent(id)}/evaluate`, { method: 'POST', body: payload });
    }

    async getGeometryValidationRuns(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/geometry-validation-runs?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async getPrintabilityFindings(status = 'open', limit = 50) {
      return this.request(`/api/v1/platform/printability-findings?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async getGeometryReviewItems(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/geometry-review-items?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async reviewGeometryItem(id, payload = {}) {
      return this.request(`/api/v1/platform/geometry-review-items/${encodeURIComponent(id)}/review`, { method: 'POST', body: payload });
    }

    async getGeometryGovernanceAuditLog(limit = 50) {
      return this.request(`/api/v1/platform/geometry-governance-audit-log?limit=${encodeURIComponent(limit)}`);
    }


    async getProviderRouting() {
      return this.request('/api/v1/platform/provider-routing');
    }

    async getProviderCapabilities(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/provider-capabilities?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async getMachineProfiles(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/machine-profiles?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async getRoutingPolicies(status = 'active') {
      return this.request(`/api/v1/platform/routing-policies?status=${encodeURIComponent(status)}`);
    }

    async getRoutingRequests(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/routing-requests?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createRoutingRequest(payload = {}) {
      return this.request('/api/v1/platform/routing-requests', { method: 'POST', body: payload });
    }

    async evaluateRoutingRequest(id, payload = {}) {
      return this.request(`/api/v1/platform/routing-requests/${encodeURIComponent(id)}/evaluate`, { method: 'POST', body: payload });
    }

    async getRoutingMatches(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/routing-matches?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async getRoutingReviewItems(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/routing-review-items?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async reviewRoutingItem(id, payload = {}) {
      return this.request(`/api/v1/platform/routing-review-items/${encodeURIComponent(id)}/review`, { method: 'POST', body: payload });
    }

    async getProviderRoutingAuditLog(limit = 50) {
      return this.request(`/api/v1/platform/provider-routing-audit-log?limit=${encodeURIComponent(limit)}`);
    }



    async getDispatchGovernance() {
      return this.request('/api/v1/platform/dispatch-governance');
    }

    async getDispatchPolicies(status = 'active') {
      return this.request(`/api/v1/platform/dispatch-policies?status=${encodeURIComponent(status)}`);
    }

    async getDispatches(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/dispatches?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createDispatch(payload = {}) {
      return this.request('/api/v1/platform/dispatches', { method: 'POST', body: payload });
    }

    async advanceDispatch(id, payload = {}) {
      return this.request(`/api/v1/platform/dispatches/${encodeURIComponent(id)}/advance`, { method: 'POST', body: payload });
    }

    async getShipmentEvents(dispatchId = null, limit = 50) {
      const query = dispatchId ? `dispatch_id=${encodeURIComponent(dispatchId)}&limit=${encodeURIComponent(limit)}` : `limit=${encodeURIComponent(limit)}`;
      return this.request(`/api/v1/platform/shipment-events?${query}`);
    }

    async recordShipmentEvent(id, payload = {}) {
      return this.request(`/api/v1/platform/dispatches/${encodeURIComponent(id)}/shipment-events`, { method: 'POST', body: payload });
    }

    async getProofOfRepairRecords(status = 'all', limit = 50) {
      return this.request(`/api/v1/platform/proof-of-repair-records?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async createProofOfRepair(id, payload = {}) {
      return this.request(`/api/v1/platform/dispatches/${encodeURIComponent(id)}/proof-of-repair`, { method: 'POST', body: payload });
    }

    async reviewProofOfRepair(id, payload = {}) {
      return this.request(`/api/v1/platform/proof-of-repair-records/${encodeURIComponent(id)}/review`, { method: 'POST', body: payload });
    }

    async getDispatchReviewItems(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/dispatch-review-items?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async reviewDispatchItem(id, payload = {}) {
      return this.request(`/api/v1/platform/dispatch-review-items/${encodeURIComponent(id)}/review`, { method: 'POST', body: payload });
    }

    async getDispatchAuditLog(limit = 50) {
      return this.request(`/api/v1/platform/dispatch-audit-log?limit=${encodeURIComponent(limit)}`);
    }

    async getServiceGovernance() {
      return this.request('/api/v1/platform/service-governance');
    }

    async getSlaPolicies() {
      return this.request('/api/v1/platform/sla-policies');
    }

    async evaluateSlas() {
      return this.request('/api/v1/platform/slas/evaluate', { method: 'POST' });
    }

    async getSlaEvaluations(status = 'active', limit = 50) {
      return this.request(`/api/v1/platform/sla-evaluations?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(limit)}`);
    }

    async markSlaResponse(id, note = 'First response recorded from prototype console.') {
      return this.request(`/api/v1/platform/sla-evaluations/${encodeURIComponent(id)}/response`, {
        method: 'POST',
        body: { note }
      });
    }

    async markSlaResolved(id, note = 'SLA resolved from prototype console.') {
      return this.request(`/api/v1/platform/sla-evaluations/${encodeURIComponent(id)}/resolve`, {
        method: 'POST',
        body: { note }
      });
    }

    async getOperationalPolicies(status = 'all') {
      return this.request(`/api/v1/platform/operational-policies?status=${encodeURIComponent(status)}`);
    }

    async getPolicyAttestations(limit = 50) {
      return this.request(`/api/v1/platform/policy-attestations?limit=${encodeURIComponent(limit)}`);
    }

    async attestOperationalPolicy(id, payload = {}) {
      return this.request(`/api/v1/platform/operational-policies/${encodeURIComponent(id)}/attest`, {
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
          escalation_runs: [],
          service_governance: null,
          sla_policies: [],
          sla_evaluations: [],
          operational_policies: [],
          policy_attestations: [],
          privacy_governance: null,
          privacy_notices: [],
          consent_records: [],
          data_processing_records: [],
          retention_rules: [],
          retention_evaluations: [],
          data_subject_requests: [],
          data_exports: [],
          release_management: null,
          beta_readiness: null,
          feature_flags: [],
          releases: [],
          release_gates: [],
          release_decisions: [],
          pilot_cohorts: [],
          pilot_participants: [],
          partner_onboarding: null,
          partners: [],
          partner_tasks: [],
          partner_agreements: [],
          partner_integrations: [],
          partner_readiness_reviews: [],
          marketplace_revenue: null,
          fee_policies: [],
          credit_accounts: [],
          credit_transactions: [],
          payout_accounts: [],
          payout_runs: [],
          payout_items: [],
          revenue_audit_log: [],
          ai_provider_sandbox: null,
          ai_provider_adapters: [],
          ai_orchestration_jobs: [],
          ai_job_events: [],
          ai_artifact_stubs: [],
          ai_provider_cost_ledger: [],
          ai_provider_sandbox_audit_log: [],
          provider_routing: null,
          provider_capabilities: [],
          machine_profiles: [],
          routing_policies: [],
          routing_requests: [],
          routing_matches: [],
          routing_review_items: [],
          provider_routing_audit_log: [],
          dispatch_governance: null,
          dispatch_policies: [],
          dispatches: [],
          shipment_events: [],
          proof_of_repair_records: [],
          dispatch_review_items: [],
          dispatch_audit_log: []
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
      let serviceGovernance = { service_governance: null };
      let slaPolicies = { sla_policies: [] };
      let slaEvaluations = { sla_evaluations: [] };
      let operationalPolicies = { operational_policies: [] };
      let policyAttestations = { policy_attestations: [] };
      let privacyGovernance = { privacy_governance: null };
      let privacyNotices = { privacy_notices: [] };
      let consentRecords = { consent_records: [] };
      let dataProcessingRecords = { data_processing_records: [] };
      let retentionRules = { retention_rules: [] };
      let retentionEvaluations = { retention_evaluations: [] };
      let dataSubjectRequests = { data_subject_requests: [] };
      let dataExports = { data_exports: [] };
      let releaseManagement = { release_management: null };
      let betaReadiness = { beta_readiness: null };
      let featureFlags = { feature_flags: [] };
      let releases = { releases: [] };
      let releaseGates = { release_gates: [] };
      let releaseDecisions = { release_decisions: [] };
      let pilotCohorts = { pilot_cohorts: [] };
      let pilotParticipants = { pilot_participants: [] };
      let partnerOnboarding = { partner_onboarding: null };
      let partnersList = { partners: [] };
      let partnerTasks = { partner_tasks: [] };
      let partnerAgreements = { partner_agreements: [] };
      let partnerIntegrations = { partner_integrations: [] };
      let partnerReadinessReviews = { partner_readiness_reviews: [] };
      let marketplaceRevenue = { marketplace_revenue: null };
      let feePolicies = { fee_policies: [] };
      let creditAccounts = { credit_accounts: [] };
      let creditTransactions = { credit_transactions: [] };
      let payoutAccounts = { payout_accounts: [] };
      let payoutRuns = { payout_runs: [] };
      let payoutItems = { payout_items: [] };
      let revenueAuditLog = { revenue_audit_log: [] };
      let makerEconomy = { maker_economy: null };
      let makerProfiles = { maker_profiles: [] };
      let modelAssets = { model_assets: [] };
      let modelLicenses = { model_licenses: [] };
      let modelDownloads = { model_downloads: [] };
      let modelRoyaltyEvents = { model_royalty_events: [] };
      let repairBounties = { repair_bounties: [] };
      let bountySubmissions = { bounty_submissions: [] };
      let makerEconomyAuditLog = { maker_economy_audit_log: [] };
      let aiGovernance = { ai_governance: null };
      let aiModelProviders = { ai_model_providers: [] };
      let aiPipelineRuns = { ai_pipeline_runs: [] };
      let aiHumanReviews = { ai_human_reviews: [] };
      let aiDatasetItems = { ai_dataset_items: [] };
      let aiQualityEvaluations = { ai_quality_evaluations: [] };
      let aiSafetyRules = { ai_safety_rules: [] };
      let aiGovernanceAuditLog = { ai_governance_audit_log: [] };
      let aiProviderSandbox = { ai_provider_sandbox: null };
      let aiProviderAdapters = { ai_provider_adapters: [] };
      let aiOrchestrationJobs = { ai_orchestration_jobs: [] };
      let aiJobEvents = { ai_job_events: [] };
      let aiArtifactStubs = { ai_artifact_stubs: [] };
      let aiProviderCostLedger = { ai_provider_cost_ledger: [] };
      let aiProviderSandboxAuditLog = { ai_provider_sandbox_audit_log: [] };
      let geometryPrintability = { geometry_printability: null };
      let geometryValidationProfiles = { geometry_validation_profiles: [] };
      let printabilityRules = { printability_rules: [] };
      let geometryAssets = { geometry_assets: [] };
      let geometryValidationRuns = { geometry_validation_runs: [] };
      let printabilityFindings = { printability_findings: [] };
      let geometryReviewItems = { geometry_review_items: [] };
      let geometryGovernanceAuditLog = { geometry_governance_audit_log: [] };
      let providerRouting = { provider_routing: null };
      let providerCapabilities = { provider_capabilities: [] };
      let machineProfiles = { machine_profiles: [] };
      let routingPolicies = { routing_policies: [] };
      let routingRequests = { routing_requests: [] };
      let routingMatches = { routing_matches: [] };
      let routingReviewItems = { routing_review_items: [] };
      let providerRoutingAuditLog = { provider_routing_audit_log: [] };
      let dispatchGovernance = { dispatch_governance: null };
      let dispatchPolicies = { dispatch_policies: [] };
      let dispatches = { dispatches: [] };
      let shipmentEvents = { shipment_events: [] };
      let proofOfRepairRecords = { proof_of_repair_records: [] };
      let dispatchReviewItems = { dispatch_review_items: [] };
      let dispatchAuditLog = { dispatch_audit_log: [] };
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
        try {
          serviceGovernance = await this.getServiceGovernance();
        } catch (_error) {
          serviceGovernance = { service_governance: null };
        }
        try {
          slaPolicies = await this.getSlaPolicies();
        } catch (_error) {
          slaPolicies = { sla_policies: [] };
        }
        try {
          slaEvaluations = await this.getSlaEvaluations();
        } catch (_error) {
          slaEvaluations = { sla_evaluations: [] };
        }
        try {
          operationalPolicies = await this.getOperationalPolicies();
        } catch (_error) {
          operationalPolicies = { operational_policies: [] };
        }
        try {
          policyAttestations = await this.getPolicyAttestations(25);
        } catch (_error) {
          policyAttestations = { policy_attestations: [] };
        }

        try {
          privacyGovernance = await this.getPrivacyGovernance();
        } catch (_error) {
          privacyGovernance = { privacy_governance: null };
        }
        try {
          privacyNotices = await this.getPrivacyNotices('all');
        } catch (_error) {
          privacyNotices = { privacy_notices: [] };
        }
        try {
          consentRecords = await this.getConsentRecords('all', 30);
        } catch (_error) {
          consentRecords = { consent_records: [] };
        }
        try {
          dataProcessingRecords = await this.getDataProcessingRecords();
        } catch (_error) {
          dataProcessingRecords = { data_processing_records: [] };
        }
        try {
          retentionRules = await this.getRetentionRules();
        } catch (_error) {
          retentionRules = { retention_rules: [] };
        }
        try {
          retentionEvaluations = await this.getRetentionEvaluations(30);
        } catch (_error) {
          retentionEvaluations = { retention_evaluations: [] };
        }
        try {
          dataSubjectRequests = await this.getDataSubjectRequests('active', 30);
        } catch (_error) {
          dataSubjectRequests = { data_subject_requests: [] };
        }
        try {
          dataExports = await this.getDataExports(30);
        } catch (_error) {
          dataExports = { data_exports: [] };
        }
        try {
          releaseManagement = await this.getReleaseManagement();
        } catch (_error) {
          releaseManagement = { release_management: null };
        }
        try {
          betaReadiness = await this.getBetaReadiness();
        } catch (_error) {
          betaReadiness = { beta_readiness: null };
        }
        try {
          featureFlags = await this.getFeatureFlags('all');
        } catch (_error) {
          featureFlags = { feature_flags: [] };
        }
        try {
          releases = await this.getReleases('active', 30);
        } catch (_error) {
          releases = { releases: [] };
        }
        const latestRelease = (releases.releases || [])[0] || (releaseManagement.release_management?.latest_release || null);
        if (latestRelease) {
          try {
            releaseGates = await this.getReleaseGates(latestRelease.id);
          } catch (_error) {
            releaseGates = { release_gates: [] };
          }
        }
        try {
          releaseDecisions = await this.getReleaseDecisions(30);
        } catch (_error) {
          releaseDecisions = { release_decisions: [] };
        }
        try {
          pilotCohorts = await this.getPilotCohorts('all');
        } catch (_error) {
          pilotCohorts = { pilot_cohorts: [] };
        }
        try {
          pilotParticipants = await this.getPilotParticipants('all', 30);
        } catch (_error) {
          pilotParticipants = { pilot_participants: [] };
        }
        try {
          partnerOnboarding = await this.getPartnerOnboarding();
        } catch (_error) {
          partnerOnboarding = { partner_onboarding: null };
        }
        try {
          partnersList = await this.getPartners('all', 30);
        } catch (_error) {
          partnersList = { partners: [] };
        }
        try {
          partnerTasks = await this.getPartnerTasks('all', 40);
        } catch (_error) {
          partnerTasks = { partner_tasks: [] };
        }
        try {
          partnerAgreements = await this.getPartnerAgreements('all', 30);
        } catch (_error) {
          partnerAgreements = { partner_agreements: [] };
        }
        try {
          partnerIntegrations = await this.getPartnerIntegrations('all', 30);
        } catch (_error) {
          partnerIntegrations = { partner_integrations: [] };
        }
        try {
          partnerReadinessReviews = await this.getPartnerReadinessReviews(30);
        } catch (_error) {
          partnerReadinessReviews = { partner_readiness_reviews: [] };
        }
        try {
          marketplaceRevenue = await this.getMarketplaceRevenue();
        } catch (_error) {
          marketplaceRevenue = { marketplace_revenue: null };
        }
        try {
          feePolicies = await this.getMarketplaceFeePolicies('all');
        } catch (_error) {
          feePolicies = { fee_policies: [] };
        }
        try {
          creditAccounts = await this.getCreditAccounts('all', 30);
        } catch (_error) {
          creditAccounts = { credit_accounts: [] };
        }
        try {
          creditTransactions = await this.getCreditTransactions(30);
        } catch (_error) {
          creditTransactions = { credit_transactions: [] };
        }
        try {
          payoutAccounts = await this.getPayoutAccounts('all', 30);
        } catch (_error) {
          payoutAccounts = { payout_accounts: [] };
        }
        try {
          payoutRuns = await this.getPayoutRuns('active', 30);
        } catch (_error) {
          payoutRuns = { payout_runs: [] };
        }
        try {
          payoutItems = await this.getPayoutItems(30);
        } catch (_error) {
          payoutItems = { payout_items: [] };
        }
        try {
          revenueAuditLog = await this.getRevenueAuditLog(30);
        } catch (_error) {
          revenueAuditLog = { revenue_audit_log: [] };
        }
        try {
          makerEconomy = await this.getMakerEconomy();
        } catch (_error) {
          makerEconomy = { maker_economy: null };
        }
        try {
          makerProfiles = await this.getMakerProfiles('all', 30);
        } catch (_error) {
          makerProfiles = { maker_profiles: [] };
        }
        try {
          modelAssets = await this.getModelAssets('all', 30);
        } catch (_error) {
          modelAssets = { model_assets: [] };
        }
        try {
          modelLicenses = await this.getModelLicenses('all');
        } catch (_error) {
          modelLicenses = { model_licenses: [] };
        }
        try {
          modelDownloads = await this.getModelDownloads(30);
        } catch (_error) {
          modelDownloads = { model_downloads: [] };
        }
        try {
          modelRoyaltyEvents = await this.getModelRoyaltyEvents(30);
        } catch (_error) {
          modelRoyaltyEvents = { model_royalty_events: [] };
        }
        try {
          repairBounties = await this.getRepairBounties('active', 30);
        } catch (_error) {
          repairBounties = { repair_bounties: [] };
        }
        try {
          bountySubmissions = await this.getBountySubmissions(30);
        } catch (_error) {
          bountySubmissions = { bounty_submissions: [] };
        }
        try {
          makerEconomyAuditLog = await this.getMakerEconomyAuditLog(30);
        } catch (_error) {
          makerEconomyAuditLog = { maker_economy_audit_log: [] };
        }

        try {
          aiGovernance = await this.getAiGovernance();
        } catch (_error) {
          aiGovernance = { ai_governance: null };
        }
        try {
          aiModelProviders = await this.getAiModelProviders('all');
        } catch (_error) {
          aiModelProviders = { ai_model_providers: [] };
        }
        try {
          aiPipelineRuns = await this.getAiPipelineRuns('active', 30);
        } catch (_error) {
          aiPipelineRuns = { ai_pipeline_runs: [] };
        }
        try {
          aiHumanReviews = await this.getAiHumanReviews(30);
        } catch (_error) {
          aiHumanReviews = { ai_human_reviews: [] };
        }
        try {
          aiDatasetItems = await this.getAiDatasetItems('all', 30);
        } catch (_error) {
          aiDatasetItems = { ai_dataset_items: [] };
        }
        try {
          aiQualityEvaluations = await this.getAiQualityEvaluations(30);
        } catch (_error) {
          aiQualityEvaluations = { ai_quality_evaluations: [] };
        }
        try {
          aiSafetyRules = await this.getAiSafetyRules('active');
        } catch (_error) {
          aiSafetyRules = { ai_safety_rules: [] };
        }
        try {
          aiGovernanceAuditLog = await this.getAiGovernanceAuditLog(30);
        } catch (_error) {
          aiGovernanceAuditLog = { ai_governance_audit_log: [] };
        }

        try {
          aiProviderSandbox = await this.getAiProviderSandbox();
        } catch (_error) {
          aiProviderSandbox = { ai_provider_sandbox: null };
        }
        try {
          aiProviderAdapters = await this.getAiProviderAdapters('all');
        } catch (_error) {
          aiProviderAdapters = { ai_provider_adapters: [] };
        }
        try {
          aiOrchestrationJobs = await this.getAiOrchestrationJobs('active', 30);
        } catch (_error) {
          aiOrchestrationJobs = { ai_orchestration_jobs: [] };
        }
        try {
          aiJobEvents = await this.getAiJobEvents(30);
        } catch (_error) {
          aiJobEvents = { ai_job_events: [] };
        }
        try {
          aiArtifactStubs = await this.getAiArtifactStubs(30);
        } catch (_error) {
          aiArtifactStubs = { ai_artifact_stubs: [] };
        }
        try {
          aiProviderCostLedger = await this.getAiProviderCostLedger(30);
        } catch (_error) {
          aiProviderCostLedger = { ai_provider_cost_ledger: [] };
        }
        try {
          aiProviderSandboxAuditLog = await this.getAiProviderSandboxAuditLog(30);
        } catch (_error) {
          aiProviderSandboxAuditLog = { ai_provider_sandbox_audit_log: [] };
        }
        try {
          geometryPrintability = await this.getGeometryPrintability();
        } catch (_error) {
          geometryPrintability = { geometry_printability: null };
        }
        try {
          geometryValidationProfiles = await this.getGeometryValidationProfiles('active');
        } catch (_error) {
          geometryValidationProfiles = { geometry_validation_profiles: [] };
        }
        try {
          printabilityRules = await this.getPrintabilityRules('active');
        } catch (_error) {
          printabilityRules = { printability_rules: [] };
        }
        try {
          geometryAssets = await this.getGeometryAssets('all', 30);
        } catch (_error) {
          geometryAssets = { geometry_assets: [] };
        }
        try {
          geometryValidationRuns = await this.getGeometryValidationRuns('all', 30);
        } catch (_error) {
          geometryValidationRuns = { geometry_validation_runs: [] };
        }
        try {
          printabilityFindings = await this.getPrintabilityFindings('open', 30);
        } catch (_error) {
          printabilityFindings = { printability_findings: [] };
        }
        try {
          geometryReviewItems = await this.getGeometryReviewItems('active', 30);
        } catch (_error) {
          geometryReviewItems = { geometry_review_items: [] };
        }
        try {
          geometryGovernanceAuditLog = await this.getGeometryGovernanceAuditLog(30);
        } catch (_error) {
          geometryGovernanceAuditLog = { geometry_governance_audit_log: [] };
        }

        try {
          providerRouting = await this.getProviderRouting();
        } catch (_error) {
          providerRouting = { provider_routing: null };
        }
        try {
          providerCapabilities = await this.getProviderCapabilities('active', 30);
        } catch (_error) {
          providerCapabilities = { provider_capabilities: [] };
        }
        try {
          machineProfiles = await this.getMachineProfiles('active', 30);
        } catch (_error) {
          machineProfiles = { machine_profiles: [] };
        }
        try {
          routingPolicies = await this.getRoutingPolicies('active');
        } catch (_error) {
          routingPolicies = { routing_policies: [] };
        }
        try {
          routingRequests = await this.getRoutingRequests('all', 30);
        } catch (_error) {
          routingRequests = { routing_requests: [] };
        }
        try {
          routingMatches = await this.getRoutingMatches('all', 30);
        } catch (_error) {
          routingMatches = { routing_matches: [] };
        }
        try {
          routingReviewItems = await this.getRoutingReviewItems('active', 30);
        } catch (_error) {
          routingReviewItems = { routing_review_items: [] };
        }
        try {
          providerRoutingAuditLog = await this.getProviderRoutingAuditLog(30);
        } catch (_error) {
          providerRoutingAuditLog = { provider_routing_audit_log: [] };
        }

        try {
          dispatchGovernance = await this.getDispatchGovernance();
        } catch (_error) {
          dispatchGovernance = { dispatch_governance: null };
        }
        try {
          dispatchPolicies = await this.getDispatchPolicies('active');
        } catch (_error) {
          dispatchPolicies = { dispatch_policies: [] };
        }
        try {
          dispatches = await this.getDispatches('all', 30);
        } catch (_error) {
          dispatches = { dispatches: [] };
        }
        try {
          shipmentEvents = await this.getShipmentEvents(null, 30);
        } catch (_error) {
          shipmentEvents = { shipment_events: [] };
        }
        try {
          proofOfRepairRecords = await this.getProofOfRepairRecords('all', 30);
        } catch (_error) {
          proofOfRepairRecords = { proof_of_repair_records: [] };
        }
        try {
          dispatchReviewItems = await this.getDispatchReviewItems('active', 30);
        } catch (_error) {
          dispatchReviewItems = { dispatch_review_items: [] };
        }
        try {
          dispatchAuditLog = await this.getDispatchAuditLog(30);
        } catch (_error) {
          dispatchAuditLog = { dispatch_audit_log: [] };
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
        escalation_runs: escalationRuns.escalation_runs || [],
        service_governance: serviceGovernance.service_governance || null,
        sla_policies: slaPolicies.sla_policies || [],
        sla_evaluations: slaEvaluations.sla_evaluations || [],
        operational_policies: operationalPolicies.operational_policies || [],
        policy_attestations: policyAttestations.policy_attestations || [],
        privacy_governance: privacyGovernance.privacy_governance || null,
        privacy_notices: privacyNotices.privacy_notices || [],
        consent_records: consentRecords.consent_records || [],
        data_processing_records: dataProcessingRecords.data_processing_records || [],
        retention_rules: retentionRules.retention_rules || [],
        retention_evaluations: retentionEvaluations.retention_evaluations || [],
        data_subject_requests: dataSubjectRequests.data_subject_requests || [],
        data_exports: dataExports.data_exports || [],
        release_management: releaseManagement.release_management || null,
        beta_readiness: betaReadiness.beta_readiness || null,
        feature_flags: featureFlags.feature_flags || [],
        releases: releases.releases || [],
        release_gates: releaseGates.release_gates || [],
        release_decisions: releaseDecisions.release_decisions || [],
        pilot_cohorts: pilotCohorts.pilot_cohorts || [],
        pilot_participants: pilotParticipants.pilot_participants || [],
        partner_onboarding: partnerOnboarding.partner_onboarding || null,
        partners: partnersList.partners || [],
        partner_tasks: partnerTasks.partner_tasks || [],
        partner_agreements: partnerAgreements.partner_agreements || [],
        partner_integrations: partnerIntegrations.partner_integrations || [],
        partner_readiness_reviews: partnerReadinessReviews.partner_readiness_reviews || [],
        marketplace_revenue: marketplaceRevenue.marketplace_revenue || null,
        fee_policies: feePolicies.fee_policies || [],
        credit_accounts: creditAccounts.credit_accounts || [],
        credit_transactions: creditTransactions.credit_transactions || [],
        payout_accounts: payoutAccounts.payout_accounts || [],
        payout_runs: payoutRuns.payout_runs || [],
        payout_items: payoutItems.payout_items || [],
        revenue_audit_log: revenueAuditLog.revenue_audit_log || [],
        maker_economy: makerEconomy.maker_economy || null,
        maker_profiles: makerProfiles.maker_profiles || [],
        model_assets: modelAssets.model_assets || [],
        model_licenses: modelLicenses.model_licenses || [],
        model_downloads: modelDownloads.model_downloads || [],
        model_royalty_events: modelRoyaltyEvents.model_royalty_events || [],
        repair_bounties: repairBounties.repair_bounties || [],
        bounty_submissions: bountySubmissions.bounty_submissions || [],
        maker_economy_audit_log: makerEconomyAuditLog.maker_economy_audit_log || [],
        ai_governance: aiGovernance.ai_governance || null,
        ai_model_providers: aiModelProviders.ai_model_providers || [],
        ai_pipeline_runs: aiPipelineRuns.ai_pipeline_runs || [],
        ai_human_reviews: aiHumanReviews.ai_human_reviews || [],
        ai_dataset_items: aiDatasetItems.ai_dataset_items || [],
        ai_quality_evaluations: aiQualityEvaluations.ai_quality_evaluations || [],
        ai_safety_rules: aiSafetyRules.ai_safety_rules || [],
        ai_governance_audit_log: aiGovernanceAuditLog.ai_governance_audit_log || [],
        ai_provider_sandbox: aiProviderSandbox.ai_provider_sandbox || null,
        ai_provider_adapters: aiProviderAdapters.ai_provider_adapters || [],
        ai_orchestration_jobs: aiOrchestrationJobs.ai_orchestration_jobs || [],
        ai_job_events: aiJobEvents.ai_job_events || [],
        ai_artifact_stubs: aiArtifactStubs.ai_artifact_stubs || [],
        ai_provider_cost_ledger: aiProviderCostLedger.ai_provider_cost_ledger || [],
        ai_provider_sandbox_audit_log: aiProviderSandboxAuditLog.ai_provider_sandbox_audit_log || [],
        geometry_printability: geometryPrintability.geometry_printability || null,
        geometry_validation_profiles: geometryValidationProfiles.geometry_validation_profiles || [],
        printability_rules: printabilityRules.printability_rules || [],
        geometry_assets: geometryAssets.geometry_assets || [],
        geometry_validation_runs: geometryValidationRuns.geometry_validation_runs || [],
        printability_findings: printabilityFindings.printability_findings || [],
        geometry_review_items: geometryReviewItems.geometry_review_items || [],
        geometry_governance_audit_log: geometryGovernanceAuditLog.geometry_governance_audit_log || [],
        provider_routing: providerRouting.provider_routing || null,
        provider_capabilities: providerCapabilities.provider_capabilities || [],
        machine_profiles: machineProfiles.machine_profiles || [],
        routing_policies: routingPolicies.routing_policies || [],
        routing_requests: routingRequests.routing_requests || [],
        routing_matches: routingMatches.routing_matches || [],
        routing_review_items: routingReviewItems.routing_review_items || [],
        provider_routing_audit_log: providerRoutingAuditLog.provider_routing_audit_log || [],
        dispatch_governance: dispatchGovernance.dispatch_governance || null,
        dispatch_policies: dispatchPolicies.dispatch_policies || [],
        dispatches: dispatches.dispatches || [],
        shipment_events: shipmentEvents.shipment_events || [],
        proof_of_repair_records: proofOfRepairRecords.proof_of_repair_records || [],
        dispatch_review_items: dispatchReviewItems.dispatch_review_items || [],
        dispatch_audit_log: dispatchAuditLog.dispatch_audit_log || []
      };
    }
  }

  window.REBORN_API = new RebornApiClient();
})();
