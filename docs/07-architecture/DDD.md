# Domain Driven Design

## Bounded Context

1. Identity Domain
2. Repair Domain
3. AI Domain
4. Knowledge Domain
5. Marketplace Domain
6. Provider Domain
7. Wallet Domain
8. Company Domain

## Repair Domain
Aggregate root: RepairCase.

Entità: RepairCase, RepairRequest, RepairStep, RepairFeedback, InstallationResult.

## AI Domain
Aggregate root: AIJob.

Entità: RecognitionTask, ReconstructionTask, OptimizationTask, AIResult.

## Knowledge Domain
Aggregate root principali: Product, Part, RepairDNA.

Entità: Brand, Product, Component, Part, PartVersion, Compatibility, FailureMode, RepairMethod, RepairScore.

## Marketplace Domain
Aggregate root: PrintOrder.

Entità: PrintQuote, PrintOrder, OrderItem, MarketplaceFee, RoyaltySplit.

## Provider Domain
Aggregate root: Provider.

Entità: ProviderProfile, Printer, MaterialCapability, ServiceArea, AvailabilitySlot.

## Wallet Domain
Aggregate root: Wallet e Bounty.

Entità: WalletTransaction, RepairCredit, RoyaltyAccount, Payout.

## Company Domain
Aggregate root: Company.

Entità: BrandPortal, ProductCatalog, OfficialPart, ESGReport, WhiteLabelInstance.
