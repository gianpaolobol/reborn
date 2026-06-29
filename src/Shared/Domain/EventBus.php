<?php

declare(strict_types=1);

namespace Reborn\Shared\Domain;

use PDO;
use Reborn\Shared\Support\Uuid;

final class EventBus
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function publish(DomainEvent $event): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO domain_events (id, name, payload, occurred_at) VALUES (:id, :name, :payload, :occurred_at)');
        $stmt->execute([
            'id' => Uuid::v4(),
            'name' => $event->name(),
            'payload' => json_encode($event->payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'occurred_at' => $event->occurredAt(),
        ]);
    }
}
