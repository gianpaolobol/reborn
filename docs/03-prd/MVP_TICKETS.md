# MVP Tickets

Ticket format:

- **ID**
- **Epic**
- **Role**
- **Story**
- **Acceptance criteria**
- **Priority**
- **Dependencies**

---

## RBN-001 — Create account

**Epic:** EPIC-01  
**Role:** Guest  
**Story:** As a guest, I want to create an account so that I can start and track repair cases.

### Acceptance criteria

- email and password are required;
- duplicate email is rejected;
- password is hashed;
- user receives default role `repair_user`;
- user is redirected to onboarding or dashboard;
- event `user.registered` is recorded.

**Priority:** P0  
**Dependencies:** none

---

## RBN-002 — Login

**Epic:** EPIC-01  
**Role:** Registered user  
**Story:** As a registered user, I want to log in securely so that I can access my repair data.

### Acceptance criteria

- invalid credentials show a generic error;
- successful login creates a session;
- session can be destroyed on logout;
- role determines dashboard route;
- event `user.logged_in` is recorded.

**Priority:** P0  
**Dependencies:** RBN-001

---

## RBN-003 — Start repair case

**Epic:** EPIC-02  
**Role:** Repair user  
**Story:** As a repair user, I want to start a repair case so that Re-born can guide me toward a solution.

### Acceptance criteria

- user can start a case from homepage or dashboard;
- case is created with status `draft`;
- case has a unique public reference;
- empty draft can be resumed;
- event `repair_case.created` is recorded.

**Priority:** P0  
**Dependencies:** RBN-002

---

## RBN-004 — Upload repair photos

**Epic:** EPIC-02  
**Role:** Repair user  
**Story:** As a repair user, I want to upload photos of the broken object so that the system can understand the problem.

### Acceptance criteria

- user can upload at least 1 photo and up to 8 photos;
- allowed formats: jpg, png, webp;
- size limit is enforced;
- upload errors are clear;
- photo is linked to the case;
- event `repair_case.photo_uploaded` is recorded.

**Priority:** P0  
**Dependencies:** RBN-003

---

## RBN-005 — Guided issue description

**Epic:** EPIC-02  
**Role:** Repair user  
**Story:** As a repair user, I want to describe what broke in simple language so that I do not need technical vocabulary.

### Acceptance criteria

- free-text description is available;
- guided prompts ask what happened, what part moved/broke, and what still works;
- optional fields exist for brand, model, dimensions and material clues;
- user can save progress;
- event `repair_case.description_added` is recorded.

**Priority:** P0  
**Dependencies:** RBN-003

---

## RBN-006 — Submit case for classification

**Epic:** EPIC-03  
**Role:** Repair user  
**Story:** As a repair user, I want to submit my case so that Re-born can analyze it.

### Acceptance criteria

- minimum data is validated;
- case status changes from `draft` to `submitted`;
- missing mandatory data blocks submission;
- Repair DNA draft is created;
- event `repair_case.submitted` is recorded.

**Priority:** P0  
**Dependencies:** RBN-004, RBN-005

---

## RBN-007 — Manual classification console

**Epic:** EPIC-03  
**Role:** Admin  
**Story:** As an admin, I want to classify early repair cases manually so that the MVP can learn before full AI automation.

### Acceptance criteria

- admin can assign category, product type, component and damage type;
- admin can set confidence level;
- admin can add missing data request;
- classification history is retained;
- event `classification.updated` is recorded.

**Priority:** P0  
**Dependencies:** RBN-006

---

## RBN-008 — Repair diagnosis summary

**Epic:** EPIC-04  
**Role:** Repair user  
**Story:** As a repair user, I want to see a simple diagnosis summary so that I understand what Re-born thinks is broken.

### Acceptance criteria

- summary shows object, suspected component, damage type and confidence;
- low confidence is clearly labelled;
- user can correct obvious mistakes;
- safety warning appears when needed;
- event `repair_case.diagnosis_viewed` is recorded.

**Priority:** P0  
**Dependencies:** RBN-007

---

## RBN-009 — Repair path comparison

**Epic:** EPIC-04  
**Role:** Repair user  
**Story:** As a repair user, I want to compare repair paths so that I can choose the best next step.

### Acceptance criteria

- at least these paths can appear: existing model, provider quote, maker bounty, AI placeholder, expert review;
- each path has expected cost/time/confidence labels;
- unavailable paths are explained, not hidden silently;
- user can choose one path;
- event `repair_path.selected` is recorded.

**Priority:** P0  
**Dependencies:** RBN-008

---

## RBN-010 — Provider profile

**Epic:** EPIC-06  
**Role:** Provider  
**Story:** As a provider, I want to define my capabilities so that I receive suitable repair requests.

### Acceptance criteria

