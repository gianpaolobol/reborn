(function () {
  'use strict';

  class RebornApiClient {
    constructor(baseUrl) {
      this.baseUrl = String(baseUrl || '').replace(/\/$/, '');
      this.token = '';
    }

    setToken(token) {
      this.token = String(token || '');
    }

    async request(path, options) {
      const opts = options || {};
      const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
      const request = {
        method: opts.method || 'GET',
        headers,
        credentials: 'same-origin'
      };

      if (this.token) {
        request.headers.Authorization = 'Bearer ' + this.token;
      }

      if (opts.body !== undefined) {
        if (opts.body instanceof FormData) {
          request.body = opts.body;
        } else {
          request.headers['Content-Type'] = 'application/json';
          request.body = JSON.stringify(opts.body);
        }
      }

      const controller = new AbortController();
      const timeoutMs = Number(opts.timeoutMs || 30000);
      const timer = window.setTimeout(function () {
        controller.abort();
      }, timeoutMs);
      request.signal = controller.signal;

      try {
        const response = await fetch(this.baseUrl + path, request);
        const text = await response.text();
        let payload = {};
        if (text) {
          try {
            payload = JSON.parse(text);
          } catch (error) {
            payload = { message: text };
          }
        }

        if (!response.ok) {
          const message = payload.message || payload.error || ('HTTP ' + response.status);
          const apiError = new Error(message);
          apiError.status = response.status;
          apiError.payload = payload;
          throw apiError;
        }

        return payload;
      } finally {
        window.clearTimeout(timer);
      }
    }

    async health() {
      return this.request('/api/health', { timeoutMs: 12000 });
    }

    async login(email, password) {
      const payload = await this.request('/api/v1/auth/login', {
        method: 'POST',
        body: { email, password },
        timeoutMs: 20000
      });
      const token = payload && payload.token ? payload.token.access_token : '';
      this.setToken(token);
      return payload;
    }

    async createRepairCase() {
      return this.request('/api/v1/repair-cases', {
        method: 'POST',
        body: {
          title: 'Riconoscimento immagini Re-born',
          description: 'Foto caricate dalla demo minimale per identificare il pezzo e decidere il percorso di sostituzione.',
          category: 'home_appliance'
        },
        timeoutMs: 20000
      });
    }

    async uploadRepairAttachment(caseId, file) {
      const data = new FormData();
      data.append('file', file, file.name || 'immagine.jpg');
      data.append('kind', 'diagnostic_photo');

      return this.request('/api/v1/repair-cases/' + encodeURIComponent(caseId) + '/attachments', {
        method: 'POST',
        body: data,
        timeoutMs: 90000
      });
    }

    async requestRecognition(caseId, attachmentIds) {
      return this.request('/api/v1/repair-cases/' + encodeURIComponent(caseId) + '/recognition-jobs', {
        method: 'POST',
        body: { attachment_ids: attachmentIds },
        timeoutMs: 180000
      });
    }
  }

  window.REBORN_API = new RebornApiClient('');
})();
