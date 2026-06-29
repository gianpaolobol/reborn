# Traceability Matrix

| Ticket | Epic | User role | Main screen | Main data entity | Main API | Metric |
|---|---|---|---|---|---|---|
| RBN-001 | EPIC-01 | Guest | Register | User | POST /auth/register | user_registered |
| RBN-002 | EPIC-01 | User | Login | Session | POST /auth/login | user_logged_in |
| RBN-003 | EPIC-02 | Repair user | Start repair | RepairCase | POST /repair-cases | repair_case_created |
| RBN-004 | EPIC-02 | Repair user | Upload photos | RepairPhoto | POST /repair-cases/{id}/photos | photo_uploaded |
| RBN-005 | EPIC-02 | Repair user | Describe issue | RepairCase | PATCH /repair-cases/{id} | description_added |
| RBN-006 | EPIC-03 | Repair user | Review & submit | RepairDNA | POST /repair-cases/{id}/submit | case_submitted |
| RBN-007 | EPIC-03 | Admin | Classification console | Classification | PATCH /admin/classifications/{id} | classification_updated |
| RBN-008 | EPIC-04 | Repair user | Diagnosis summary | Classification | GET /repair-cases/{id}/diagnosis | diagnosis_viewed |
| RBN-009 | EPIC-04 | Repair user | Path comparison | RepairPath | POST /repair-cases/{id}/paths/select | repair_path_selected |
| RBN-010 | EPIC-06 | Provider | Provider onboarding | ProviderProfile | POST /providers | provider_created |
| RBN-011 | EPIC-06 | Repair user | Provider path | QuoteRequest | POST /repair-cases/{id}/quote-requests | quote_requested |
| RBN-012 | EPIC-06 | Provider | Quote creation | Quote | POST /provider/quotes | quote_created |
| RBN-013 | EPIC-05 | Maker | Maker onboarding | MakerProfile | POST /makers | maker_created |
| RBN-014 | EPIC-05 | Maker | Model submit | Model | POST /models | model_submitted |
| RBN-015 | EPIC-04 | Repair user | Bounty path | Bounty | POST /repair-cases/{id}/bounties | bounty_created |
| RBN-016 | EPIC-07 | Admin | Model review | ModelReview | PATCH /admin/models/{id}/review | model_reviewed |
| RBN-017 | EPIC-08 | Repair user | Outcome form | RepairOutcome | POST /repair-cases/{id}/outcome | outcome_recorded |
| RBN-018 | EPIC-08 | System | System service | KnowledgeSignal | internal service | knowledge_signal_recorded |
| RBN-019 | EPIC-09 | Admin/System | Safety review | SafetyFlag | POST /admin/repair-cases/{id}/safety-flags | safety_case_flagged |
| RBN-020 | EPIC-10 | Admin/Founder | Metrics dashboard | AnalyticsEvent | GET /admin/metrics | dashboard_viewed |
