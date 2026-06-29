# Architecture — Domain Events

Gli eventi di dominio permettono a Re-born di apprendere da ogni azione.

## Repair Domain Events

- RepairCaseCreated
- RepairCaseSubmitted
- RepairCaseImageUploaded
- ProductIdentified
- ComponentIdentified
- RepairPathRecommended
- RepairPathSelected
- RepairValidated
- RepairFailed

## AI Domain Events

- AIRecognitionRequested
- AIRecognitionCompleted
- AIRecognitionLowConfidence
- AIReconstructionRequested
- AIReconstructionCompleted
- AIOutputRejected

## Knowledge Domain Events

- ProductCreated
- ComponentCreated
- CompatibilityAdded
- FailureModeMapped
- RepairDNAUpdated
- RepairScoreUpdated

## Marketplace Domain Events

- QuoteRequested
- QuoteSubmitted
- QuoteAccepted
- PrintOrderCreated
- PrintOrderCompleted
- MarketplaceFeeCalculated
- RoyaltySplitCalculated

## Provider Domain Events

- ProviderRegistered
- ProviderVerified
- PrinterAdded
- MaterialCapabilityAdded
- ProviderRatingUpdated

## Wallet Domain Events

- WalletCreated
- RepairCreditsPurchased
- RoyaltyAccrued
- ProviderPayoutScheduled
- BountyFunded
- BountyPaid

## Company Domain Events

- CompanyRegistered
- OfficialPartPublished
- ProductCatalogImported
- ESGMetricUpdated

## Regola

Gli eventi non sono logging tecnico. Sono memoria del sistema e alimentano Knowledge Graph, Trust Engine e analytics.
