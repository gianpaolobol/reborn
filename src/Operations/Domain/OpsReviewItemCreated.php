<?php

declare(strict_types=1);

namespace Reborn\Operations\Domain;

use Reborn\Shared\Domain\DomainEvent;

final class OpsReviewItemCreated implements DomainEvent
{
    public function __construct(
        private readonly string $reviewItemId,
        private readonly string $category,
        private readonly string $priority,
        private readonly string $createdBy,
        private readonly string $occurredAt,
    ) {
    }

    public function name(): string
    {
        return 'ops.review_item_created';
    }

    public function payload(): array
    {
        return ['review_item_id' => $this->reviewItemId, 'category' => $this->category, 'priority' => $this->priority, 'created_by' => $this->createdBy];
    }

    public function occurredAt(): string
    {
        return $this->occurredAt;
    }
}
