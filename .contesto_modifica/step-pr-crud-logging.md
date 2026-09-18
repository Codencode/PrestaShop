// TODO <cnc> ########## BACK OFFICE ACTIVITY LOGGING ########## - step-pr-crud-logging.md - DA ELIMINARE ALLA FINE

# Step PR Back Office activity logging

Branch:

```text
fix/42814-restore-back-office-crud-logging
```

Riferimenti:

- Issue: https://github.com/PrestaShop/PrestaShop/issues/42814
- PR collegata: https://github.com/PrestaShop/PrestaShop/pull/42864

## Completato

- [x] Rimossa la dipendenza diretta del nuovo codice da `LegacyLogger`.
- [x] Eliminato `LegacyCrudActivitySubscriber`.
- [x] Eliminato `BackOfficeCrudOperationSucceededEvent`.
- [x] Eliminato il passaggio `reporter -> event -> subscriber -> LegacyLogger`.
- [x] Passaggio a `Psr\Log\LoggerInterface` / Monolog.
- [x] Logging reso best-effort nel concrete logger.
- [x] Refactoring naming da CRUD reporting ad ActivityLog.
- [x] Creato `Core\ActivityLog`.
- [x] Creati:
  - `BackOfficeActivity`
  - `BackOfficeActivityType`
  - `BackOfficeActivityLoggerInterface`
- [x] Spostata l'implementazione Symfony sotto `PrestaShopBundle\Service\Log`.
- [x] Creati:
  - `BackOfficeActivityLogger`
  - `BackOfficeActivityScope`
  - `BackOfficeActivityScopeSubscriber`
- [x] Sistemato il problema lazy proxy del subscriber mantenendolo non `final`.
- [x] Configurato alias DI:
  `BackOfficeActivityLoggerInterface -> BackOfficeActivityLogger`.
- [x] Sistemato il wiring della `FormHandlerFactory`.
- [x] Rinominato `crudActivityObjectType` in `activityLogObjectType`.
- [x] Configurato Product come consumer opt-in con object type `Product`.
- [x] Verificato che il nuovo codice non abbia riferimenti diretti a
  `LegacyLogger`.
- [x] Verificato UPDATE Product manualmente.
- [x] Verificato che UPDATE Product produca una voce in `ps_log`.
- [x] Separato l'ID dell'activity log dalla logica ExtraProperty:
  `resolveExtraPropertyEntityId()` resta relativo a ExtraProperty.

## Da verificare prima di considerare UPDATE chiuso

- [ ] Verificare che il log UPDATE venga prodotto nel punto finale corretto del
  `FormHandler`, dopo tutte le operazioni che devono far parte del successo.
- [ ] Verificare nel record `ps_log`:
  - message;
  - `object_type`;
  - `object_id`;
  - severity;
  - `allow_duplicate`;
  - employee.
- [ ] Aggiungere/aggiornare i test relativi a UPDATE.

## Prossimo step: CREATE

- [ ] Implementare activity logging in `FormHandler::handleFormCreate()`.
- [ ] Verificare la forma reale del valore restituito da `dataHandler->create()`.
- [ ] Ottenere correttamente l'ID creato.
- [ ] Non usare helper ExtraProperty soltanto per ottenere l'ID del log.
- [ ] Eseguire il log soltanto dopo il completamento corretto dell'intero create.
- [ ] Verificare il messaggio storico.
- [ ] Verificare `object_type`.
- [ ] Verificare `object_id`.
- [ ] Verificare severity.
- [ ] Verificare employee.
- [ ] Aggiungere test CREATE.

## DELETE

- [ ] Individuare il punto applicativo che rappresenta il successo effettivo del
  delete Product.
- [ ] Chiamare `BackOfficeActivityLoggerInterface` soltanto dopo il successo.
- [ ] Nessun log in caso di eccezione.
- [ ] Verificare record storico in `ps_log`.
- [ ] Aggiungere test.

## DUPLICATE

- [ ] Implementare l'activity logging.
- [ ] Preservare:
  - source ID;
  - new ID;
  - log object ID.
- [ ] Verificare messaggio storico:
  `Product duplicated: (from <sourceId> to <newId>).`
- [ ] Verificare `object_id` storico.
- [ ] Aggiungere test.

## BULK DELETE

- [ ] Loggare ogni singolo elemento riuscito.
- [ ] Non loggare gli elementi falliti.
- [ ] Preservare successi parziali se il bulk complessivo fallisce.
- [ ] Aggiungere test con successo parziale.

## BULK DUPLICATE

- [ ] Loggare ogni singola duplicazione riuscita.
- [ ] Preservare source/new ID.
- [ ] Verificare `AbstractBulkHandler`.
- [ ] Verificare `BulkProductException`.
- [ ] Modificare l'infrastruttura bulk soltanto se necessario.
- [ ] Aggiungere test con successo parziale.

## Scope Back Office

- [ ] Testare che richieste Back Office vengano loggate.
- [ ] Testare API: nessun activity log.
- [ ] Testare CLI: nessun activity log.
- [ ] Verificare eventuali altri entry point.

## Test generali

- [ ] Test unitari di `BackOfficeActivityLogger`.
- [ ] Test dello scope.
- [ ] Test FormHandler create/update.
- [ ] Test delete/duplicate.
- [ ] Test bulk.
- [ ] Test compatibilità storica di `ps_log`.
- [ ] Verificare che un errore del logger non rompa l'operazione principale.

## Prima della PR

- [ ] Ricontrollare gli ultimi commenti della #42814.
- [ ] Ricontrollare gli ultimi commenti della #42864.
- [ ] Decidere branch target (`develop` oppure `9.2.x`).
- [ ] Ridurre/rimuovere TODO temporanei.
- [ ] Eseguire coding standards.
- [ ] Eseguire test rilevanti.
- [ ] Verificare il diff completo contro il branch target.
- [ ] Valutare altre entità soltanto dopo la validazione completa di Product.
