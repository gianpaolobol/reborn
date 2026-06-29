<?php

declare(strict_types=1);

namespace Reborn\Provider\Application;

use InvalidArgumentException;
use Reborn\Provider\Domain\ProviderMatchRepository;
use Reborn\Provider\Domain\ProviderQuoteEstimated;
use Reborn\Provider\Domain\ProviderQuoteRequestRepository;
use Reborn\Provider\Domain\ProviderQuoteRequested;
use Reborn\Shared\Domain\EventBus;
use Reborn\Shared\Http\NotFoundException;
use Reborn\Shared\Http\ValidationException;

final class RequestProviderQuoteService
{
    public function __construct(
        private readonly ProviderMatchRepository $providerMatches,
        private readonly ProviderQuoteRequestRepository $quotes,
        private readonly ProviderQuoteEngine $engine,
        private readonly EventBus $eventBus,
    ) {
    }

    /** @return array{quote_request: array<string, mixed>} */
    public function handle(string $providerMatchId, string $providerId, string $requestedBy): array
    {
        $match = $this->providerMatches->find($providerMatchId);
        if ($match === null) {
            throw new NotFoundException('Provider match not found.');
        }
        if ($match->status !== 'completed') {
            throw new ValidationException(['provider_match_id' => ['Provider match must be completed before requesting a quote.']]);
        }

        $this->eventBus->publish(new ProviderQuoteRequested($providerMatchId, $match->repairCaseId, $providerId, $requestedBy, gmdate('c')));

        try {
            $quote = $this->engine->estimate($match, $providerId);
        } catch (InvalidArgumentException $exception) {
            throw new ValidationException(['provider_id' => [$exception->getMessage()]]);
        }

        $expiresAt = gmdate('c', time() + (7 * 24 * 60 * 60));
        $quoteRequest = $this->quotes->createEstimated($providerMatchId, $match->repairCaseId, $providerId, $requestedBy, $quote, $expiresAt);
        $this->eventBus->publish(new ProviderQuoteEstimated($quoteRequest->id, $match->repairCaseId, $providerId, (int) ($quote['total_cents'] ?? 0), gmdate('c')));

        return ['quote_request' => $quoteRequest->toArray()];
    }
}
