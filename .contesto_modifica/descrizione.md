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

L'intero `BackOfficeActivityLogger::log()` è protetto da `try/catch (Throwable)`,
inclusi scope check, traduzione/formattazione del messaggio e invio al logger.

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

L'UPDATE Product è attualmente funzionante ed è stato verificato manualmente.

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

La logica ExtraProperty rimane separata:

```text
$entityId
    -> ExtraProperty

(int) $id
    -> UPDATE activity log
```

Il log viene prodotto nel punto finale del `FormHandler`, dopo Extra Properties
e hook finale, in modo che un errore precedente impedisca la scrittura
dell'activity log.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## CREATE Product

CREATE Product è stato implementato in `FormHandler::handleFormCreate()` ed è
stato verificato manualmente.

L'ID dell'activity log è l'ID restituito da:

```php
$this->dataHandler->create($data);
```

Non viene usato `resolveExtraPropertyEntityId()` per determinare l'ID del log.

Come per UPDATE, il log viene prodotto soltanto al termine del flusso riuscito,
dopo Extra Properties e hook finale.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## DELETE Product

DELETE Product è stato implementato mantenendo il logging fuori dai command
handler.

È stato aggiunto in `PrestaShopAdminController` un helper protetto e generico
che inoltra un `BackOfficeActivity` a `BackOfficeActivityLoggerInterface`.

`ProductController` usa questo helper dopo il successo di
`DeleteProductCommand` nei tre flussi:

```text
all shops
single shop
shop group
```

Questo mantiene il call site specifico di Product, ma l'infrastruttura
riutilizzabile da altre entità.

Se `DeleteProductCommand` fallisce, il codice non raggiunge il logging e quindi
non viene prodotto alcun activity log.

Il comportamento individuato è coerente con lo storico almeno per:

```text
message: Product deletion
object_type: Product
object_id: product ID
```

La verifica manuale del DELETE Product in `ps_log` è stata completata con
esito positivo. Restano da completare i test automatici e l'eventuale verifica
esaustiva di tutti i metadata storici.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## BULK DELETE Product

BULK DELETE Product è stato implementato e verificato manualmente.

Il controller ricava gli ID riusciti come differenza tra gli ID selezionati e
gli ID contenuti nella `BulkProductException`, quindi produce un activity log
`DELETE` per ciascun Product cancellato con successo tramite l'helper generico
di `PrestaShopAdminController`.

In questo modo un errore parziale del bulk non elimina i log delle operazioni
già riuscite:

```text
item riuscito -> log
item fallito  -> nessun log
```

È stato inoltre corretto il flusso `all shops` affinché restituisca la response
del bulk helper, preservando gli errori parziali.

La strategia replica il comportamento storico di logging immediato per ogni
delete riuscito. Restano da aggiungere/completare i test automatici, in
particolare il caso di successo parziale.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Adozione futura da parte di altre entità

Per estendere l'activity logging ad altre entità mantenere, quando applicabile,
questa distinzione:

```text
CREATE / UPDATE
    -> generic FormHandler

DELETE / DUPLICATE / STATUS / altre operazioni BO fuori dal FormHandler
    -> helper generico di PrestaShopAdminController
    -> BackOfficeActivityLoggerInterface

BULK
    -> un BackOfficeActivity per ogni elemento riuscito
    -> preservare sempre i successi parziali
```

Il call site può essere specifico dell'entità; l'infrastruttura non deve
esserlo.

Non introdurre activity logging nei command handler o middleware globale del
CommandBus soltanto per semplificare l'adozione da parte di nuove entità.

### Procedura per una nuova entità

Prima di aggiungere il logging:

1. verificare quali operazioni venivano storicamente registrate;
2. verificare `message`, `object_type`, `object_id`, severity,
   `allow_duplicate` ed employee;
3. individuare il punto finale di successo dell'operazione;
4. usare il `FormHandler` per CREATE/UPDATE quando disponibile;
5. usare l'helper BO del controller per operazioni controller-driven;
6. costruire un `BackOfficeActivity` mantenendo distinti source/object ID,
   log object ID, eventuale new object ID e flag bulk;
