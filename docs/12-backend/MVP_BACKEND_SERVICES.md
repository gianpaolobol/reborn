# MVP Backend Services

## Identity Application Services

- RegisterUserService
- LoginUserService
- LogoutUserService
- AssignRoleService

## Repair Application Services

- CreateRepairCaseService
- UploadRepairPhotoService
- UpdateRepairDetailsService
- SubmitRepairCaseService
- GetRepairDiagnosisService
- GenerateRepairPathsService
- SelectRepairPathService
- RecordRepairOutcomeService

## Classification Services

- CreateRepairDnaDraftService
- UpdateClassificationService
- RequestMissingDataService
- FlagSafetyRiskService

## Provider Services

- UpsertProviderProfileService
- AddProviderCapabilityService
- CreateQuoteRequestService
- CreateQuoteService

## Maker / Marketplace Services

- UpsertMakerProfileService
- SubmitModelMetadataService
- ReviewModelService
- CreateBountyService
- SubmitBountySolutionService

## Knowledge Services

- RecordKnowledgeSignalService
- LinkRepairCaseToTaxonomyService
- RecordOutcomeLearningService

## Metrics Services

- RecordAnalyticsEventService
- BuildMvpMetricsDashboardService

## Rule

Controllers must remain thin. They parse input, call application services and render responses. Business decisions belong in domain/application services.
