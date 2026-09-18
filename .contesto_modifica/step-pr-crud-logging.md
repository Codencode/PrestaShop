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
- [x] Spostato il logging CREATE/UPDATE nel punto finale del `FormHandler`,
  dopo Extra Properties e hook finale.
- [x] Implementato CREATE Product.
- [x] Verificato CREATE Product manualmente.
- [x] Aggiunto helper protetto generico in `PrestaShopAdminController` per
  inoltrare `BackOfficeActivity` a `BackOfficeActivityLoggerInterface`.
- [x] Implementato DELETE Product nei flussi all shops, shop e shop group.
- [x] Il DELETE viene loggato soltanto dopo il successo di
  `DeleteProductCommand`; nessun log in caso di eccezione.
- [x] Verificato il comportamento storico di base del DELETE Product:
  `Product deletion`, object type `Product`, object ID del prodotto.

## Da verificare per CREATE / UPDATE

- [ ] Verificare nel record `ps_log` tutti i metadata storici rilevanti:
  - message;
  - `object_type`;
  - `object_id`;
  - severity;
  - `allow_duplicate`;
  - employee.
- [ ] Aggiungere/aggiornare i test relativi a CREATE/UPDATE.

## CREATE

- [x] Implementare activity logging in `FormHandler::handleFormCreate()`.
- [x] Usare l'ID restituito da `dataHandler->create()` per l'activity log Product.
- [x] Non usare helper ExtraProperty per ottenere l'ID del log.
- [x] Eseguire il log soltanto dopo il completamento corretto dell'intero create.
- [x] Verificare manualmente il funzionamento CREATE Product.
- [ ] Completare verifica metadata storici in `ps_log`.
- [ ] Aggiungere test CREATE.

## DELETE

- [x] Individuato il punto di integrazione nel layer Back Office, fuori dai
  command handler.
- [x] Aggiunto helper generico in `PrestaShopAdminController`.
- [x] Integrato Product delete per all shops, shop e shop group.
- [x] Chiamare `BackOfficeActivityLoggerInterface` soltanto dopo il successo.
- [x] Nessun log in caso di eccezione del `DeleteProductCommand`.
- [x] Verificato comportamento storico di base: `Product deletion`, Product,
  object ID del prodotto.
- [ ] Verificare manualmente il record DELETE in `ps_log`.
- [ ] Verificare eventuali altri metadata storici rilevanti.
- [ ] Aggiungere test DELETE.

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

## Adozione futura da parte di altre entità

- [x] Pattern corrente: CREATE/UPDATE tramite generic `FormHandler` quando
  applicabile.
- [x] Pattern corrente: DELETE e altre operazioni BO fuori dal `FormHandler`
  possono riutilizzare l'helper generico di `PrestaShopAdminController`.
- [x] Mantenere specifico dell'entità il call site, non l'infrastruttura.
- [x] Non introdurre logging nei command handler o middleware globale del
  CommandBus soltanto per centralizzare le chiamate.
- [ ] Per DUPLICATE verificare prima il flusso reale e la semantica storica;
  non assumere ancora che debba usare lo stesso punto del DELETE.
- [ ] Prima di aggiungere nuove astrazioni per altre entità, verificare se i
  punti generici esistenti sono sufficienti.

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
