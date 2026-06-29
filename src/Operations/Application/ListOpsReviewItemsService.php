<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Operations\Domain\AdminOperationsRepository;

final class ListOpsReviewItemsService
{
    public function __construct(private readonly AdminOperationsRepository $repository)
    {
    }

    public function handle(?string $status = null, ?string $priority = null): array
    {
        return ['review_items' => array_map(static fn($item): array => $item->toArray(), $this->repository->listReviewItems($status, $priority))];
    }
}
