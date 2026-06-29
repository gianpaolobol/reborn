<?php

declare(strict_types=1);

namespace Reborn\Marketplace\Application;

use Reborn\Provider\Domain\ProviderQuoteRequest;

final class RepairOrderAssembler
{
    /** @return array<string, mixed> */
    public function assemble(ProviderQuoteRequest $quote): array
    {
        $quoteJson = $quote->quote;
        $currency = (string) ($quoteJson['currency'] ?? 'EUR');
        $lineItems = is_array($quoteJson['line_items'] ?? null) ? $quoteJson['line_items'] : [];
        $provider = is_array($quoteJson['provider'] ?? null) ? $quoteJson['provider'] : ['id' => $quote->providerId];
        $scope = is_array($quoteJson['repair_scope'] ?? null) ? $quoteJson['repair_scope'] : [];
        $assumptions = is_array($quoteJson['assumptions'] ?? null) ? $quoteJson['assumptions'] : [];

        return [
            'currency' => $currency,
            'subtotal_cents' => (int) ($quoteJson['subtotal_cents'] ?? 0),
            'platform_fee_cents' => (int) ($quoteJson['platform_fee_cents'] ?? 0),
            'provider_payout_cents' => (int) ($quoteJson['provider_payout_cents'] ?? 0),
            'total_cents' => (int) ($quoteJson['total_cents'] ?? 0),
            'order_json' => [
                'source' => 'provider_quote_request',
                'quote_request_id' => $quote->id,
                'provider' => $provider,
                'repair_scope' => $scope,
                'line_items' => $lineItems,
                'assumptions' => $assumptions,
                'fulfilment' => [
                    'estimated_days' => (int) ($quoteJson['estimated_days'] ?? 0),
                    'provider_validation_required' => true,
                    'repair_success_definition' => 'The object returns to function, not merely file delivery or production output.',
                ],
                'quality_gate' => [
                    'confirm_dimensions_before_production',
                    'validate_material_and_tolerance',
                    'document provider acceptance before production',
                    'close order only after repair outcome is confirmed',
                ],
                'payment' => [
                    'provider' => 'mock',
                    'real_money_movement' => false,
                    'ready_for_stripe_or_paypal_adapter' => true,
                ],
            ],
        ];
    }
}
