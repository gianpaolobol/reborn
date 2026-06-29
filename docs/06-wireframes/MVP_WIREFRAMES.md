# MVP Text Wireframes

These are low-fidelity textual wireframes. They are not visual design. They define hierarchy, content and interaction.

---

## WF-001 — Homepage

```text
[Top Nav]
Re-born | How it works | For Makers | For Providers | Login | Start Repair

[Hero]
Headline: Repair anything. Start with a photo.
Subcopy: Re-born helps identify broken parts, find repair paths and connect you with makers or local providers.
Primary CTA: Start a repair
Secondary CTA: See how it works

[Trust strip]
AI-assisted diagnosis | Maker community | Local production | Repair knowledge graph

[How it works]
1. Upload photos
2. Describe what broke
3. Get repair paths
4. Repair locally
5. Teach the system

[Sections]
For users | For makers | For providers | Sustainability impact

[Footer]
Mission: Allow anyone to repair anything.
```

Primary action: `Start a repair`  
Metric: `homepage_start_repair_clicked`

---

## WF-002 — Register

```text
[Header]
Create your Re-born account

[Form]
Email
Password
Confirm password
Role intention:
  ( ) I want to repair something
  ( ) I make CAD/3D models
  ( ) I provide 3D printing/repair services

[Checkbox]
Accept terms and repair safety policy

[CTA]
Create account

[Secondary]
Already have an account? Log in
```

Primary action: `Create account`  
Metric: `user_registered`

---

## WF-003 — User dashboard

```text
[Header]
Your repair workspace
CTA: Start new repair

[Cards]
Open repairs
- Case #RB-0001 | Waiting for classification | Continue
- Case #RB-0002 | Quote received | View quote

[Impact]
Objects saved: 0
Repair knowledge contributed: 2 cases

[Education]
Good photos make better repair paths.
```

Primary action: `Start new repair`  
Metric: `dashboard_start_repair_clicked`

---

## WF-004 — Start repair

```text
[Progress]
1 Photos | 2 Problem | 3 Details | 4 Review

[Title]
What are we repairing?

[Body]
Do not worry about technical names. Start with the object and the broken area.

[Input]
Short object name placeholder: e.g. coffee machine knob, chair foot, vacuum cleaner clip

[CTA]
Continue to photos
```

Primary action: `Continue to photos`  
Metric: `repair_case_started`

---

## WF-005 — Upload photos

```text
[Progress]
1 Photos active

[Title]
Upload photos of the object and the broken part

[Guidance cards]
- Whole object
- Broken area close-up
- Any labels, brand or model
- Measurement reference if possible

[Upload area]
Drag photos here or choose files
Limit: 8 photos

[Photo preview grid]
Photo 1 | Photo 2 | Photo 3

[CTA]
Continue
```

Error states:

- file too large;
- unsupported format;
- upload failed;
- no photo uploaded.

Metric: `photo_uploaded`

---

## WF-006 — Describe issue

```text
[Progress]
2 Problem active

[Title]
Tell us what happened

[Prompt 1]
What broke or stopped working?
[Textarea]

[Prompt 2]
Is the broken part still available?
Options: yes / no / partly / not sure

[Prompt 3]
Does the object still work without this part?
Options: yes / no / partly / not sure

[CTA]
Continue to details
```

Metric: `description_added`

---

## WF-007 — Add details

```text
[Progress]
3 Details active

[Title]
Add anything that can help identify the part

[Optional fields]
Brand
Model
Approximate dimensions
Material clues
Where the part is located

[Helper]
Skip anything you do not know. Re-born can ask later.

[CTA]
Review repair case
```

Metric: `details_added`

---

## WF-008 — Review and submit

```text
[Progress]
4 Review active

[Summary]
Object name
Photos count
Description
Brand/model if provided
Dimensions if provided

[Safety notice]
Re-born provides repair guidance and connects you with contributors. Functional and safety-critical parts require extra review.

[CTA]
Submit for repair analysis
[Secondary]
Save as draft
```

Metric: `repair_case_submitted`

---

## WF-009 — Case timeline waiting

```text
[Header]
Repair Case RB-0001
Status: Submitted / Under review

[Timeline]
✓ Case created
✓ Photos uploaded
✓ Description submitted
• Classification in progress
• Repair paths pending

[Card]
What happens next?
We identify the object, component and best repair routes.

[CTA]
Add more information
```

Metric: `case_timeline_viewed`

---

## WF-010 — Admin classification console

```text
[Admin layout]
Left: case queue
Main: selected case
Right: classification panel

[Case data]
Photos
Description
Brand/model/dimensions
User notes

[Classification panel]
Category dropdown
Product type dropdown
Component candidate dropdown
Damage type dropdown
Confidence: low / medium / high
Safety flag: yes/no
Missing data request

[CTA]
Save classification
[Secondary]
Request more data
```

Metric: `classification_updated`

---

## WF-011 — Diagnosis summary

```text
[Header]
We found the likely repair target

[Diagnosis card]
Object: Coffee machine
Component: Steam knob
Damage: Broken internal connector
Confidence: Medium

[What this means]
This part may be replaceable by printing or sourcing a compatible component.

[Warnings]
Check heat resistance. This may require provider review.

[CTA]
View repair paths
[Secondary]
This is wrong — correct it
```

Metric: `diagnosis_viewed`

---

## WF-012 — Repair path comparison

```text
[Header]
Choose the best repair path

[Path cards]
1. Ask a local provider
   Cost: estimate pending
   Speed: medium
   Confidence: high if printable
   CTA: Request quote

2. Ask makers to model it
   Cost: bounty/credits placeholder
   Speed: slower
   Confidence: good for unavailable parts
   CTA: Create bounty

3. Search existing model
   Cost: low
   Speed: fast
   Confidence: depends on compatibility
   CTA: View candidates

4. AI generation
   Experimental
   CTA: Join AI generation path

5. Expert/manual review
   CTA: Request review
```

Metric: `repair_path_selected`

---

## WF-013 — Provider request detail

```text
[Provider dashboard]
Request RB-0001

[Repair DNA]
Category
Component
Damage
Photos
Dimensions
Material clues
Safety flags

[Provider action]
Can produce? yes/no/need more data
Material proposal
Price
Turnaround
Notes

[CTA]
Send quote
```

Metric: `quote_created`

---

## WF-014 — Maker bounty detail

```text
[Maker dashboard]
Open bounty RB-0001

[Problem]
Object and component
Photos
Dimensions
Required constraints
Reward placeholder
Deadline

[Actions]
Submit candidate model metadata
Ask question
Follow bounty
```

Metric: `bounty_viewed`

---

## WF-015 — Outcome confirmation

```text
[Header]
Did the repair work?

[Options]
( ) Yes, object works again
( ) Part was produced but did not fit
( ) Repair was abandoned
( ) Still in progress

[Optional]
Upload final photo
What helped?
What failed?

[Impact]
This feedback improves future repairs.

[CTA]
Save outcome
```

Metric: `outcome_recorded`

---

## WF-016 — Admin metrics dashboard

```text
[Admin metrics]
Date range selector

[Funnel]
Cases created
Cases submitted
Cases classified
Paths selected
Quotes requested
Outcomes recorded
Successful repairs

[Knowledge]
New products
New components
New models
New signals
Failed repair signals

[Marketplace]
Active providers
Quote response time
Active makers
Model submissions
```

Metric: `admin_metrics_viewed`
