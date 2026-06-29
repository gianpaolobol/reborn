<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Throwable;
use Reborn\Shared\Support\Uuid;

final class ProductionReadinessService
{
    /** @param array<string, mixed> $appConfig @param array<string, mixed> $databaseConfig @param array<string, mixed> $securityConfig */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $appConfig,
        private readonly array $databaseConfig,
        private readonly array $securityConfig,
        private readonly string $rootPath,
    ) {
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->databaseCheck(),
            'migrations' => $this->migrationsCheck(),
            'storage' => $this->storageCheck(),
            'configuration' => $this->configurationCheck(),
            'security' => $this->securityCheck(),
            'runtime' => $this->runtimeCheck(),
            'observability' => $this->observabilityCheck(),
            'backup' => $this->backupCheck(),
            'incident_response' => $this->incidentResponseCheck(),
            'notification_center' => $this->notificationCenterCheck(),
            'service_governance' => $this->serviceGovernanceCheck(),
            'privacy_governance' => $this->privacyGovernanceCheck(),
            'release_management' => $this->releaseManagementCheck(),
            'partner_onboarding' => $this->partnerOnboardingCheck(),
            'marketplace_revenue' => $this->marketplaceRevenueCheck(),
            'maker_economy' => $this->makerEconomyCheck(),
            'ai_pipeline_governance' => $this->aiPipelineGovernanceCheck(),
            'ai_provider_sandbox' => $this->aiProviderSandboxCheck(),
        ];

        $status = 'ready';
        foreach ($checks as $check) {
            if (($check['status'] ?? 'fail') === 'fail') {
                $status = 'not_ready';
                break;
            }
            if (($check['status'] ?? 'ok') === 'warn' && $status !== 'not_ready') {
                $status = 'degraded';
            }
        }

        return [
            'status' => $status,
            'environment' => $this->appConfig['env'] ?? 'unknown',
            'generated_at' => gmdate('c'),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    public function securityPolicy(): array
    {
        return [
            'policy_version' => 'production_readiness_v1',
            'security_headers_enabled' => (bool) ($this->securityConfig['security_headers_enabled'] ?? true),
            'rate_limit_enabled' => (bool) ($this->securityConfig['rate_limit_enabled'] ?? true),
            'rate_limit_max_requests' => (int) ($this->securityConfig['rate_limit_max_requests'] ?? 240),
            'rate_limit_window_seconds' => (int) ($this->securityConfig['rate_limit_window_seconds'] ?? 60),
            'max_upload_bytes' => (int) ($this->securityConfig['max_upload_bytes'] ?? 15728640),
            'headers' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'no-referrer',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
                'Cross-Origin-Resource-Policy' => 'same-origin',
                'Cache-Control' => 'no-store for API JSON responses',
            ],
            'notes' => [
                'MVP uses SQLite-backed fixed-window rate limiting for local and pilot readiness.',
                'CSP for the prototype remains intentionally deferred because the current static prototype uses inline handlers.',
                'Real payment webhooks remain out of scope until mock PaymentIntent is replaced with provider integrations.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function deployChecklist(): array
    {
        return [
            'checklist_version' => 'production_readiness_v12_step31',
            'items' => $this->securityConfig['production_checklist'] ?? [],
            'blocked_until' => [
                'APP_DEBUG=false is verified in the target environment',
                'real payment providers have signed webhook verification',
                'backup and restore procedure has been tested',
                'alert evaluation and status page workflow are verified',
                'notification dispatch and escalation workflow are verified',
                'SLA evaluation and operational policy attestations are reviewed',
                'privacy notices, consent capture, data subject request and retention dry-run are reviewed',
                'privacy/legal liability terms for repair outcomes are approved',
                'feature flags, release gates and pilot cohort rules are reviewed',
                'partner onboarding tasks, agreements, integrations and readiness reviews are approved',
                'marketplace fee policies, credit ledger and payout governance are reviewed before monetization',
                'maker model licensing, repair bounty rewards and royalty credit rules are reviewed before public maker onboarding',
                'AI provider usage, human review gates, dataset consent/licensing and quality evaluation are reviewed before real AI integrations',
                'AI provider adapters, job orchestration, provider costs, retry rules and artifact stubs are reviewed before any live external AI call',
            ],
            'step_21_status' => 'Observability dashboard, backup automation and deployment runbook v1 implemented.',
            'step_22_status' => 'Incident response, alert evaluation, maintenance windows and status page v1 implemented.',
            'step_23_status' => 'Notification center, mock delivery records and escalation workflow v1 implemented.',
            'step_24_status' => 'Service level objectives, SLA evaluations and operational policy governance v1 implemented.',
            'step_25_status' => 'Privacy notices, consent ledger, processing records, retention dry-run and data subject request workflow v1 implemented.',
            'step_26_status' => 'Beta release management, feature flags, release gates and pilot cohort readiness v1 implemented.',
            'step_27_status' => 'Enterprise and partner onboarding governance, agreements, integrations and readiness reviews v1 implemented.',
            'step_28_status' => 'Marketplace revenue governance, repair credits ledger and mock payout workflow v1 implemented.',
            'step_29_status' => 'Maker economy governance, model licensing, local royalty credits and repair bounty workflow v1 implemented.',
            'step_30_status' => 'AI pipeline governance, human-in-the-loop review, dataset governance and AI quality evaluation v1 implemented.',
            'step_31_status' => 'AI provider adapter sandbox, mock job orchestration, cost ledger and artifact stubs v1 implemented.',
        ];
    }

    /** @return array<string, mixed> */
    public function runtimeReport(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'app_env' => $this->appConfig['env'] ?? 'unknown',
            'app_debug' => (bool) ($this->appConfig['debug'] ?? false),
            'database_connection' => $this->databaseConfig['connection'] ?? 'unknown',
            'database_path' => ($this->databaseConfig['connection'] ?? 'sqlite') === 'sqlite' ? $this->databaseConfig['database'] : null,
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'sqlite3' => extension_loaded('sqlite3'),
                'json' => extension_loaded('json'),
                'fileinfo' => extension_loaded('fileinfo'),
            ],
            'storage' => [
                'logs_dir' => $this->rootPath . '/storage/logs',
                'uploads_dir' => $this->rootPath . '/storage/uploads',
                'database_dir' => $this->rootPath . '/storage/database',
                'free_bytes' => @disk_free_space($this->rootPath) ?: null,
            ],
        ];
    }

    public function recordSnapshot(?string $createdBy): array
    {
        $readiness = $this->readiness();
        $snapshot = [
            'id' => Uuid::v4(),
            'status' => $readiness['status'],
            'checks_json' => $readiness['checks'],
            'created_by' => $createdBy,
            'created_at' => gmdate('c'),
        ];

        $stmt = $this->pdo->prepare('INSERT INTO platform_readiness_snapshots (id, status, checks_json, created_by, created_at) VALUES (:id, :status, :checks_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => $snapshot['id'],
            'status' => $snapshot['status'],
            'checks_json' => json_encode($snapshot['checks_json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $snapshot['created_by'],
            'created_at' => $snapshot['created_at'],
        ]);

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function databaseCheck(): array
    {
        try {
            $value = $this->pdo->query('SELECT 1')->fetchColumn();
            return ['status' => ((int) $value) === 1 ? 'ok' : 'fail', 'message' => 'Database connection responds.'];
        } catch (Throwable $exception) {
            return ['status' => 'fail', 'message' => 'Database connection failed.', 'error' => $exception->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function migrationsCheck(): array
    {
        try {
            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            $latest = $this->pdo->query('SELECT filename FROM migrations ORDER BY executed_at DESC, id DESC LIMIT 1')->fetchColumn();
            return [
                'status' => $count >= 25 ? 'ok' : 'warn',
                'executed_count' => $count,
                'latest' => $latest ?: null,
                'message' => $count >= 25 ? 'All MVP hardening, governance, marketplace, maker economy, AI governance and AI provider sandbox migrations are present.' : 'Some migrations may still need to run.',
            ];
        } catch (Throwable $exception) {
            return ['status' => 'fail', 'message' => 'Migration metadata is unavailable.', 'error' => $exception->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function storageCheck(): array
    {
        $paths = [
            'storage' => $this->rootPath . '/storage',
            'logs' => $this->rootPath . '/storage/logs',
            'uploads' => $this->rootPath . '/storage/uploads',
            'database' => $this->rootPath . '/storage/database',
        ];

        $results = [];
        $hasFailure = false;
        foreach ($paths as $name => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $ok = is_dir($path) && is_writable($path);
            $results[$name] = ['path' => $path, 'writable' => $ok];
            if (!$ok) {
                $hasFailure = true;
            }
        }

        return ['status' => $hasFailure ? 'fail' : 'ok', 'paths' => $results];
    }

    /** @return array<string, mixed> */
    private function configurationCheck(): array
    {
        $env = (string) ($this->appConfig['env'] ?? 'development');
        $debug = (bool) ($this->appConfig['debug'] ?? true);
        $warnings = [];
        if ($env === 'production' && $debug) {
            $warnings[] = 'APP_DEBUG must be false in production.';
        }
        if ($env !== 'production') {
            $warnings[] = 'APP_ENV is not production; this is acceptable for local smoke tests.';
        }

        return [
            'status' => ($env === 'production' && $debug) ? 'fail' : ($warnings ? 'warn' : 'ok'),
            'app_env' => $env,
            'app_debug' => $debug,
            'warnings' => $warnings,
        ];
    }

    /** @return array<string, mixed> */
    private function securityCheck(): array
    {
        return [
            'status' => ((bool) ($this->securityConfig['security_headers_enabled'] ?? true) && (bool) ($this->securityConfig['rate_limit_enabled'] ?? true)) ? 'ok' : 'warn',
            'security_headers_enabled' => (bool) ($this->securityConfig['security_headers_enabled'] ?? true),
            'rate_limit_enabled' => (bool) ($this->securityConfig['rate_limit_enabled'] ?? true),
            'rate_limit_max_requests' => (int) ($this->securityConfig['rate_limit_max_requests'] ?? 240),
            'rate_limit_window_seconds' => (int) ($this->securityConfig['rate_limit_window_seconds'] ?? 60),
        ];
    }

    /** @return array<string, mixed> */
    private function observabilityCheck(): array
    {
        try {
            $table = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'platform_http_metrics'")->fetchColumn();
            if (!$table) {
                return ['status' => 'warn', 'message' => 'HTTP metrics table is not available yet. Run migrations.'];
            }

            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_http_metrics')->fetchColumn();
            $last = $this->pdo->query('SELECT occurred_at FROM platform_http_metrics ORDER BY occurred_at DESC LIMIT 1')->fetchColumn();
            return [
                'status' => 'ok',
                'message' => 'HTTP metrics recorder is available.',
                'recorded_requests' => $count,
                'last_request_at' => $last ?: null,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Observability metrics are not readable yet.', 'error' => $exception->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function backupCheck(): array
    {
        $backupDir = $this->rootPath . '/storage/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }

        $writable = is_dir($backupDir) && is_writable($backupDir);
        $latest = null;
        try {
            $latest = $this->pdo->query("SELECT created_at FROM platform_backup_runs WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1")->fetchColumn() ?: null;
        } catch (Throwable) {
            // Table may not exist before Step 21 migration. Keep this as a warning, not a hard failure.
        }

        if (!$writable) {
            return ['status' => 'fail', 'message' => 'Backup directory is not writable.', 'backup_dir' => $backupDir];
        }

        return [
            'status' => $latest ? 'ok' : 'warn',
            'message' => $latest ? 'At least one completed backup exists.' : 'Backup directory is writable, but no completed backup has been recorded yet.',
            'backup_dir' => $backupDir,
            'latest_completed_backup_at' => $latest,
        ];
    }


    /** @return array<string, mixed> */
    private function incidentResponseCheck(): array
    {
        try {
            $tables = ['platform_alert_rules', 'platform_alerts', 'platform_incidents', 'platform_status_updates', 'platform_maintenance_windows'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $rules = 0;
            if ($missing === []) {
                $rules = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_alert_rules WHERE enabled = 1')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? ($rules > 0 ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Incident response tables are available.' : 'Incident response tables are not fully migrated yet.',
                'enabled_alert_rules' => $rules,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Incident response checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function notificationCenterCheck(): array
    {
        try {
            $tables = ['platform_notification_channels', 'platform_notification_rules', 'platform_notification_deliveries', 'platform_escalation_policies', 'platform_escalation_runs'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $channels = 0;
            $rules = 0;
            $policies = 0;
            if ($missing === []) {
                $channels = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_notification_channels WHERE status = 'active'")->fetchColumn();
                $rules = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_notification_rules WHERE enabled = 1')->fetchColumn();
                $policies = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_escalation_policies WHERE enabled = 1')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($channels > 0 && $rules > 0 && $policies > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Notification and escalation tables are available.' : 'Notification center tables are not fully migrated yet.',
                'active_channels' => $channels,
                'enabled_notification_rules' => $rules,
                'enabled_escalation_policies' => $policies,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Notification center checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function serviceGovernanceCheck(): array
    {
        try {
            $tables = ['platform_sla_policies', 'platform_sla_evaluations', 'platform_operational_policies', 'platform_policy_attestations'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $slaPolicies = 0;
            $operationalPolicies = 0;
            if ($missing === []) {
                $slaPolicies = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_sla_policies WHERE enabled = 1')->fetchColumn();
                $operationalPolicies = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_operational_policies WHERE status IN ('active', 'draft')")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($slaPolicies > 0 && $operationalPolicies > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Service governance tables are available.' : 'Service governance tables are not fully migrated yet.',
                'enabled_sla_policies' => $slaPolicies,
                'operational_policies' => $operationalPolicies,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Service governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function privacyGovernanceCheck(): array
    {
        try {
            $tables = ['platform_privacy_notices', 'platform_consent_records', 'platform_data_processing_records', 'platform_retention_rules', 'platform_retention_evaluations', 'platform_data_subject_requests', 'platform_data_exports'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $notices = 0;
            $processingRecords = 0;
            $retentionRules = 0;
            if ($missing === []) {
                $notices = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_privacy_notices WHERE status IN ('draft', 'active')")->fetchColumn();
                $processingRecords = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_data_processing_records WHERE status IN ('draft', 'active')")->fetchColumn();
                $retentionRules = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_retention_rules WHERE enabled = 1')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($notices > 0 && $processingRecords > 0 && $retentionRules > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Privacy, consent and data governance tables are available.' : 'Privacy governance tables are not fully migrated yet.',
                'privacy_notices' => $notices,
                'processing_records' => $processingRecords,
                'enabled_retention_rules' => $retentionRules,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Privacy governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function releaseManagementCheck(): array
    {
        try {
            $tables = ['platform_feature_flags', 'platform_releases', 'platform_release_gates', 'platform_release_decisions', 'platform_pilot_cohorts', 'platform_pilot_participants'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $flags = 0;
            $releases = 0;
            $cohorts = 0;
            if ($missing === []) {
                $flags = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_feature_flags WHERE status IN ('enabled', 'beta')")->fetchColumn();
                $releases = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_releases WHERE status IN ('draft', 'evaluating', 'approved', 'blocked')")->fetchColumn();
                $cohorts = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_pilot_cohorts WHERE status IN ('draft', 'recruiting', 'active')")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($flags > 0 && $releases > 0 && $cohorts > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Release management and pilot readiness tables are available.' : 'Release management tables are not fully migrated yet.',
                'enabled_or_beta_feature_flags' => $flags,
                'active_releases' => $releases,
                'pilot_cohorts' => $cohorts,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Release management checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function partnerOnboardingCheck(): array
    {
        try {
            $tables = ['platform_partner_accounts', 'platform_partner_onboarding_tasks', 'platform_partner_agreements', 'platform_partner_integrations', 'platform_partner_readiness_reviews'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $partners = 0;
            $tasks = 0;
            $agreements = 0;
            if ($missing === []) {
                $partners = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_partner_accounts WHERE status IN ('prospect', 'onboarding', 'active')")->fetchColumn();
                $tasks = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_partner_onboarding_tasks')->fetchColumn();
                $agreements = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_partner_agreements')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($partners > 0 && $tasks > 0 && $agreements > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Partner onboarding governance tables are available.' : 'Partner onboarding tables are not fully migrated yet.',
                'partners' => $partners,
                'onboarding_tasks' => $tasks,
                'agreements' => $agreements,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Partner onboarding checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function marketplaceRevenueCheck(): array
    {
        try {
            $tables = ['platform_marketplace_fee_policies', 'platform_credit_accounts', 'platform_credit_transactions', 'platform_payout_accounts', 'platform_payout_runs', 'platform_payout_items', 'platform_revenue_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $feePolicies = 0;
            $creditAccounts = 0;
            $payoutAccounts = 0;
            if ($missing === []) {
                $feePolicies = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_marketplace_fee_policies WHERE status IN ('active', 'draft')")->fetchColumn();
                $creditAccounts = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_credit_accounts WHERE status IN ('active', 'pending')")->fetchColumn();
                $payoutAccounts = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_payout_accounts WHERE status IN ('active', 'pending')")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($feePolicies > 0 && $creditAccounts > 0 && $payoutAccounts > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Marketplace revenue, credits and payout governance tables are available.' : 'Marketplace revenue governance tables are not fully migrated yet.',
                'fee_policies' => $feePolicies,
                'credit_accounts' => $creditAccounts,
                'payout_accounts' => $payoutAccounts,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Marketplace revenue governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function makerEconomyCheck(): array
    {
        try {
            $tables = ['platform_maker_profiles', 'platform_model_assets', 'platform_model_licenses', 'platform_model_downloads', 'platform_model_royalty_events', 'platform_repair_bounties', 'platform_bounty_submissions', 'platform_maker_economy_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $makers = 0;
            $models = 0;
            $licenses = 0;
            $bounties = 0;
            if ($missing === []) {
                $makers = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_maker_profiles WHERE status IN ('onboarding', 'active')")->fetchColumn();
                $models = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_model_assets WHERE status IN ('submitted', 'in_review', 'approved')")->fetchColumn();
                $licenses = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_model_licenses WHERE status IN ('active', 'draft')")->fetchColumn();
                $bounties = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_repair_bounties WHERE status IN ('open', 'in_review')")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($makers > 0 && $models > 0 && $licenses > 0 && $bounties > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Maker economy, model licensing and repair bounty governance tables are available.' : 'Maker economy governance tables are not fully migrated yet.',
                'maker_profiles' => $makers,
                'model_assets' => $models,
                'model_licenses' => $licenses,
                'repair_bounties' => $bounties,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Maker economy governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function aiPipelineGovernanceCheck(): array
    {
        try {
            $tables = ['platform_ai_model_providers', 'platform_ai_pipeline_runs', 'platform_ai_human_reviews', 'platform_ai_dataset_items', 'platform_ai_quality_evaluations', 'platform_ai_safety_rules', 'platform_ai_governance_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $providers = 0;
            $runs = 0;
            $rules = 0;
            $datasetItems = 0;
            if ($missing === []) {
                $providers = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_ai_model_providers WHERE status IN ('mock', 'active')")->fetchColumn();
                $runs = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_ai_pipeline_runs WHERE status IN ('queued', 'running', 'in_review', 'approved', 'completed')")->fetchColumn();
                $rules = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_ai_safety_rules WHERE status = 'active'")->fetchColumn();
                $datasetItems = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_ai_dataset_items WHERE status IN ('candidate', 'approved')")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($providers > 0 && $runs > 0 && $rules > 0 && $datasetItems > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'AI pipeline governance, human review, dataset and safety rule tables are available.' : 'AI pipeline governance tables are not fully migrated yet.',
                'ai_model_providers' => $providers,
                'ai_pipeline_runs' => $runs,
                'ai_safety_rules' => $rules,
                'ai_dataset_items' => $datasetItems,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'AI pipeline governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function aiProviderSandboxCheck(): array
    {
        try {
            $tables = ['platform_ai_provider_adapters', 'platform_ai_orchestration_jobs', 'platform_ai_job_events', 'platform_ai_artifact_stubs', 'platform_ai_provider_cost_ledger', 'platform_ai_provider_sandbox_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $adapters = 0;
            $jobs = 0;
            $secretWarnings = 0;
            if ($missing === []) {
                $adapters = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_ai_provider_adapters WHERE status IN ('sandbox', 'ready')")->fetchColumn();
                $jobs = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_ai_orchestration_jobs")->fetchColumn();
                $secretWarnings = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_ai_provider_adapters WHERE requires_secret = 1 AND secret_status <> 'configured'")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($adapters > 0 && $jobs > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'AI provider adapter sandbox and job orchestration tables are available. Missing provider secrets remain warnings while adapters are mock/sandbox only.' : 'AI provider sandbox tables are not fully migrated yet.',
                'sandbox_adapters' => $adapters,
                'orchestration_jobs' => $jobs,
                'missing_provider_secrets' => $secretWarnings,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'AI provider sandbox checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function runtimeCheck(): array
    {
        $required = [
            'pdo' => extension_loaded('pdo'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'json' => extension_loaded('json'),
        ];
        $optional = [
            'sqlite3' => extension_loaded('sqlite3'),
            'fileinfo' => extension_loaded('fileinfo'),
        ];

        $missingRequired = array_keys(array_filter($required, static fn (bool $loaded): bool => !$loaded));
        $missingOptional = array_keys(array_filter($optional, static fn (bool $loaded): bool => !$loaded));

        return [
            'status' => $missingRequired ? 'fail' : ($missingOptional ? 'warn' : 'ok'),
            'php_version' => PHP_VERSION,
            'required_extensions' => $required,
            'optional_extensions' => $optional,
            'missing_required' => $missingRequired,
            'missing_optional' => $missingOptional,
            'message' => $missingRequired
                ? 'One or more required PHP extensions are missing.'
                : ($missingOptional ? 'Runtime is usable, but one or more optional extensions should be enabled before production.' : 'Runtime extensions are ready.'),
        ];
    }
}
