<?php

declare(strict_types=1);

namespace Reborn\Platform\Application;

use PDO;
use Reborn\Shared\Http\ValidationException;
use Reborn\Shared\Support\Uuid;

final class CustomerCareGovernanceService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'summary' => [
                'acceptance_policies' => $this->count('platform_customer_acceptance_policies', "status = 'active'"),
                'acceptance_records' => $this->count('platform_customer_acceptance_records'),
                'pending_acceptance' => $this->count('platform_customer_acceptance_records', "status IN ('pending_acceptance','reminder_due')"),
                'accepted_repairs' => $this->count('platform_customer_acceptance_records', "status = 'accepted'"),
                'warranty_cases_open' => $this->count('platform_warranty_cases', "status IN ('open','investigating','rework_planned')"),
                'support_tickets_open' => $this->count('platform_post_repair_support_tickets', "status IN ('open','triaged','waiting_customer','waiting_provider')"),
                'feedback_records' => $this->count('platform_customer_feedback_records'),
                'open_reviews' => $this->count('platform_post_repair_review_items', "status IN ('open','assigned')"),
            ],
            'latest_acceptance_records' => $this->acceptanceRecords('all', 6),
            'open_warranty_cases' => $this->warrantyCases('active', 6),
            'open_support_tickets' => $this->supportTickets('active', 6),
            'latest_feedback' => $this->feedbackRecords(6),
            'open_reviews' => $this->reviewItems('active', 6),
            'policies' => $this->customerAcceptancePolicies('active'),
            'warranty_policies' => $this->warrantyPolicies('active'),
            'scope_note' => 'Step 35 governs customer acceptance, warranty placeholders and post-repair support for local/pilot operations. It does not create legal warranty terms, CRM integrations, refunds or real customer notifications.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function customerAcceptancePolicies(string $status = 'active'): array
    {
        return $this->policyRows('platform_customer_acceptance_policies', $status);
    }

    /** @return list<array<string, mixed>> */
    public function warrantyPolicies(string $status = 'active'): array
    {
        $sql = 'SELECT * FROM platform_warranty_policies';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY coverage_days DESC, name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $row): array {
            $row['coverage_days'] = (int) $row['coverage_days'];
            $row['exclusions'] = $this->decodeJson($row['exclusions_json'] ?? '[]');
            unset($row['exclusions_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    public function acceptanceRecords(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT a.*, p.summary AS proof_summary, p.quality_score AS proof_quality_score, d.dispatch_code, d.tracking_number FROM platform_customer_acceptance_records a LEFT JOIN platform_proof_of_repair_records p ON p.id = a.proof_of_repair_id LEFT JOIN platform_fulfilment_dispatches d ON d.id = a.dispatch_id";
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE a.status IN ('pending_acceptance','reminder_due','issue_reported','disputed')";
            } else {
                $sql .= ' WHERE a.status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeAcceptance'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createAcceptanceRecord(array $body, ?string $userId): array
    {
        $proofId = trim((string) ($body['proof_of_repair_id'] ?? '')) ?: null;
        $proof = $proofId ? $this->findProof($proofId) : $this->latestProof();
        if ($proofId !== null && $proof === null) {
            throw new ValidationException(['proof_of_repair_id' => ['Proof-of-repair record was not found.']]);
        }

        $id = Uuid::v4();
        $now = gmdate('c');
        $acceptanceCode = 'ACCEPT-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $customerEmail = trim((string) ($body['customer_email'] ?? 'pilot.customer@reborn.local')) ?: 'pilot.customer@reborn.local';
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['customer_email' => ['A valid customer email is required.']]);
        }
        $dueAt = trim((string) ($body['due_at'] ?? gmdate('c', time() + 7 * 86400))) ?: gmdate('c', time() + 7 * 86400);
        $dispatchId = trim((string) ($body['dispatch_id'] ?? ($proof['dispatch_id'] ?? ''))) ?: null;

        $stmt = $this->pdo->prepare('INSERT INTO platform_customer_acceptance_records (id, acceptance_code, proof_of_repair_id, dispatch_id, repair_case_id, repair_order_id, customer_user_id, customer_email, status, acceptance_decision, satisfaction_score, issue_summary, evidence_json, requested_at, decided_at, due_at, created_by, created_at, updated_at) VALUES (:id, :acceptance_code, :proof_of_repair_id, :dispatch_id, :repair_case_id, :repair_order_id, :customer_user_id, :customer_email, :status, :acceptance_decision, :satisfaction_score, :issue_summary, :evidence_json, :requested_at, :decided_at, :due_at, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            'id' => $id,
            'acceptance_code' => $acceptanceCode,
            'proof_of_repair_id' => $proof['id'] ?? $proofId,
            'dispatch_id' => $dispatchId,
            'repair_case_id' => trim((string) ($body['repair_case_id'] ?? '')) ?: null,
            'repair_order_id' => trim((string) ($body['repair_order_id'] ?? '')) ?: null,
            'customer_user_id' => trim((string) ($body['customer_user_id'] ?? '')) ?: null,
            'customer_email' => $customerEmail,
            'status' => 'pending_acceptance',
            'acceptance_decision' => null,
            'satisfaction_score' => null,
            'issue_summary' => null,
            'evidence_json' => json_encode($body['evidence'] ?? ['channel' => 'pilot_console', 'proof_available' => $proof !== null], JSON_THROW_ON_ERROR),
            'requested_at' => $now,
            'decided_at' => null,
            'due_at' => $dueAt,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('acceptance_requested', 'customer_acceptance', $id, sprintf('Customer acceptance %s requested.', $acceptanceCode), ['proof_of_repair_id' => $proof['id'] ?? null, 'dispatch_id' => $dispatchId], $userId);

        return $this->requireAcceptance($id);
    }

    /** @return array<string, mixed> */
    public function recordCustomerDecision(string $id, array $body, ?string $userId): array
    {
        $acceptance = $this->requireAcceptance($id);
        $decision = trim((string) ($body['decision'] ?? 'accepted')) ?: 'accepted';
        $allowed = ['accepted', 'accepted_with_notes', 'rejected_with_issue', 'needs_rework', 'disputed'];
        if (!in_array($decision, $allowed, true)) {
            throw new ValidationException(['decision' => ['Unsupported customer acceptance decision.']]);
        }
        $score = $body['satisfaction_score'] ?? ($decision === 'accepted' ? 5 : 2);
        $score = max(1, min(5, (int) $score));
        $issueSummary = trim((string) ($body['issue_summary'] ?? ($decision === 'accepted' || $decision === 'accepted_with_notes' ? '' : 'Customer reported a fit/function issue after repair.'))) ?: null;
        $status = in_array($decision, ['accepted', 'accepted_with_notes'], true) ? 'accepted' : 'issue_reported';
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('UPDATE platform_customer_acceptance_records SET status = :status, acceptance_decision = :decision, satisfaction_score = :score, issue_summary = :issue_summary, evidence_json = :evidence_json, decided_at = :decided_at, updated_at = :updated_at WHERE id = :id');
        $evidence = $this->decodeJson($acceptance['evidence_json'] ?? '{}');
        $evidence['customer_decision'] = $body['evidence'] ?? ['channel' => 'pilot_console', 'recorded_by' => $userId];
        $stmt->execute([
            'status' => $status,
            'decision' => $decision,
            'score' => $score,
            'issue_summary' => $issueSummary,
            'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'decided_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);

        if (!empty($acceptance['proof_of_repair_id'])) {
            $proofStatus = $status === 'accepted' ? 'accepted' : 'issue_reported';
            $stmt = $this->pdo->prepare('UPDATE platform_proof_of_repair_records SET customer_acceptance_status = :status, customer_notes = :notes, updated_at = :updated_at WHERE id = :id');
            $stmt->execute(['status' => $proofStatus, 'notes' => $issueSummary, 'updated_at' => $now, 'id' => $acceptance['proof_of_repair_id']]);
        }

        $supportTicket = null;
        $warrantyCase = null;
        if ($status !== 'accepted' || $score <= 3) {
            $supportTicket = $this->createSupportTicket([
                'acceptance_record_id' => $id,
                'dispatch_id' => $acceptance['dispatch_id'] ?? null,
                'customer_email' => $acceptance['customer_email'] ?? 'pilot.customer@reborn.local',
                'priority' => $decision === 'disputed' ? 'high' : 'medium',
                'category' => 'post_repair_issue',
                'subject' => 'Customer reported an issue after repair',
                'message' => $issueSummary ?: 'Customer decision requires follow-up.',
            ], $userId);
            if (in_array($decision, ['rejected_with_issue', 'needs_rework', 'disputed'], true)) {
                $warrantyCase = $this->createWarrantyCase([
                    'acceptance_record_id' => $id,
                    'proof_of_repair_id' => $acceptance['proof_of_repair_id'] ?? null,
                    'dispatch_id' => $acceptance['dispatch_id'] ?? null,
                    'severity' => $decision === 'disputed' ? 'high' : 'medium',
                    'claim_type' => 'customer_acceptance_issue',
                    'claim_summary' => $issueSummary ?: 'Customer acceptance issue requires warranty review.',
                ], $userId);
            }
            $this->createReviewItem('customer_acceptance', $id, $decision === 'disputed' ? 'high' : 'medium', 'Customer acceptance decision requires operator follow-up.', $userId);
        }

        $this->audit('customer_decision_recorded', 'customer_acceptance', $id, sprintf('Customer acceptance decision %s recorded.', $decision), ['status' => $status, 'satisfaction_score' => $score], $userId);

        return [
            'acceptance_record' => $this->requireAcceptance($id),
            'support_ticket' => $supportTicket,
            'warranty_case' => $warrantyCase,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function warrantyCases(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT wc.*, p.name AS policy_name, a.acceptance_code FROM platform_warranty_cases wc LEFT JOIN platform_warranty_policies p ON p.id = wc.policy_id LEFT JOIN platform_customer_acceptance_records a ON a.id = wc.acceptance_record_id';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE wc.status IN ('open','investigating','rework_planned')";
            } else {
                $sql .= ' WHERE wc.status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY wc.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'normalizeWarrantyCase'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function createWarrantyCase(array $body, ?string $userId): array
    {
        $acceptanceId = trim((string) ($body['acceptance_record_id'] ?? '')) ?: null;
        $acceptance = $acceptanceId ? $this->requireAcceptance($acceptanceId) : null;
        $policyId = trim((string) ($body['policy_id'] ?? '')) ?: $this->defaultWarrantyPolicyId();
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'WARRANTY-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $claimSummary = trim((string) ($body['claim_summary'] ?? 'Pilot warranty review opened for post-repair issue.')) ?: 'Pilot warranty review opened for post-repair issue.';

        $stmt = $this->pdo->prepare('INSERT INTO platform_warranty_cases (id, warranty_code, acceptance_record_id, proof_of_repair_id, dispatch_id, policy_id, status, severity, claim_type, claim_summary, resolution_summary, evidence_json, opened_by, assigned_to, created_at, updated_at, resolved_at) VALUES (:id, :warranty_code, :acceptance_record_id, :proof_of_repair_id, :dispatch_id, :policy_id, :status, :severity, :claim_type, :claim_summary, :resolution_summary, :evidence_json, :opened_by, :assigned_to, :created_at, :updated_at, :resolved_at)');
        $stmt->execute([
            'id' => $id,
            'warranty_code' => $code,
            'acceptance_record_id' => $acceptanceId,
            'proof_of_repair_id' => trim((string) ($body['proof_of_repair_id'] ?? ($acceptance['proof_of_repair_id'] ?? ''))) ?: null,
            'dispatch_id' => trim((string) ($body['dispatch_id'] ?? ($acceptance['dispatch_id'] ?? ''))) ?: null,
            'policy_id' => $policyId,
            'status' => 'open',
            'severity' => trim((string) ($body['severity'] ?? 'medium')) ?: 'medium',
            'claim_type' => trim((string) ($body['claim_type'] ?? 'fit_or_function_issue')) ?: 'fit_or_function_issue',
            'claim_summary' => $claimSummary,
            'resolution_summary' => null,
            'evidence_json' => json_encode($body['evidence'] ?? ['channel' => 'pilot_console'], JSON_THROW_ON_ERROR),
            'opened_by' => $userId,
            'assigned_to' => trim((string) ($body['assigned_to'] ?? '')) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
            'resolved_at' => null,
        ]);

        $this->audit('warranty_case_opened', 'warranty_case', $id, sprintf('Warranty case %s opened.', $code), ['acceptance_record_id' => $acceptanceId, 'policy_id' => $policyId], $userId);
        $this->createReviewItem('warranty_case', $id, trim((string) ($body['severity'] ?? 'medium')) ?: 'medium', 'Warranty case requires operator triage.', $userId);
        return $this->requireWarrantyCase($id);
    }

    /** @return array<string, mixed> */
    public function updateWarrantyCaseStatus(string $id, array $body, ?string $userId): array
    {
        $this->requireWarrantyCase($id);
        $status = trim((string) ($body['status'] ?? 'investigating')) ?: 'investigating';
        $allowed = ['open', 'investigating', 'rework_planned', 'resolved', 'rejected', 'closed'];
        if (!in_array($status, $allowed, true)) {
            throw new ValidationException(['status' => ['Unsupported warranty case status.']]);
        }
        $resolution = trim((string) ($body['resolution_summary'] ?? 'Updated from Step 35 pilot console.')) ?: null;
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_warranty_cases SET status = :status, resolution_summary = :resolution_summary, updated_at = :updated_at, resolved_at = :resolved_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'resolution_summary' => $resolution,
            'updated_at' => $now,
            'resolved_at' => in_array($status, ['resolved', 'rejected', 'closed'], true) ? $now : null,
            'id' => $id,
        ]);
        $this->audit('warranty_case_status_updated', 'warranty_case', $id, sprintf('Warranty case status changed to %s.', $status), ['resolution_summary' => $resolution], $userId);
        return $this->requireWarrantyCase($id);
    }

    /** @return list<array<string, mixed>> */
    public function supportTickets(string $status = 'all', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT t.*, a.acceptance_code, wc.warranty_code FROM platform_post_repair_support_tickets t LEFT JOIN platform_customer_acceptance_records a ON a.id = t.acceptance_record_id LEFT JOIN platform_warranty_cases wc ON wc.id = t.warranty_case_id';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE t.status IN ('open','triaged','waiting_customer','waiting_provider')";
            } else {
                $sql .= ' WHERE t.status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function createSupportTicket(array $body, ?string $userId): array
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $code = 'SUPPORT-' . strtoupper(substr(str_replace('-', '', $id), 0, 10));
        $customerEmail = trim((string) ($body['customer_email'] ?? 'pilot.customer@reborn.local')) ?: 'pilot.customer@reborn.local';
        $subject = trim((string) ($body['subject'] ?? 'Post-repair support request')) ?: 'Post-repair support request';
        $message = trim((string) ($body['message'] ?? 'Customer requested post-repair assistance.')) ?: 'Customer requested post-repair assistance.';

        $stmt = $this->pdo->prepare('INSERT INTO platform_post_repair_support_tickets (id, ticket_code, acceptance_record_id, warranty_case_id, dispatch_id, customer_email, status, priority, category, subject, message, response_summary, created_by, assigned_to, created_at, updated_at, resolved_at) VALUES (:id, :ticket_code, :acceptance_record_id, :warranty_case_id, :dispatch_id, :customer_email, :status, :priority, :category, :subject, :message, :response_summary, :created_by, :assigned_to, :created_at, :updated_at, :resolved_at)');
        $stmt->execute([
            'id' => $id,
            'ticket_code' => $code,
            'acceptance_record_id' => trim((string) ($body['acceptance_record_id'] ?? '')) ?: null,
            'warranty_case_id' => trim((string) ($body['warranty_case_id'] ?? '')) ?: null,
            'dispatch_id' => trim((string) ($body['dispatch_id'] ?? '')) ?: null,
            'customer_email' => $customerEmail,
            'status' => 'open',
            'priority' => trim((string) ($body['priority'] ?? 'medium')) ?: 'medium',
            'category' => trim((string) ($body['category'] ?? 'post_repair_question')) ?: 'post_repair_question',
            'subject' => $subject,
            'message' => $message,
            'response_summary' => null,
            'created_by' => $userId,
            'assigned_to' => trim((string) ($body['assigned_to'] ?? '')) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
            'resolved_at' => null,
        ]);
        $this->audit('support_ticket_created', 'support_ticket', $id, sprintf('Support ticket %s created.', $code), ['category' => $body['category'] ?? 'post_repair_question'], $userId);
        return $this->requireSupportTicket($id);
    }

    /** @return array<string, mixed> */
    public function updateSupportTicketStatus(string $id, array $body, ?string $userId): array
    {
        $this->requireSupportTicket($id);
        $status = trim((string) ($body['status'] ?? 'triaged')) ?: 'triaged';
        $allowed = ['open', 'triaged', 'waiting_customer', 'waiting_provider', 'resolved', 'closed'];
        if (!in_array($status, $allowed, true)) {
            throw new ValidationException(['status' => ['Unsupported support ticket status.']]);
        }
        $summary = trim((string) ($body['response_summary'] ?? 'Updated from Step 35 pilot console.')) ?: null;
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_post_repair_support_tickets SET status = :status, response_summary = :summary, updated_at = :updated_at, resolved_at = :resolved_at WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'summary' => $summary,
            'updated_at' => $now,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $now : null,
            'id' => $id,
        ]);
        $this->audit('support_ticket_status_updated', 'support_ticket', $id, sprintf('Support ticket status changed to %s.', $status), ['response_summary' => $summary], $userId);
        return $this->requireSupportTicket($id);
    }

    /** @return list<array<string, mixed>> */
    public function feedbackRecords(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_customer_feedback_records ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['rating'] = (int) $row['rating'];
            $row['nps_score'] = $row['nps_score'] === null ? null : (int) $row['nps_score'];
            $row['follow_up_required'] = (bool) $row['follow_up_required'];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function recordFeedback(array $body, ?string $userId): array
    {
        $id = Uuid::v4();
        $rating = max(1, min(5, (int) ($body['rating'] ?? 5)));
        $nps = isset($body['nps_score']) ? max(0, min(10, (int) $body['nps_score'])) : null;
        $sentiment = $rating >= 4 ? 'positive' : ($rating <= 2 ? 'negative' : 'neutral');
        $followUp = ($rating <= 3 || ($nps !== null && $nps <= 6)) ? 1 : 0;
        $text = trim((string) ($body['feedback_text'] ?? 'Pilot customer feedback recorded.')) ?: 'Pilot customer feedback recorded.';
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_customer_feedback_records (id, acceptance_record_id, dispatch_id, customer_email, channel, rating, nps_score, sentiment, feedback_text, follow_up_required, created_by, created_at) VALUES (:id, :acceptance_record_id, :dispatch_id, :customer_email, :channel, :rating, :nps_score, :sentiment, :feedback_text, :follow_up_required, :created_by, :created_at)');
        $stmt->execute([
            'id' => $id,
            'acceptance_record_id' => trim((string) ($body['acceptance_record_id'] ?? '')) ?: null,
            'dispatch_id' => trim((string) ($body['dispatch_id'] ?? '')) ?: null,
            'customer_email' => trim((string) ($body['customer_email'] ?? 'pilot.customer@reborn.local')) ?: 'pilot.customer@reborn.local',
            'channel' => trim((string) ($body['channel'] ?? 'pilot_console')) ?: 'pilot_console',
            'rating' => $rating,
            'nps_score' => $nps,
            'sentiment' => trim((string) ($body['sentiment'] ?? $sentiment)) ?: $sentiment,
            'feedback_text' => $text,
            'follow_up_required' => $followUp,
            'created_by' => $userId,
            'created_at' => $now,
        ]);
        if ($followUp === 1) {
            $this->createReviewItem('customer_feedback', $id, 'medium', 'Customer feedback requires post-repair follow-up.', $userId);
        }
        $this->audit('customer_feedback_recorded', 'customer_feedback', $id, 'Customer feedback recorded.', ['rating' => $rating, 'nps_score' => $nps, 'follow_up_required' => (bool) $followUp], $userId);
        return $this->feedbackRecords(1)[0];
    }

    /** @return list<array<string, mixed>> */
    public function reviewItems(string $status = 'active', int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM platform_post_repair_review_items';
        $params = [];
        if ($status !== 'all') {
            if ($status === 'active') {
                $sql .= " WHERE status IN ('open','assigned')";
            } else {
                $sql .= ' WHERE status = :status';
                $params['status'] = $status;
            }
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function reviewPostRepairItem(string $id, array $body, ?string $userId): array
    {
        $decision = trim((string) ($body['decision'] ?? 'resolved_with_notes')) ?: 'resolved_with_notes';
        $notes = trim((string) ($body['notes'] ?? 'Reviewed from Step 35 pilot console.')) ?: null;
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('UPDATE platform_post_repair_review_items SET status = :status, decision = :decision, notes = :notes, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'status' => 'resolved',
            'decision' => $decision,
            'notes' => $notes,
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
        if ($stmt->rowCount() === 0) {
            throw new ValidationException(['id' => ['Post-repair review item was not found.']]);
        }
        $this->audit('post_repair_review_completed', 'post_repair_review', $id, 'Post-repair review item completed.', ['decision' => $decision], $userId);
        return $this->findReviewItem($id);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM platform_post_repair_audit_log ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $row): array {
            $row['metadata'] = $this->decodeJson($row['metadata_json'] ?? '{}');
            unset($row['metadata_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    private function policyRows(string $table, string $status): array
    {
        $sql = "SELECT * FROM {$table}";
        $params = [];
        if ($status !== 'all') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY priority ASC, name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $row): array {
            $row['priority'] = (int) $row['priority'];
            $row['rules'] = $this->decodeJson($row['rules_json'] ?? '{}');
            unset($row['rules_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed>|null */
    private function latestProof(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM platform_proof_of_repair_records ORDER BY CASE WHEN status = 'accepted' THEN 0 WHEN status = 'pending_review' THEN 1 ELSE 2 END, created_at DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findProof(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_proof_of_repair_records WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function requireAcceptance(string $id): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, p.summary AS proof_summary, p.quality_score AS proof_quality_score, d.dispatch_code, d.tracking_number FROM platform_customer_acceptance_records a LEFT JOIN platform_proof_of_repair_records p ON p.id = a.proof_of_repair_id LEFT JOIN platform_fulfilment_dispatches d ON d.id = a.dispatch_id WHERE a.id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['Customer acceptance record was not found.']]);
        }
        return $this->normalizeAcceptance($row);
    }

    /** @return array<string, mixed> */
    private function requireWarrantyCase(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT wc.*, p.name AS policy_name, a.acceptance_code FROM platform_warranty_cases wc LEFT JOIN platform_warranty_policies p ON p.id = wc.policy_id LEFT JOIN platform_customer_acceptance_records a ON a.id = wc.acceptance_record_id WHERE wc.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['Warranty case was not found.']]);
        }
        return $this->normalizeWarrantyCase($row);
    }

    /** @return array<string, mixed> */
    private function requireSupportTicket(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_post_repair_support_tickets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['Support ticket was not found.']]);
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function findReviewItem(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_post_repair_review_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ValidationException(['id' => ['Post-repair review item was not found.']]);
        }
        return $row;
    }

    private function defaultWarrantyPolicyId(): ?string
    {
        $stmt = $this->pdo->query("SELECT id FROM platform_warranty_policies WHERE status = 'active' ORDER BY coverage_days DESC LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id ? (string) $id : null;
    }

    /** @return array<string, mixed> */
    private function normalizeAcceptance(array $row): array
    {
        $row['satisfaction_score'] = $row['satisfaction_score'] === null ? null : (int) $row['satisfaction_score'];
        $row['proof_quality_score'] = $row['proof_quality_score'] === null ? null : (int) $row['proof_quality_score'];
        $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '{}');
        unset($row['evidence_json']);
        return $row;
    }

    /** @return array<string, mixed> */
    private function normalizeWarrantyCase(array $row): array
    {
        $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? '{}');
        unset($row['evidence_json']);
        return $row;
    }

    private function createReviewItem(string $entityType, string $entityId, string $priority, string $reason, ?string $userId): array
    {
        $id = Uuid::v4();
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('INSERT INTO platform_post_repair_review_items (id, related_entity_type, related_entity_id, status, priority, review_reason, decision, notes, created_by, reviewed_by, created_at, updated_at, reviewed_at) VALUES (:id, :related_entity_type, :related_entity_id, :status, :priority, :review_reason, :decision, :notes, :created_by, :reviewed_by, :created_at, :updated_at, :reviewed_at)');
        $stmt->execute([
            'id' => $id,
            'related_entity_type' => $entityType,
            'related_entity_id' => $entityId,
            'status' => 'open',
            'priority' => in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium',
            'review_reason' => $reason,
            'decision' => null,
            'notes' => null,
            'created_by' => $userId,
            'reviewed_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'reviewed_at' => null,
        ]);
        return $this->findReviewItem($id);
    }

    private function audit(string $action, string $entityType, ?string $entityId, string $message, array $metadata, ?string $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_post_repair_audit_log (id, action, entity_type, entity_id, message, metadata_json, created_by, created_at) VALUES (:id, :action, :entity_type, :entity_id, :message, :metadata_json, :created_by, :created_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
            'created_at' => gmdate('c'),
        ]);
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}" . ($where ? " WHERE {$where}" : '');
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** @return mixed */
    private function decodeJson(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
