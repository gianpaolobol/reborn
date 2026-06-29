# Create Re-born MVP GitHub issues using GitHub CLI.
# Prerequisites:
#   winget install --id GitHub.cli
#   gh auth login
#   cd C:\REBORN\REBORN
#   git remote -v

$ErrorActionPreference = "Stop"

$issues = @(
  @{ title="RBN-001 Create account"; body="See docs/03-prd/MVP_TICKETS.md#rbn-001--create-account"; labels="type:story,priority:P0,epic:identity" },
  @{ title="RBN-002 Login"; body="See docs/03-prd/MVP_TICKETS.md#rbn-002--login"; labels="type:story,priority:P0,epic:identity" },
  @{ title="RBN-003 Start repair case"; body="See docs/03-prd/MVP_TICKETS.md#rbn-003--start-repair-case"; labels="type:story,priority:P0,epic:repair" },
  @{ title="RBN-004 Upload repair photos"; body="See docs/03-prd/MVP_TICKETS.md#rbn-004--upload-repair-photos"; labels="type:story,priority:P0,epic:repair" },
  @{ title="RBN-005 Guided issue description"; body="See docs/03-prd/MVP_TICKETS.md#rbn-005--guided-issue-description"; labels="type:story,priority:P0,epic:repair" },
  @{ title="RBN-006 Submit case for classification"; body="See docs/03-prd/MVP_TICKETS.md#rbn-006--submit-case-for-classification"; labels="type:story,priority:P0,epic:repair" },
  @{ title="RBN-007 Manual classification console"; body="See docs/03-prd/MVP_TICKETS.md#rbn-007--manual-classification-console"; labels="type:story,priority:P0,epic:admin" },
  @{ title="RBN-008 Repair diagnosis summary"; body="See docs/03-prd/MVP_TICKETS.md#rbn-008--repair-diagnosis-summary"; labels="type:story,priority:P0,epic:repair" },
  @{ title="RBN-009 Repair path comparison"; body="See docs/03-prd/MVP_TICKETS.md#rbn-009--repair-path-comparison"; labels="type:story,priority:P0,epic:repair" },
  @{ title="RBN-010 Provider profile"; body="See docs/03-prd/MVP_TICKETS.md#rbn-010--provider-profile"; labels="type:story,priority:P1,epic:provider" },
  @{ title="RBN-011 Provider quote request"; body="See docs/03-prd/MVP_TICKETS.md#rbn-011--provider-quote-request"; labels="type:story,priority:P0,epic:provider" },
  @{ title="RBN-012 Provider quote response"; body="See docs/03-prd/MVP_TICKETS.md#rbn-012--provider-quote-response"; labels="type:story,priority:P0,epic:provider" },
  @{ title="RBN-013 Maker profile"; body="See docs/03-prd/MVP_TICKETS.md#rbn-013--maker-profile"; labels="type:story,priority:P1,epic:maker" },
  @{ title="RBN-014 Model metadata contribution"; body="See docs/03-prd/MVP_TICKETS.md#rbn-014--model-metadata-contribution"; labels="type:story,priority:P1,epic:maker" },
  @{ title="RBN-015 Maker bounty request"; body="See docs/03-prd/MVP_TICKETS.md#rbn-015--maker-bounty-request"; labels="type:story,priority:P1,epic:marketplace" },
  @{ title="RBN-016 Admin model approval"; body="See docs/03-prd/MVP_TICKETS.md#rbn-016--admin-model-approval"; labels="type:story,priority:P1,epic:admin" },
  @{ title="RBN-017 Outcome confirmation"; body="See docs/03-prd/MVP_TICKETS.md#rbn-017--outcome-confirmation"; labels="type:story,priority:P0,epic:knowledge" },
  @{ title="RBN-018 Knowledge Graph signal writer"; body="See docs/03-prd/MVP_TICKETS.md#rbn-018--knowledge-graph-signal-writer"; labels="type:story,priority:P0,epic:knowledge" },
  @{ title="RBN-019 Restricted/risky item flag"; body="See docs/03-prd/MVP_TICKETS.md#rbn-019--restrictedrisky-item-flag"; labels="type:story,priority:P0,epic:safety" },
  @{ title="RBN-020 MVP metrics dashboard"; body="See docs/03-prd/MVP_TICKETS.md#rbn-020--mvp-metrics-dashboard"; labels="type:story,priority:P1,epic:metrics" }
)

foreach ($issue in $issues) {
  gh issue create --title $issue.title --body $issue.body --label $issue.labels
}
