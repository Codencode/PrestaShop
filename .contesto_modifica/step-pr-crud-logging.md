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
- [x] Verificato manualmente DELETE Product in `ps_log`.
- [x] Implementato BULK DELETE Product preservando i successi parziali.
- [x] Gli ID riusciti del BULK DELETE vengono ricavati come selezione meno ID
  contenuti nella `BulkProductException`.
- [x] Ogni Product cancellato con successo viene loggato singolarmente tramite
  l'helper generico di `PrestaShopAdminController`.
- [x] Corretto il flusso BULK DELETE all-shops affinché restituisca la response
  del bulk helper e preservi gli errori parziali.
- [x] Verificato manualmente il funzionamento del BULK DELETE.
- [x] Reso `BackOfficeActivityLogger::log()` completamente best-effort,
  includendo scope check, message building e logger nel `try/catch`.
- [x] Verificato storicamente DUPLICATE Product:
  `Product duplicated: (from <sourceId> to <newId>).`.
- [x] Verificato storicamente `object_id = 0` per DUPLICATE Product.
- [x] Implementato DUPLICATE Product singolo dopo il successo del command.
- [x] Implementato BULK DUPLICATE preservando `sourceProductId => new ProductId`.
- [x] Estesa `BulkProductException` per conservare genericamente i risultati
  riusciti quando il bulk termina con errori parziali.
- [x] Mantenuto l'activity logging fuori da `AbstractBulkHandler` e dai command
  handler.
- [x] Aggiunti i tipi `ACTIVATE` e `DEACTIVATE`.
- [x] Implementato logging status nei flussi singoli/toggle Product.
- [x] Implementato logging BULK ACTIVATE/DEACTIVATE con successi parziali.

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
- [x] Verificare manualmente il record DELETE in `ps_log`.
- [ ] Verificare eventuali altri metadata storici rilevanti.
- [ ] Aggiungere test DELETE.

## DUPLICATE

- [x] Implementare l'activity logging.
- [x] Preservare:
  - source ID;
  - new ID;
  - log object ID.
- [x] Verificare messaggio storico:
  `Product duplicated: (from <sourceId> to <newId>).`
- [x] Verificare `object_id` storico: `0`.
- [x] Loggare soltanto dopo il successo della duplicazione.
- [ ] Verificare manualmente il record prodotto dalla nuova implementazione.
- [ ] Aggiungere test.

## BULK DELETE

- [x] Loggare ogni singolo elemento riuscito.
- [x] Non loggare gli elementi falliti.
- [x] Preservare successi parziali se il bulk complessivo fallisce.
- [x] Ricavare gli ID riusciti come selezione meno ID presenti nella
  `BulkProductException`.
- [x] Correggere il flusso all-shops per restituire la response del bulk helper.
- [x] Verificare manualmente il funzionamento del BULK DELETE.
- [ ] Aggiungere test automatico con successo parziale.

## BULK DUPLICATE

- [x] Loggare ogni singola duplicazione riuscita.
- [x] Preservare source/new ID.
- [x] Verificare `AbstractBulkHandler`.
- [x] Verificare `BulkProductException`.
- [x] Preservare i risultati riusciti `sourceProductId => new ProductId` anche
  quando viene lanciata la bulk exception.
- [x] Mantenere generica l'infrastruttura bulk, senza dipendenze dall'activity
  logging.
- [ ] Verificare manualmente bulk completamente riuscito.
- [ ] Verificare manualmente successo parziale.
- [ ] Aggiungere test con successo parziale.

## ACTIVATE / DEACTIVATE

- [x] Aggiungere `BackOfficeActivityType::ACTIVATE`.
- [x] Aggiungere `BackOfficeActivityType::DEACTIVATE`.
- [x] Integrare logging nel toggle Product.
- [x] Integrare logging nei flussi enable/disable espliciti.
- [x] Integrare BULK ACTIVATE/DEACTIVATE.
- [x] Preservare successi parziali nei bulk status con:
  `selected IDs - failed IDs`.
- [ ] Verificare manualmente `Product activated: <id>`.
- [ ] Verificare manualmente `Product deactivated: <id>`.
- [ ] Verificare `object_id` e gli altri metadata storici.
- [ ] Aggiungere test singoli e bulk.

## Adozione futura da parte di altre entità

- [x] Pattern corrente: CREATE/UPDATE tramite generic `FormHandler` quando
  applicabile.
- [x] Pattern corrente: DELETE/DUPLICATE/STATUS e altre operazioni BO fuori dal
  `FormHandler` possono riutilizzare l'helper generico di
  `PrestaShopAdminController`.
- [x] Mantenere specifico dell'entità il call site, non l'infrastruttura.
- [x] Non introdurre logging nei command handler o middleware globale del
  CommandBus soltanto per centralizzare le chiamate.
- [x] Per i bulk preservare i successi parziali.
- [x] Se servono dati aggiuntivi del successo bulk, conservarli genericamente
  nel contratto bulk, non nel logging.
- [x] Non assumere che `object_id` coincida con source/new ID.
- [ ] Per ogni nuova entità ricostruire prima operazioni e metadata storici.
- [ ] Verificare prima i punti generici esistenti prima di creare nuove
  astrazioni.

Procedura sintetica per una nuova entità:

1. verificare comportamento storico e metadata;
2. individuare il punto finale di successo;
3. usare `FormHandler` per CREATE/UPDATE quando disponibile;
4. usare l'helper BO del controller per operazioni controller-driven;
5. costruire `BackOfficeActivity` con ID semanticamente corretti;
6. per i bulk loggare solo i successi e preservare eventuali risultati
   aggiuntivi;
7. verificare BO/API/CLI;
8. confrontare il record reale in `ps_log`.

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
- [ ] Aggiungere un test che dimostri che errori di scope/message/logger non rompono l'operazione principale.

## Prima della PR

- [ ] Ricontrollare gli ultimi commenti della #42814.
- [ ] Ricontrollare gli ultimi commenti della #42864.
- [ ] Decidere branch target (`develop` oppure `9.2.x`).
- [ ] Ridurre/rimuovere TODO temporanei.
- [ ] Eseguire coding standards.
- [ ] Eseguire test rilevanti.
- [ ] Verificare il diff completo contro il branch target.
- [ ] Valutare altre entità soltanto dopo la validazione completa di Product.
