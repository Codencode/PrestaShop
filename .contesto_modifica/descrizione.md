// TODO <cnc> ########## BACK OFFICE ACTIVITY LOGGING ########## - descrizione.md - DA ELIMINARE ALLA FINE

# BACK OFFICE ACTIVITY LOGGING

// TODO <cnc> ########## BACK OFFICE ACTIVITY LOGGING ########## ------------------------ NOTA

Questa nota descrive la seconda parte del lavoro relativo a:

https://github.com/PrestaShop/PrestaShop/issues/42814

La prima parte è gestita separatamente in:

https://github.com/PrestaShop/PrestaShop/pull/42864

## Direzione architetturale

Alla luce della #42864, il nuovo codice non deve dipendere direttamente da:

```text
LegacyLogger
PrestaShopLogger
ps_log
```

Il codice moderno deve usare:

```php
Psr\Log\LoggerInterface
```

La pipeline prevista è:

```text
Back Office operation
    -> BackOfficeActivity
    -> BackOfficeActivityLoggerInterface
    -> BackOfficeActivityLogger
    -> LoggerInterface
    -> Monolog
    -> legacy log handler esistente
    -> LegacyLogger / PrestaShopLogger
    -> ps_log
```

Il logging deve essere best-effort: un problema nel meccanismo di activity
logging non deve trasformare un'operazione già riuscita in un errore
applicativo.

L'infrastruttura non deve essere Product-specific.

Product viene usato come primo consumer completo per validare:

- create;
- update;
- delete;
- duplicate;
- bulk delete;
- bulk duplicate.

Solo dopo la validazione completa di Product verrà valutata l'adozione da parte
di altre entità.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Naming e struttura corrente

Il primo approccio modellava il concetto come una generica "CRUD operation":

```text
CrudOperation
CrudOperationType
BackOfficeCrudOperationReporterInterface
```

Il naming è stato successivamente corretto perché l'astrazione non esegue una
CRUD: descrive un'attività Back Office riuscita che deve essere registrata.

La struttura corrente è:

```text
src/Core/ActivityLog/
    BackOfficeActivity.php
    BackOfficeActivityType.php
    BackOfficeActivityLoggerInterface.php

src/PrestaShopBundle/Service/Log/
    BackOfficeActivityLogger.php
    BackOfficeActivityScope.php

src/PrestaShopBundle/EventSubscriber/
    BackOfficeActivityScopeSubscriber.php
```

Il Core contiene:

- il DTO che descrive l'attività;
- il tipo di attività;
- il contratto del logger.

Il layer `PrestaShopBundle` contiene:

- l'implementazione concreta basata su `LoggerInterface`;
- il controllo del contesto Back Office;
- il subscriber che marca la Request come Back Office.

Questo mantiene separato il contratto Core dall'infrastruttura Symfony.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## BackOfficeActivity

`BackOfficeActivity` è un piccolo DTO immutabile che descrive ciò che deve essere
registrato nell'activity log.

Deve poter rappresentare almeno:

```text
CREATE
UPDATE
DELETE
DUPLICATE
```

e mantenere separate, quando necessario, le informazioni:

- object type;
- source/object ID;
- log object ID;
- eventuale new object ID;
- eventuale indicazione bulk.

La separazione tra source ID, new ID e log object ID è importante soprattutto
per la duplication.

Esempio storico Product:

```text
Product duplicated: (from <sourceId> to <newId>).
```

L'ID usato nel messaggio e quello persistito come `object_id` potrebbero non
coincidere.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## BackOfficeActivityLoggerInterface

Il contratto Core è:

```php
interface BackOfficeActivityLoggerInterface
{
    public function log(BackOfficeActivity $activity): void;
}
```

Il Core non deve conoscere:

- Monolog;
- `LegacyLogger`;
- `PrestaShopLogger`;
- `ps_log`;
- dettagli di Request Symfony.

L'implementazione concreta è:

```text
PrestaShopBundle\Service\Log\BackOfficeActivityLogger
```

e utilizza:

```php
Psr\Log\LoggerInterface
```

Il servizio viene collegato all'interfaccia tramite alias DI.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Back Office scope

L'activity logging deve avvenire soltanto per operazioni originate dal Back
Office.

Il subscriber:

```text
BackOfficeActivityScopeSubscriber
```

marca la Request principale quando il controller appartiene al Back Office.

Il logger verifica poi:

```text
BackOfficeActivityScope
```

prima di produrre il record.

Comportamento atteso:

```text
Back Office -> log
API         -> no-op
CLI         -> no-op
altri entry point -> no-op
```

Il subscriber non è `final`, perché la directory
`PrestaShopBundle\EventSubscriber` viene registrata globalmente come
`lazy: true` e Symfony deve poter generare il relativo proxy.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Product opt-in

L'adozione del meccanismo è opt-in.

Il generic `FormHandler` riceve un:

```text
activityLogObjectType
```

che per default è `null`.

Quando il valore è `null` non viene prodotto alcun activity log.

Product è configurato con:

```text
activityLogObjectType = Product
```

Questo permette di introdurre progressivamente l'activity logging senza
modificare automaticamente il comportamento di tutti gli altri FormHandler.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## UPDATE Product

L'UPDATE Product è attualmente funzionante.

