<?php

declare(strict_types=1);

namespace Reborn\Provider\Infrastructure;

use PDO;
use Reborn\Provider\Domain\ProviderQuoteRequest;
use Reborn\Provider\Domain\ProviderQuoteRequestRepository;
use Reborn\Shared\Support\Uuid;

final class SqliteProviderQuoteRequestRepository implements ProviderQuoteRequestRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createEstimated(string $providerMatchId, string $repairCaseId, string $providerId, string $requestedBy, array $quote, string $expiresAt): ProviderQuoteRequest
    {
        $id = Uuid::v4();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare('INSERT INTO provider_quote_requests (id, provider_match_id, repair_case_id, provider_id, requested_by, status, quote_json, created_at, expires_at, accepted_at) VALUES (:id, :provider_match_id, :repair_case_id, :provider_id, :requested_by, :status, :quote_json, :created_at, :expires_at, :accepted_at)');
        $stmt->execute([
            'id' => $id,
            'provider_match_id' => $providerMatchId,
            'repair_case_id' => $repairCaseId,
            'provider_id' => $providerId,
            'requested_by' => $requestedBy,
            'status' => 'estimated',
            'quote_json' => json_encode($quote, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'expires_at' => $expiresAt,
            'accepted_at' => null,
        ]);

        $quoteRequest = $this->find($id);
        if ($quoteRequest === null) {
            throw new \RuntimeException('Provider quote request creation failed.');
        }

        return $quoteRequest;
    }

    public function find(string $id): ?ProviderQuoteRequest
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_quote_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ProviderQuoteRequest::fromRow($row) : null;
    }

    public function listByRepairCase(string $repairCaseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM provider_quote_requests WHERE repair_case_id = :repair_case_id ORDER BY created_at DESC');
        $stmt->execute(['repair_case_id' => $repairCaseId]);

        return array_map(static fn(array $row): ProviderQuoteRequest => ProviderQuoteRequest::fromRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
