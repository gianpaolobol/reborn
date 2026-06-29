<?php

declare(strict_types=1);

namespace Reborn\Shared\Domain;

interface DomainEvent
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function payload(): array;

    public function occurredAt(): string;
}
