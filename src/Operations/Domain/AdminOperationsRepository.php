<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

use Reborn\Identity\Domain\User;

interface AdminOperationsRepository
{
    /** @param array<string, mixed> $payload */
    public function createReviewItem(User $actor, array $payload): OpsReviewItem;

    /** @return list<OpsReviewItem> */
    public function listReviewItems(?string $status = null, ?string $priority = null): array;

    public function findReviewItem(string $id): ?OpsReviewItem;

    public function assignReviewItem(string $id, User $actor, ?string $assignedTo): OpsReviewItem;

    /** @param array<string, mixed> $payload */
    public function recordModerationAction(string $reviewItemId, User $actor, array $payload): OpsModerationAction;

    /** @return list<OpsModerationAction> */
    public function listModerationActions(string $reviewItemId): array;

    /** @param array<string, mixed> $payload */
    public function createEscalation(string $reviewItemId, User $actor, array $payload): OpsEscalation;

    /** @return list<OpsEscalation> */
    public function listEscalations(?string $status = null): array;

    /** @param array<string, mixed> $payload */
    public function resolveReviewItem(string $id, User $actor, array $payload): OpsReviewItem;

    /** @param array<string, mixed> $payload */
    public function audit(User $actor, string $action, string $subjectType, string $subjectId, array $payload): void;

    /** @return array<string, mixed> */
    public function summary(): array;
}
