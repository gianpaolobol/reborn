# Database — Knowledge Graph Schema

## Obiettivo

Il Knowledge Graph collega prodotti, componenti, guasti, soluzioni, modelli, provider e risultati reali.

## Nodi principali

- Brand
- Product
- ProductVersion
- Component
- FailureMode
- RepairMethod
- Part
- PartVersion
- CADModel
- MaterialProfile
- PrinterProfile
- Provider
- Maker
- Tutorial
- RepairCase
- ValidationEvent
- Feedback

## Relazioni principali

- Brand HAS_PRODUCT Product
- Product HAS_VERSION ProductVersion
- ProductVersion HAS_COMPONENT Component
- Component FAILS_WITH FailureMode
- FailureMode CAN_BE_REPAIRED_BY RepairMethod
- RepairMethod USES_PART Part
- Part HAS_VERSION PartVersion
- PartVersion HAS_CAD_MODEL CADModel
- PartVersion COMPATIBLE_WITH ProductVersion
- PartVersion RECOMMENDS_MATERIAL MaterialProfile
- Provider CAN_PRINT MaterialProfile
- Provider OWNS_PRINTER PrinterProfile
- RepairCase TARGETS Component
- RepairCase VALIDATES PartVersion
- Feedback UPDATES RepairScore

## Dato chiave

La compatibilità non deve essere una nota testuale. Deve diventare relazione interrogabile.
