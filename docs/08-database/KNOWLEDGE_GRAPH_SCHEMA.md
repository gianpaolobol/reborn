# Knowledge Graph Schema

The MVP can implement this graph through relational tables.

---

## Main graph entities

```text
products
product_models
brands
components
component_variants
repair_cases
repair_paths
cad_models
cad_model_versions
materials
providers
makers
spare_parts
bounties
outcomes
trust_signals
knowledge_edges
```

---

## Generic edge table concept

```text
knowledge_edges
- id
- source_type
- source_id
- relation_type
- target_type
- target_id
- confidence
- evidence_type
- evidence_id
- created_at
- updated_at
```

Example relation types:

- HAS_COMPONENT
- COMPATIBLE_WITH
- REPLACED_BY
- PRINTED_FROM
- VERIFIED_BY_OUTCOME
- CREATED_BY
- FULFILLED_BY
- FAILED_WITH
- RECOMMENDED_FOR

---

## Evidence

Every important relationship should have evidence when possible:

- user confirmation;
- admin correction;
- provider fulfilment;
- repair outcome;
- maker submission;
- AI recognition;
- external reference;
- enterprise validation.

---

## MVP rule

Do not build a complex graph database first.

Start with relational tables and explicit edges.
