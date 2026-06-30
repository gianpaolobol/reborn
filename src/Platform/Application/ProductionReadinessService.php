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
            'geometry_printability' => $this->geometryPrintabilityCheck(),
            'provider_routing' => $this->providerRoutingCheck(),
            'dispatch_governance' => $this->dispatchGovernanceCheck(),
            'customer_care_governance' => $this->customerCareGovernanceCheck(),
            'sustainability_impact' => $this->sustainabilityImpactCheck(),
            'investor_reporting' => $this->investorReportingCheck(),
            'demo_walkthrough' => $this->demoWalkthroughCheck(),
            'pilot_launch' => $this->pilotLaunchCheck(),
            'public_pilot' => $this->publicPilotCheck(),
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
            'checklist_version' => 'production_readiness_v20_step42',
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
                'CAD/geometry validation, printability findings and human review decisions are completed before provider routing or maker publication',
                'provider routing, dispatch tracking and proof-of-repair governance are reviewed before real fulfilment operations',
                'customer acceptance, warranty placeholders and post-repair support workflows are reviewed before beta customer commitments',
                'sustainability impact, circularity factors and repair outcome intelligence are reviewed before external environmental claims',
                'investor demo KPIs, board report narrative and caveats are reviewed before external fundraising use',
                'guided demo walkthrough script, feedback capture and readiness caveats are reviewed before partner/investor presentations',
                'demo data room, pilot launch checklist, stakeholder feedback and go/no-go decision are reviewed before any private beta commitment',
                'public pilot surfaces, external intake, lead scoring and real-world validation cases are reviewed before inviting external stakeholders at scale',
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
            'step_32_status' => 'CAD/geometry validation, printability governance and human review workflow v1 implemented.',
            'step_33_status' => 'Provider capability, machine profile and fulfilment routing governance v1 implemented.',
            'step_34_status' => 'Fulfilment dispatch, shipment tracking and proof-of-repair governance v1 implemented.',
            'step_35_status' => 'Customer acceptance, warranty placeholder and post-repair support governance v1 implemented.',
            'step_36_status' => 'Sustainability impact, circularity metrics and repair outcome intelligence v1 implemented.',
            'step_37_status' => 'Investor demo KPI narrative and board reporting governance v1 implemented.',
            'step_40_status' => 'Demo mode, guided repair journey and investor walkthrough governance v1 implemented.',
            'step_41_status' => 'Demo data room, pilot launch pack and stakeholder feedback loop v1 implemented.',
            'step_42_status' => 'Public pilot demo, partner/provider/maker intake and real-world validation pack v1 implemented.',
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
                'status' => $count >= 34 ? 'ok' : 'warn',
                'executed_count' => $count,
                'latest' => $latest ?: null,
                'message' => $count >= 34 ? 'All MVP hardening, governance, marketplace, maker economy, AI governance, geometry, routing, dispatch, customer care, sustainability, investor, demo, pilot launch and public pilot migrations are present.' : 'Some migrations may still need to run.',
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
    private function geometryPrintabilityCheck(): array
    {
        try {
            $tables = ['platform_geometry_validation_profiles', 'platform_geometry_assets', 'platform_geometry_validation_runs', 'platform_printability_rules', 'platform_printability_findings', 'platform_geometry_review_items', 'platform_geometry_governance_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $profiles = 0;
            $rules = 0;
            $assets = 0;
            $runs = 0;
            if ($missing === []) {
                $profiles = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_geometry_validation_profiles WHERE status = 'active'")->fetchColumn();
                $rules = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_printability_rules WHERE status = 'active'")->fetchColumn();
                $assets = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_geometry_assets')->fetchColumn();
                $runs = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_geometry_validation_runs')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($profiles > 0 && $rules > 0 && $assets > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'CAD/geometry validation and printability governance tables are available. Real mesh/CAD kernel analysis remains out of scope for the local pilot.' : 'Geometry printability governance tables are not fully migrated yet.',
                'validation_profiles' => $profiles,
                'printability_rules' => $rules,
                'geometry_assets' => $assets,
                'validation_runs' => $runs,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Geometry printability governance checks are not readable yet.', 'error' => $exception->getMessage()];
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

    /** @return array<string, mixed> */
    private function providerRoutingCheck(): array
    {
        try {
            $tables = ['platform_provider_capability_profiles', 'platform_machine_profiles', 'platform_routing_policies', 'platform_fulfilment_routing_requests', 'platform_provider_routing_matches', 'platform_routing_review_items', 'platform_provider_routing_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $capabilities = 0;
            $machines = 0;
            $policies = 0;
            $requests = 0;
            if ($missing === []) {
                $capabilities = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_provider_capability_profiles WHERE status = 'active'")->fetchColumn();
                $machines = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_machine_profiles WHERE status = 'active'")->fetchColumn();
                $policies = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_routing_policies WHERE status = 'active'")->fetchColumn();
                $requests = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_fulfilment_routing_requests')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($capabilities > 0 && $machines > 0 && $policies > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Provider capability, machine profile and fulfilment routing governance tables are available. Real capacity booking remains out of scope for the local pilot.' : 'Provider routing governance tables are not fully migrated yet.',
                'provider_capabilities' => $capabilities,
                'machine_profiles' => $machines,
                'routing_policies' => $policies,
                'routing_requests' => $requests,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Provider routing governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function dispatchGovernanceCheck(): array
    {
        try {
            $tables = ['platform_dispatch_policies', 'platform_fulfilment_dispatches', 'platform_shipment_tracking_events', 'platform_proof_of_repair_records', 'platform_dispatch_review_items', 'platform_dispatch_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $policies = 0;
            $dispatches = 0;
            $trackingEvents = 0;
            $proofs = 0;
            if ($missing === []) {
                $policies = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_dispatch_policies WHERE status = 'active'")->fetchColumn();
                $dispatches = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_fulfilment_dispatches')->fetchColumn();
                $trackingEvents = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_shipment_tracking_events')->fetchColumn();
                $proofs = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_proof_of_repair_records')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? ($policies > 0 ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Dispatch, shipment tracking and proof-of-repair governance tables are available. Real courier booking and return logistics remain out of scope for the local pilot.' : 'Dispatch governance tables are not fully migrated yet.',
                'dispatch_policies' => $policies,
                'dispatches' => $dispatches,
                'tracking_events' => $trackingEvents,
                'proof_of_repair_records' => $proofs,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Dispatch governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function customerCareGovernanceCheck(): array
    {
        try {
            $tables = ['platform_customer_acceptance_policies', 'platform_customer_acceptance_records', 'platform_warranty_policies', 'platform_warranty_cases', 'platform_post_repair_support_tickets', 'platform_customer_feedback_records', 'platform_post_repair_review_items', 'platform_post_repair_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $acceptancePolicies = 0;
            $warrantyPolicies = 0;
            $acceptanceRecords = 0;
            $supportTickets = 0;
            if ($missing === []) {
                $acceptancePolicies = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_customer_acceptance_policies WHERE status = 'active'")->fetchColumn();
                $warrantyPolicies = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_warranty_policies WHERE status = 'active'")->fetchColumn();
                $acceptanceRecords = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_customer_acceptance_records')->fetchColumn();
                $supportTickets = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_post_repair_support_tickets')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? (($acceptancePolicies > 0 && $warrantyPolicies > 0) ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Customer acceptance, warranty placeholder and post-repair support governance tables are available. Legal warranty terms, refunds and CRM integrations remain out of scope for the local pilot.' : 'Customer care governance tables are not fully migrated yet.',
                'acceptance_policies' => $acceptancePolicies,
                'warranty_policies' => $warrantyPolicies,
                'acceptance_records' => $acceptanceRecords,
                'support_tickets' => $supportTickets,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Customer care governance checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function sustainabilityImpactCheck(): array
    {
        try {
            $tables = ['platform_sustainability_factors', 'platform_repair_impact_records', 'platform_circularity_metric_snapshots', 'platform_repair_outcome_insights', 'platform_impact_review_items', 'platform_sustainability_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $factors = 0;
            $impacts = 0;
            $snapshots = 0;
            $insights = 0;
            if ($missing === []) {
                $factors = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_sustainability_factors WHERE status = 'active'")->fetchColumn();
                $impacts = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_repair_impact_records')->fetchColumn();
                $snapshots = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_circularity_metric_snapshots')->fetchColumn();
                $insights = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_repair_outcome_insights WHERE status IN ('open','investigating')")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? ($factors > 0 ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Sustainability impact, circularity metrics and repair outcome intelligence tables are available. Public environmental claims remain out of scope until methodology is validated.' : 'Sustainability impact tables are not fully migrated yet.',
                'sustainability_factors' => $factors,
                'repair_impact_records' => $impacts,
                'circularity_snapshots' => $snapshots,
                'open_insights' => $insights,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Sustainability impact checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function investorReportingCheck(): array
    {
        try {
            $tables = ['platform_investor_kpi_definitions', 'platform_investor_kpi_snapshots', 'platform_demo_narrative_sections', 'platform_board_reports', 'platform_board_report_sections', 'platform_board_report_evidence', 'platform_investor_demo_readiness_reviews', 'platform_investor_reporting_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $kpis = 0;
            $sections = 0;
            $reviews = 0;
            if ($missing === []) {
                $kpis = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_investor_kpi_definitions WHERE status = 'active'")->fetchColumn();
                $sections = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_demo_narrative_sections WHERE status = 'active'")->fetchColumn();
                $reviews = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_investor_demo_readiness_reviews")->fetchColumn();
            }

            return [
                'status' => $missing === [] ? ($kpis > 0 && $sections > 0 ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Investor demo KPI, narrative and board reporting governance tables are available. Metrics remain pilot/local evidence until externally validated.' : 'Investor reporting tables are not fully migrated yet.',
                'kpi_definitions' => $kpis,
                'narrative_sections' => $sections,
                'readiness_reviews' => $reviews,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Investor reporting checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function demoWalkthroughCheck(): array
    {
        try {
            $tables = ['platform_demo_modes', 'platform_guided_walkthrough_steps', 'platform_demo_sessions', 'platform_demo_session_events', 'platform_demo_feedback', 'platform_demo_readiness_reviews', 'platform_demo_walkthrough_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $modes = 0;
            $steps = 0;
            $reviews = 0;
            if ($missing === []) {
                $modes = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_demo_modes WHERE status = 'active'")->fetchColumn();
                $steps = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_guided_walkthrough_steps WHERE status = 'active'")->fetchColumn();
                $reviews = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_demo_readiness_reviews')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? ($modes > 0 && $steps >= 6 ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Demo mode, guided repair journey and investor walkthrough tables are available. Demo output remains local/pilot evidence with explicit caveats.' : 'Demo walkthrough tables are not fully migrated yet.',
                'active_modes' => $modes,
                'active_steps' => $steps,
                'readiness_reviews' => $reviews,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Demo walkthrough checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function pilotLaunchCheck(): array
    {
        try {
            $tables = ['platform_demo_data_room_assets', 'platform_pilot_launch_checklist_items', 'platform_stakeholder_feedback_loops', 'platform_stakeholder_feedback_items', 'platform_post_demo_reports', 'platform_pilot_go_no_go_decisions', 'platform_pilot_launch_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $assets = 0;
            $checklist = 0;
            $feedbackLoops = 0;
            $decisions = 0;
            if ($missing === []) {
                $assets = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_demo_data_room_assets WHERE status = 'ready'")->fetchColumn();
                $checklist = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_pilot_launch_checklist_items')->fetchColumn();
                $feedbackLoops = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_stakeholder_feedback_loops')->fetchColumn();
                $decisions = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_pilot_go_no_go_decisions')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? ($assets >= 3 && $checklist >= 4 && $feedbackLoops >= 1 ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Demo data room, pilot launch checklist and stakeholder feedback loop tables are available. Pilot launch remains conditional until legal, provider, fulfilment and production readiness are validated.' : 'Pilot launch tables are not fully migrated yet.',
                'ready_data_room_assets' => $assets,
                'checklist_items' => $checklist,
                'feedback_loops' => $feedbackLoops,
                'go_no_go_decisions' => $decisions,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Pilot launch checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }


    /** @return array<string, mixed> */
    private function publicPilotCheck(): array
    {
        try {
            $tables = ['platform_public_pilot_demo_pages', 'platform_external_pilot_intake_submissions', 'platform_real_world_validation_cases', 'platform_pilot_stakeholder_lead_scores', 'platform_public_pilot_audit_log'];
            $missing = [];
            foreach ($tables as $tableName) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
                $stmt->execute(['name' => $tableName]);
                if (!$stmt->fetchColumn()) {
                    $missing[] = $tableName;
                }
            }

            $pages = 0;
            $submissions = 0;
            $validationCases = 0;
            $leadScores = 0;
            if ($missing === []) {
                $pages = (int) $this->pdo->query("SELECT COUNT(*) FROM platform_public_pilot_demo_pages WHERE status = 'active'")->fetchColumn();
                $submissions = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_external_pilot_intake_submissions')->fetchColumn();
                $validationCases = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_real_world_validation_cases')->fetchColumn();
                $leadScores = (int) $this->pdo->query('SELECT COUNT(*) FROM platform_pilot_stakeholder_lead_scores')->fetchColumn();
            }

            return [
                'status' => $missing === [] ? ($pages >= 2 && $submissions >= 1 && $validationCases >= 1 && $leadScores >= 1 ? 'ok' : 'warn') : 'warn',
                'message' => $missing === [] ? 'Public pilot demo, external stakeholder intake, lead scoring and real-world validation case tables are available. Public pilot remains controlled and caveated until legal, provider, payments, fulfilment and production readiness are validated.' : 'Public pilot tables are not fully migrated yet.',
                'active_public_pages' => $pages,
                'intake_submissions' => $submissions,
                'validation_cases' => $validationCases,
                'lead_scores' => $leadScores,
                'missing_tables' => $missing,
            ];
        } catch (Throwable $exception) {
            return ['status' => 'warn', 'message' => 'Public pilot checks are not readable yet.', 'error' => $exception->getMessage()];
        }
    }

}
