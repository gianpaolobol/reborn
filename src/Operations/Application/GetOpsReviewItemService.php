<?php

declare(strict_types=1);

namespace Reborn\Operations\Application;

use Reborn\Operations\Domain\AdminOperationsRepository;
use Reborn\Shared\Http\NotFoundException;

final class GetOpsReviewItemService
{
    public function __construct(private readonly AdminOperationsRepository $repository)
    {
    }

    public function handle(string $id): array
    {
        $item = $this->repository->findReviewItem($id);
        if ($item === null) {
            throw new NotFoundException('Operations review item not found.');
        }

        return [
            'review_item' => $item->toArray(),
            'moderation_actions' => array_map(static fn($action): array => $action->toArray(), $this->repository->listModerationActions($id)),
        ];
    }
}
