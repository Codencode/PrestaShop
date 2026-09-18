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

DELETE e altre operazioni BO fuori dal FormHandler
    -> helper generico di PrestaShopAdminController
    -> BackOfficeActivityLoggerInterface
```

Il call site può essere specifico dell'entità; l'infrastruttura non deve
esserlo.

Prima di creare nuove astrazioni, verificare se questi punti generici sono già
sufficienti.

Non introdurre activity logging nei command handler o middleware globale del
CommandBus soltanto per semplificare l'adozione da parte di nuove entità.

DUPLICATE non è ancora stata implementata. Prima di decidere se usare anche in
quel caso l'helper di `PrestaShopAdminController`, verificare il flusso reale e
la semantica storica di source ID, new ID e log object ID.

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

BULK DELETE è implementato e preserva i successi parziali ricavando gli ID
riusciti come selezione meno ID falliti presenti nella `BulkProductException`.

Comportamento verificato:

```text
item 1 riuscito -> log
item 2 riuscito -> log
item 3 fallito  -> nessun log
item 4 riuscito -> log
```

Per BULK DUPLICATE bisogna invece verificare separatamente come preservare i
risultati delle duplicazioni riuscite, perché saranno necessari almeno source
ID e new ID e non è detto che la stessa strategia del BULK DELETE sia
sufficiente.

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

1. analizzare e implementare DUPLICATE;
2. implementare BULK DUPLICATE;
3. completare i test automatici di CREATE/UPDATE/DELETE/BULK DELETE;
4. completare i test generali e di compatibilità storica;
5. verificare API/CLI no-op;
6. ripulire TODO temporanei;
7. decidere il branch target definitivo;
8. soltanto dopo Product valutare altre entità riutilizzando i punti generici
   già introdotti.

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