È stato verificato manualmente che il salvataggio di un Product dal Back Office
produce una voce in:

```text
ps_log
```

Il logging passa attraverso:

```text
FormHandler
    -> BackOfficeActivityLoggerInterface
    -> BackOfficeActivityLogger
    -> LoggerInterface
    -> Monolog
    -> legacy handler
    -> ps_log
```

Per l'UPDATE, l'ID dell'activity log deve essere direttamente l'ID
dell'oggetto aggiornato:

```php
(int) $id
```

Non usare:

```php
resolveExtraPropertyEntityId()
```

per determinare l'ID dell'activity log.

La riga già presente in `develop`:

```php
$entityId = $this->resolveExtraPropertyEntityId($newId ?? $id);
```

deve restare invariata perché appartiene alla funzionalità ExtraProperty.

Quindi i due concetti devono rimanere separati:

```text
$entityId
    -> ExtraProperty

(int) $id
    -> UPDATE activity log
```

Il log deve essere prodotto soltanto nel punto in cui l'operazione di update
considerata dal FormHandler è terminata correttamente.

// TODO <cnc> Ricontrollare il punto esatto del log UPDATE rispetto a extra
// properties e hook finali prima della PR.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## CREATE Product

Il prossimo step è:

```text
FormHandler::handleFormCreate()
```

È stato aggiunto un TODO nel metodo per ricordare il punto da implementare.

Create deve:

- recuperare correttamente l'ID dell'oggetto creato;
- produrre l'activity log soltanto dopo che l'intera operazione di create del
  FormHandler è terminata correttamente;
- non riutilizzare helper specifici delle Extra Properties soltanto per ottenere
  l'ID del log;
- usare lo stesso `BackOfficeActivityLoggerInterface`;
- preservare messaggio e metadati storici in `ps_log`.

Prima di implementarlo, verificare la forma reale del valore restituito da:

```php
$this->dataHandler->create($data);
```

per non introdurre assunzioni non valide per gli altri FormHandler.

// TODO <cnc> Implementare activity logging in handleFormCreate().

//////////////////////////////////////////////////////////////////////////////////////////////////////

## DELETE

Delete deve usare lo stesso logger, ma il log deve essere prodotto nel punto in
cui la cancellazione è realmente terminata con successo.

Non forzare delete dentro l'astrazione del FormHandler se il flusso applicativo
non passa da lì.

Per Product bisogna individuare il punto corretto dopo il successo del relativo
command/handler.

Se l'operazione fallisce, nessun activity log deve essere prodotto.

// TODO <cnc> Individuare il punto di successo effettivo del delete Product.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## DUPLICATE

La duplication deve usare lo stesso logger e preservare:

- source ID;
- new ID;
- log object ID.

Messaggio storico da verificare:

```text
Product duplicated: (from <sourceId> to <newId>).
```

Il record deve essere prodotto soltanto dopo che l'intera duplicazione è
terminata correttamente.

// TODO <cnc> Verificare message e object_id storici della duplication Product.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## BULK

Le operazioni bulk devono preservare i successi parziali.

Comportamento atteso:

```text
item 1 riuscito -> log
item 2 riuscito -> log
item 3 fallito  -> nessun log
item 4 riuscito -> log
```

Non aspettare necessariamente il successo dell'intero bulk per produrre i log
dei singoli elementi riusciti.

Per bulk duplicate bisogna verificare se l'infrastruttura corrente perde i
risultati delle azioni riuscite quando viene lanciata una
`BulkProductException`.

// TODO <cnc> Verificare AbstractBulkHandler/BulkProductException e scegliere la
// modifica minima necessaria per preservare i risultati parziali.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Compatibilità storica

Per ogni attività devono essere verificati almeno:

- message;
- `object_type`;
- `object_id`;
- severity;
- `allow_duplicate`;
- employee associato al record.

`id_employee` non deve essere aggiunto preventivamente al nuovo context se il
legacy logging lo associa già correttamente tramite il contesto Back Office.

Aggiungerlo esplicitamente soltanto se i test dimostrano che è necessario.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Da non usare

Non usare middleware globale del CommandBus.

Non usare hook ObjectModel come soluzione generica del logging.

Non chiamare direttamente:

```text
LegacyLogger
PrestaShopLogger
```

da:

```text
FormHandler
command handler
controller
servizi Core
BackOfficeActivityLogger
```

Non reintrodurre un evento Symfony usato soltanto come passaggio intermedio
prima del logger.

Un evento applicativo ha senso soltanto se in futuro esisteranno consumer
indipendenti dal logging.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Prossimi passi

Ordine consigliato:

1. completare e verificare definitivamente UPDATE Product;
2. implementare CREATE nel `handleFormCreate()`;
3. verificare create/update in `ps_log`;
4. implementare DELETE;
5. implementare DUPLICATE;
6. implementare bulk delete;
7. implementare bulk duplicate;
8. aggiungere/completare i test;
9. verificare API/CLI no-op;
10. ripulire TODO temporanei;
11. decidere il branch target definitivo;
12. soltanto dopo Product valutare altre entità.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Riferimenti

Issue:

- https://github.com/PrestaShop/PrestaShop/issues/42814

PR collegata:

- https://github.com/PrestaShop/PrestaShop/pull/42864

Branch:

```text
fix/42814-restore-back-office-crud-logging
```
