// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## - step-pr-crud-logging.md - DA ELIMINARE ALLA FINE

# Step PR activity logging

Branch:

```text
fix/42814-restore-back-office-crud-logging
```

Riferimenti:

- Issue: https://github.com/PrestaShop/PrestaShop/issues/42814
- PR collegata: https://github.com/PrestaShop/PrestaShop/pull/42864

## Completato

- [x] Separato il nuovo codice CRUD dal logging legacy diretto.
- [x] `BackOfficeActivityLogger` usa `Psr\Log\LoggerInterface`.
- [x] Spostata nel reporter la costruzione del messaggio storico.
- [x] Spostato nel reporter il context:
  - `object_type`
  - `object_id`
  - `allow_duplicate`
- [x] Eliminato `LegacyCrudActivitySubscriber`.
- [x] Eliminato `BackOfficeBackOfficeActivitySucceededEvent`.
- [x] Eliminato il passaggio:
  `reporter -> event -> subscriber -> LegacyLogger`.
- [x] Verificato che nel nuovo codice CRUD non restino riferimenti a
  `LegacyLogger`.
- [x] Verificato che nel codice `src` non restino riferimenti a
  `BackOfficeBackOfficeActivitySucceededEvent`.
- [x] Identificato il motivo dell'errore lazy del
  `BackOfficeActivityScopeSubscriber`: tutti i subscriber vengono caricati
  con `lazy: true`.
- [x] Allineato il nuovo subscriber al comportamento degli altri subscriber
  esistenti, evitando una classe `final` incompatibile con il lazy proxy.

## Da verificare subito

- [ ] Verificare end-to-end che una modifica Product dal Back Office produca
  realmente una voce in `ps_log`.
- [ ] Verificare che `LoggerInterface::info()` raggiunga il legacy Monolog
  handler.
- [ ] Se `info()` viene filtrato, definire il routing/livello corretto senza
  cambiare impropriamente la severity storica.
- [ ] Verificare in `ps_log`:
  - messaggio;
  - `object_type`;
  - `object_id`;
  - severity;
  - `allow_duplicate`;
  - employee associato.

## Da completare nell'infrastruttura

- [ ] Verificare che il reporter sia realmente best-effort e che un errore del
  logging non possa rompere una CRUD già riuscita.
- [ ] Verificare namespace e collocazione delle classi Core/PrestaShopBundle.
- [ ] Rinominare eventuali riferimenti `legacyObjectType` in
  `activityLogObjectType`.
- [ ] Completare il wiring della `FormHandlerFactory`.
- [ ] Configurare Product con il proprio `activityLogObjectType`.

## CRUD Product

### Create

- [ ] Aggiungere il reporting.
- [ ] Eseguirlo soltanto dopo il completamento corretto dell'intero
  `FormHandler`.
- [ ] Verificare il messaggio storico e i metadati in `ps_log`.

### Update

- [ ] Verificare il reporting già iniziato.
- [ ] Spostarlo, se necessario, alla fine dell'intera operazione del
  `FormHandler`.
- [ ] Verificare il messaggio storico e i metadati in `ps_log`.

### Delete

- [ ] Individuare il punto applicativo che rappresenta il successo effettivo.
- [ ] Chiamare il reporter soltanto dopo il successo.
- [ ] Verificare che un'eccezione non produca alcun log.

### Duplicate

- [ ] Implementare il reporting.
- [ ] Preservare separatamente:
  - source ID;
  - new ID;
  - log object ID.
- [ ] Verificare il messaggio storico:
  `Product duplicated: (from <sourceId> to <newId>).`

### Bulk delete

- [ ] Loggare ogni singolo item riuscito.
- [ ] Non loggare gli item falliti.
- [ ] Preservare i successi parziali anche se il bulk complessivo fallisce.

### Bulk duplicate

- [ ] Loggare ogni singola duplicazione riuscita.
- [ ] Preservare source/new ID dei successi.
- [ ] Verificare se `BulkProductException` o il risultato bulk perdono oggi i
  risultati parzialmente riusciti.
- [ ] Estendere l'infrastruttura bulk soltanto se necessario e in modo generico.

## Test

- [ ] Test unitari del reporter.
- [ ] Test del controllo Back Office / no-op fuori dal BO.
- [ ] Test create/update Product.
- [ ] Test delete/duplicate Product.
- [ ] Test bulk con successi parziali.
- [ ] Test compatibilità storica dei record `ps_log`.
- [ ] Verificare API e CLI: nessun CRUD activity log.

## Prima della PR

- [ ] Decidere il branch target definitivo (`develop` oppure `9.2.x`).
- [ ] Ricontrollare gli ultimi commenti della #42814 e della #42864.
- [ ] Ridurre eventuali TODO temporanei.
- [ ] Verificare coding standards e test CI.
- [ ] Valutare soltanto dopo Product se aggiungere una seconda entità come
  consumer dell'infrastruttura.