7. nei bulk loggare soltanto gli elementi riusciti;
8. se il bulk produce risultati aggiuntivi necessari al logging, conservarli
   nel contratto bulk in modo generico;
9. verificare API/CLI no-op;
10. validare il risultato reale in `ps_log`.

Il Product dimostra che `object_id` non è necessariamente uguale al source ID o
al new ID: DUPLICATE usa storicamente `object_id = 0`.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## DUPLICATE

DUPLICATE Product è stato implementato usando lo stesso logger e l'helper
generico di `PrestaShopAdminController`.

Il comportamento storico verificato manualmente è:

```text
Product duplicated: (from <sourceId> to <newId>).
```

con:

```text
object_type = Product
object_id   = 0
```

Per questo `BackOfficeActivity` mantiene separati:

- source/object ID;
- log object ID;
- new object ID.

Il record viene prodotto soltanto dopo il successo della duplicazione.

Resta da verificare manualmente il record prodotto dalla nuova implementazione.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## BULK

### BULK DELETE

BULK DELETE è implementato e preserva i successi parziali ricavando gli ID
riusciti come selezione meno ID falliti presenti nella `BulkProductException`.

Comportamento verificato:

```text
item 1 riuscito -> log
item 2 riuscito -> log
item 3 fallito  -> nessun log
item 4 riuscito -> log
```

### BULK DUPLICATE

BULK DUPLICATE è stato implementato, ma richiede una strategia diversa dal
DELETE.

Il risultato utile è:

```text
sourceProductId => new ProductId
```

e questa mappa deve essere preservata anche quando il bulk termina con una
`BulkProductException`.

Per questo l'infrastruttura bulk conserva i risultati riusciti anche in caso di
errore aggregato. `BulkProductException` espone tali risultati in modo generico;
non conosce `BackOfficeActivity` né il logging.

Il `ProductController` interpreta quei risultati e crea un activity log
DUPLICATE per ogni elemento riuscito.

Restano da verificare manualmente il caso completamente riuscito e il successo
parziale.

### BULK STATUS

ACTIVATE/DEACTIVATE bulk riusano invece la strategia del DELETE:

```text
selected IDs - failed IDs = successful IDs
```

perché non è necessario preservare un nuovo ID.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## ACTIVATE / DEACTIVATE Product

Lo storico include anche activity log relativi allo stato Product, ad esempio:

```text
Product deactivated: <productId>
```

Sono stati aggiunti:

```text
BackOfficeActivityType::ACTIVATE
BackOfficeActivityType::DEACTIVATE
```

e i call site sono stati inseriti nei punti condivisi del `ProductController`:

```text
updateProductStatusByShopConstraint()
toggleProductStatusByShopConstraint()
bulkUpdateProductStatus()
```

Il log viene prodotto soltanto dopo il successo dell'update dello stato.

Per i bulk vengono preservati i successi parziali con la stessa strategia del
BULK DELETE.

L'implementazione è presente ma la verifica manuale in `ps_log` è ancora da
completare. Il formato storico completo di ACTIVATE e i metadata devono essere
confermati prima della chiusura.

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

1. verificare manualmente DUPLICATE singolo;
2. verificare BULK DUPLICATE, incluso successo parziale;
3. verificare ACTIVATE/DEACTIVATE singoli;
4. verificare BULK ACTIVATE/BULK DEACTIVATE;
5. completare la verifica dei metadata storici di tutte le activity Product;
6. aggiungere/completare i test automatici;
7. verificare esplicitamente Back Office / API / CLI;
8. ripulire TODO temporanei;
9. decidere il branch target definitivo;
10. soltanto dopo la validazione completa di Product valutare altre entità
    riutilizzando i punti generici già introdotti.

Nota: `BackOfficeActivityLogger::getMessage()` può essere ulteriormente
semplificato/refactorizzato per mantenere leggibile la gestione dei diversi tipi,
ma questo è un miglioramento interno e non deve cambiare la semantica storica.

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