- provider can enter location/service area;
- provider can list materials and technologies;
- provider can list machines;
- provider status starts as `pending_review`;
- event `provider.profile_created` is recorded.

**Priority:** P1  
**Dependencies:** RBN-002

---

## RBN-011 — Provider quote request

**Epic:** EPIC-06  
**Role:** Repair user  
**Story:** As a repair user, I want to request a quote from a local provider so that I can get the part produced.

### Acceptance criteria

- quote request is linked to repair case;
- provider sees structured case data;
- request has status `sent`;
- event `quote.requested` is recorded.

**Priority:** P0  
**Dependencies:** RBN-009, RBN-010

---

## RBN-012 — Provider quote response

**Epic:** EPIC-06  
**Role:** Provider  
**Story:** As a provider, I want to send a quote so that the user can decide whether to proceed.

### Acceptance criteria

- provider can enter price, turnaround time, notes and material;
- quote status changes to `quoted`;
- user can view quote;
- event `quote.created` is recorded.

**Priority:** P0  
**Dependencies:** RBN-011

---

## RBN-013 — Maker profile

**Epic:** EPIC-05  
**Role:** Maker  
**Story:** As a maker, I want to create a profile so that I can contribute repair models and respond to bounties.

### Acceptance criteria

- maker can enter skills, software, location and portfolio URL;
- profile status starts as `pending_review`;
- admin can approve it;
- event `maker.profile_created` is recorded.

**Priority:** P1  
**Dependencies:** RBN-002

---

## RBN-014 — Model metadata contribution

**Epic:** EPIC-05  
**Role:** Maker  
**Story:** As a maker, I want to upload model metadata so that my repair design can be reused.

### Acceptance criteria

- maker can upload model title, description, category, component, compatibility and file placeholder;
- model status starts as `pending_review`;
- model can be linked to repair cases;
- event `model.submitted` is recorded.

**Priority:** P1  
**Dependencies:** RBN-013

---

## RBN-015 — Maker bounty request

**Epic:** EPIC-04  
**Role:** Repair user  
**Story:** As a repair user, I want to create a bounty when no existing model exists so that makers can propose a solution.

### Acceptance criteria

- bounty is linked to a repair case;
- bounty has description, reward placeholder and expiry date;
- makers can see open bounties;
- event `bounty.created` is recorded.

**Priority:** P1  
**Dependencies:** RBN-009, RBN-013

---

## RBN-016 — Admin model approval

**Epic:** EPIC-07  
**Role:** Admin  
**Story:** As an admin, I want to approve or reject submitted models so that public repair suggestions remain trustworthy.

### Acceptance criteria

- admin can approve, reject or request changes;
- rejection reason is stored;
- status history is retained;
- event `model.reviewed` is recorded.

**Priority:** P1  
**Dependencies:** RBN-014

---

## RBN-017 — Outcome confirmation

**Epic:** EPIC-08  
**Role:** Repair user  
**Story:** As a repair user, I want to confirm whether the repair worked so that Re-born can learn.

### Acceptance criteria

- user can mark repair as successful, failed or abandoned;
- user can upload final photo;
- user can explain failure reason;
- outcome updates Knowledge Graph signals;
- event `repair_case.outcome_recorded` is recorded.

**Priority:** P0  
**Dependencies:** RBN-009

---

## RBN-018 — Knowledge Graph signal writer

**Epic:** EPIC-08  
**Role:** System  
**Story:** As the system, I want to store learning signals from every case so that future repairs improve.

### Acceptance criteria

- classification, selected path and outcome are stored as graph-ready signals;
- failed repairs are not discarded;
- admin can inspect signal records;
- event `knowledge.signal_recorded` is recorded.

**Priority:** P0  
**Dependencies:** RBN-006, RBN-017

---

## RBN-019 — Restricted/risky item flag

**Epic:** EPIC-09  
**Role:** System/Admin  
**Story:** As Re-born, I want to flag risky categories so that unsafe repairs are not treated like normal consumer parts.

### Acceptance criteria

- admin can flag case as risky;
- user sees a clear safety message;
- risky case cannot proceed to public maker/provider marketplace without review;
- event `safety.case_flagged` is recorded.

**Priority:** P0  
**Dependencies:** RBN-007

---

## RBN-020 — MVP metrics dashboard

**Epic:** EPIC-10  
**Role:** Admin/Founder  
**Story:** As the founder, I want to see MVP funnel metrics so that I know whether Re-born is working.

### Acceptance criteria

- dashboard shows created cases, submitted cases, classified cases, selected paths, outcomes and success rate;
- dashboard shows number of graph entities/signals;
- dashboard shows provider/maker activity;
- metrics are filterable by date range;
- event collection is documented.

**Priority:** P1  
**Dependencies:** RBN-003, RBN-006, RBN-017, RBN-018
