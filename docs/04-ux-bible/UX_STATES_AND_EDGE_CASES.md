# UX States and Edge Cases

## Required states for every core screen

- default;
- loading;
- empty;
- error;
- partial data;
- success;
- offline/retry where relevant;
- permission denied where relevant;
- low confidence where AI is involved.

---

## Repair intake edge cases

- user uploads blurry images;
- user uploads irrelevant images;
- user uploads copyrighted/brand-sensitive content;
- object is unsafe to repair;
- component is missing and cannot be photographed;
- dimensions are unknown;
- user does not know brand/model;
- user gives contradictory information;
- multiple components appear broken.

---

## AI edge cases

- multiple candidate objects;
- low confidence;
- category recognized but component unknown;
- false positive;
- missing scale reference;
- AI suggests a non-verified path;
- AI generation produces decorative but non-functional geometry.

---

## Marketplace edge cases

- model exists but not verified;
- model compatible only with variant;
- model is free but risky;
- model is paid and untested;
- maker removed model;
- model version changed;
- dispute over IP or originality.

---

## Provider edge cases

- no provider nearby;
- provider declines request;
- provider quote too high;
- provider fails delivery;
- print fails;
- user rejects quote;
- material not available;
- provider quality disputed.

---

## Outcome edge cases

- user never confirms outcome;
- repair works initially then fails;
- component fits but material unsuitable;
- provider produced correctly but model was wrong;
- model correct but user measured wrong;
- partial repair success.
