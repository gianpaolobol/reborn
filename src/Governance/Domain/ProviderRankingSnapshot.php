<?php

declare(strict_types=1);

namespace Reborn\Governance\Domain;

final class ProviderRankingSnapshot
{
    /** @param list<array<string, mixed>> $ranking @param array<string, mixed> $policy */
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly string $rankingFormulaVersion,
        public readonly int $providerCount,
        public readonly array $ranking,
        public readonly array $policy,
        public readonly string $createdBy,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $ranking = json_decode((string) ($row['ranking_json'] ?? '[]'), true);
        if (!is_array($ranking)) {
            $ranking = [];
        }
        $policy = json_decode((string) ($row['policy_json'] ?? '{}'), true);
        if (!is_array($policy)) {
            $policy = [];
        }

        return new self(
            (string) $row['id'],
            (string) $row['status'],
            (string) $row['ranking_formula_version'],
            (int) $row['provider_count'],
            $ranking,
            $policy,
            (string) $row['created_by'],
            (string) $row['created_at'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'ranking_formula_version' => $this->rankingFormulaVersion,
            'provider_count' => $this->providerCount,
            'ranking_json' => $this->ranking,
            'policy_json' => $this->policy,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }
}
