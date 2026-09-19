// TODO <cnc> ########## BACK OFFICE ACTIVITY LOGGING ########## - CONTEXT-HANDOFF.md - DA ELIMINARE ALLA FINE

# CONTEXT HANDOFF — PrestaShop #42814 Back Office Activity Logging

## IMPORTANTE: cosa caricare in una nuova chat

Per riprendere correttamente questo lavoro, eseguire prima lo script:

```text
.contesto_modifica/create-prestashop-branch-zip.ps1
```

Lo script prepara automaticamente:

```text
.contesto_modifica/chat-upload
```

Caricare nella nuova chat **tutti i file presenti in quella cartella**.

Normalmente saranno:

```text
CONTEXT-HANDOFF.md
descrizione.md
step-pr-crud-logging.md
prestashop-42814-current.zip
```

e, se sono presenti cancellazioni o rename:

```text
branch-diff-current.patch
```

`prestashop-42814-current.zip` è obbligatorio per una review tecnica
affidabile e deve essere considerato la fonte reale dello stato corrente del
codice del branch.

Quando apri la nuova chat puoi scrivere semplicemente:

> Continua il lavoro descritto in CONTEXT-HANDOFF.md. Ho caricato tutti i file
> generati nella cartella chat-upload: verifica sempre il codice nello ZIP
> prima di propormi modifiche.

---

## Come preparare automaticamente i file da caricare

Per evitare di preparare manualmente ZIP, patch e file di contesto, usare:

```text
.contesto_modifica/create-prestashop-branch-zip.ps1
```

Lo script crea automaticamente la cartella:

```text
.contesto_modifica/chat-upload
```

La cartella viene ricreata a ogni esecuzione e contiene tutti i file da
caricare nella nuova chat.

Normalmente contiene:

```text
CONTEXT-HANDOFF.md
descrizione.md
step-pr-crud-logging.md
prestashop-42814-current.zip
```

Se vengono rilevati file eliminati o rinominati, viene aggiunto anche:

```text
branch-diff-current.patch
```

### Cosa contiene lo ZIP del branch

`prestashop-42814-current.zip` include automaticamente:

- file modificati e già committati nel branch rispetto al branch base;
- modifiche non ancora committate;
- modifiche già in staging;
- file nuovi non ancora tracciati da Git.

La struttura originale delle directory del repository viene mantenuta.

La cartella generata `.contesto_modifica/chat-upload` viene esclusa
dall'esportazione, in modo da evitare che gli export precedenti finiscano
ricorsivamente dentro lo ZIP.

### Esecuzione consigliata su Windows / PowerShell

Posizionarsi prima nella root del repository PrestaShop, ad esempio:

```powershell
cd D:\cms\Prestashop\_Github\PrestaShop-dev
```

Quindi eseguire:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\.contesto_modifica\create-prestashop-branch-zip.ps1"
```

Questo è il comando consigliato nel setup corrente perché evita di modificare
in modo permanente la Execution Policy del sistema.

Al termine lo script mostra il percorso della cartella:

```text
.contesto_modifica\chat-upload
```

e l'elenco dei file pronti da caricare.

### Branch base diverso

Il branch base predefinito è:

```text
develop
```

Se necessario è possibile specificarne uno diverso:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\.contesto_modifica\create-prestashop-branch-zip.ps1" -BaseBranch "9.2.x"
```

### Cosa caricare nella nuova chat

Aprire:

```text
.contesto_modifica/chat-upload
```

e caricare i file presenti al suo interno.

`prestashop-42814-current.zip` deve essere considerato la fonte reale dello
stato corrente del codice del branch.

`branch-diff-current.patch`, quando presente, permette di verificare anche file
eliminati o rinominati.

---

## Obiettivo del lavoro

Issue:

https://github.com/PrestaShop/PrestaShop/issues/42814

PR collegata / prima parte:

https://github.com/PrestaShop/PrestaShop/pull/42864

Branch di lavoro:

```text
fix/42814-restore-back-office-crud-logging
```

Obiettivo: ripristinare lo storico activity logging delle operazioni Back Office
migrate a Symfony senza introdurre nuove dipendenze dirette dal sistema legacy
di logging.

---

## Decisione architetturale principale

La #42864 ha chiarito la direzione:

```text
NON:
modern code -> LegacyLogger

SÌ:
modern code -> Psr\Log\LoggerInterface -> Monolog -> existing legacy handler -> ps_log
```

Il nuovo codice non deve chiamare direttamente:

```text
LegacyLogger
PrestaShopLogger
ps_log
```

Il logging deve essere best-effort: un problema nel logging non deve rompere
un'operazione già riuscita.

`BackOfficeActivityLogger::log()` protegge l'intero flusso con `try/catch
(Throwable)`, includendo:

```text
scope check
message translation/formatting
context creation
PSR logger / Monolog
```

quindi un errore in qualunque fase dell'activity logging non deve propagarsi
all'operazione Back Office già riuscita.

---

## Naming attuale

Inizialmente il codice utilizzava concetti come:

```text
CrudOperation
CrudOperationType
BackOfficeCrudOperationReporterInterface
```

Questo naming è stato abbandonato perché l'astrazione non esegue una CRUD:
descrive un'attività Back Office da registrare.

Naming attuale:

```text
BackOfficeActivity
BackOfficeActivityType
BackOfficeActivityLoggerInterface
BackOfficeActivityLogger
BackOfficeActivityScope
BackOfficeActivityScopeSubscriber
```

Struttura:

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

---

## Pipeline attuale

```text
Back Office operation
    -> BackOfficeActivity
    -> BackOfficeActivityLoggerInterface
    -> BackOfficeActivityLogger
    -> LoggerInterface
    -> Monolog
    -> existing legacy handler
    -> LegacyLogger / PrestaShopLogger
    -> ps_log
```

`BackOfficeActivityLoggerInterface` viene collegata all'implementazione concreta
tramite alias nel container Symfony.

Il logger concreto usa esplicitamente il servizio core:

```text
@logger
```

---

## Scope Back Office

Il logger deve essere no-op fuori dal Back Office.

`BackOfficeActivityScopeSubscriber` marca la Request BO.

`BackOfficeActivityScope` viene verificato dal logger prima di scrivere.

Il subscriber NON è `final` perché:

```text
PrestaShopBundle\EventSubscriber\
```

viene registrato con:

```yaml
lazy: true
```

e Symfony deve poter generare il proxy lazy.

---

## Product opt-in

Il generic FormHandler supporta un parametro opt-in:

```text
activityLogObjectType
```

Il default è:

```text
null
```

quindi nessun log viene prodotto per gli handler che non hanno esplicitamente
aderito.

Product usa:

```text
Product
```

come `activityLogObjectType`.

---

## UPDATE Product

UPDATE Product è stato implementato e provato manualmente.

Il salvataggio genera una voce in:

```text
ps_log
```

Per il log UPDATE usare direttamente:

```php
(int) $id
```

NON usare:

```php
resolveExtraPropertyEntityId()
```

per determinare l'ID del log.

Questa riga:

```php
$entityId = $this->resolveExtraPropertyEntityId($newId ?? $id);
```

esiste già in `develop` e appartiene alla funzionalità ExtraProperty.

Quindi:

```text
$entityId
-> ExtraProperty

(int) $id
-> activity log UPDATE
```

Il log UPDATE viene prodotto nel punto finale del `FormHandler`, dopo Extra
Properties e hook finale, così un errore precedente impedisce la scrittura
dell'activity log.

---

## CREATE Product

CREATE Product è stato implementato in `FormHandler::handleFormCreate()` e
provato manualmente.

Per il log CREATE viene usato direttamente l'ID restituito da:

```php
$this->dataHandler->create($data);
```

senza riutilizzare `resolveExtraPropertyEntityId()` per il logging.

Anche CREATE viene loggato soltanto al termine del flusso riuscito del
`FormHandler`, dopo Extra Properties e hook finale.

---

## DELETE Product

DELETE Product è stato implementato senza aggiungere activity logging nei
command handler.

È stato aggiunto un helper protetto e generico in:

```text
PrestaShopAdminController
```

che inoltra un `BackOfficeActivity` a:

```text
BackOfficeActivityLoggerInterface
```

`ProductController` usa questo helper soltanto dopo il successo di
`DeleteProductCommand` nei tre flussi:

```text
all shops
single shop
shop group
```

Quindi, se il command fallisce, nessun activity log viene prodotto.

Il comportamento individuato è coerente con lo storico per:

```text
message: Product deletion
object:  Product
object_id: product ID
```

La verifica manuale del DELETE Product in `ps_log` è stata eseguita con esito
positivo. Restano da completare i test automatici e l'eventuale verifica
esaustiva di tutti i metadata storici.

---

## BULK DELETE Product

BULK DELETE Product è stato implementato e verificato manualmente.

Il controller determina gli ID cancellati con successo come differenza tra:

```text
ID selezionati - ID presenti in BulkProductException
```

e produce un activity log `DELETE` per ogni Product riuscito tramite lo stesso
helper generico di `PrestaShopAdminController` usato dal DELETE singolo.

Questo preserva i successi parziali:

```text
item riuscito -> log
item fallito  -> nessun log
```

Il comportamento resta quindi coerente con lo storico, dove ogni cancellazione
riuscita viene registrata anche se il bulk complessivo contiene errori.

È stato inoltre corretto il flusso `all shops` affinché restituisca la response
del bulk helper, preservando la gestione degli errori parziali.

Restano da aggiungere/completare i test automatici del bulk delete, inclusi i
casi di successo parziale.

---

## Pattern di adozione per altre entità

L'infrastruttura deve restare generica; può invece essere specifico dell'entità
il punto in cui viene invocato il logger.

Pattern attuale:

```text
CREATE / UPDATE
    -> generic FormHandler, quando l'entità usa quel flusso

DELETE / DUPLICATE / STATUS e altre operazioni BO fuori dal FormHandler
    -> helper generico di PrestaShopAdminController
    -> BackOfficeActivityLoggerInterface

BULK
    -> preservare sempre i successi parziali
    -> costruire un BackOfficeActivity per ogni elemento riuscito
```

Per future entità, riutilizzare prima questi punti generici invece di introdurre
nuove astrazioni.

Non spostare il logging nei command handler e non introdurre middleware globale
del CommandBus soltanto per centralizzare le chiamate.

### Procedura consigliata per una nuova entità

Prima di implementare il logging per una nuova entità:

1. ricostruire il comportamento storico reale e le operazioni che venivano
   registrate;
2. verificare per ogni operazione:
   - message;
   - `object_type`;
   - `object_id`;
   - severity;
   - `allow_duplicate`;
   - employee;
3. individuare il punto finale di successo dell'operazione;
4. usare il generic `FormHandler` per CREATE/UPDATE quando disponibile;
5. usare l'helper generico di `PrestaShopAdminController` per operazioni
   controller-driven fuori dal `FormHandler`;
6. mantenere separati nel DTO:
   - source/object ID;
   - log object ID;
   - eventuale new object ID;
   - flag bulk;
7. per i bulk loggare ogni singolo successo e non loggare gli elementi falliti;
8. se il bulk produce dati aggiuntivi necessari al log, preservarli nel
   contratto bulk senza introdurre dipendenze dall'activity logging;
9. verificare che API, CLI e altri entry point non Back Office restino no-op;
10. considerare l'entità completata soltanto dopo la verifica dei record reali
    in `ps_log`.

Il Product ha mostrato che `object_id` non deve essere derivato automaticamente
dal source/new ID: per DUPLICATE lo storico usa `object_id = 0`, pur mantenendo
source ID e new ID nel messaggio.

---

## DUPLICATE Product

DUPLICATE singolo è stato implementato usando lo stesso helper generico di
`PrestaShopAdminController`.

Il comportamento storico verificato manualmente è:

```text
message: Product duplicated: (from <sourceId> to <newId>).
object_type: Product
object_id: 0
```

`BackOfficeActivity` mantiene quindi separati:

```text
object/source ID -> source Product ID
log object ID    -> 0
new object ID    -> nuovo Product ID
```

Il log viene prodotto soltanto dopo il successo di `DuplicateProductCommand`.

La verifica manuale finale del record prodotto dalla nuova implementazione resta
da completare.

---

## BULK DUPLICATE Product

BULK DUPLICATE è stato implementato preservando la mappa:

```text
sourceProductId => new ProductId
```

necessaria per produrre lo stesso messaggio della duplicazione singola.

A differenza del BULK DELETE, in caso di errore parziale non è sufficiente
calcolare:

```text
selected IDs - failed IDs
```

perché serve conoscere anche il nuovo ID generato per ogni duplicazione riuscita.

Per questo `AbstractBulkHandler` conserva i risultati riusciti anche quando deve
lanciare una `BulkProductException`, e `BulkProductException` espone tali
risultati senza conoscere nulla dell'activity logging.

Il controller usa quindi i risultati riusciti per produrre un
`BackOfficeActivity::DUPLICATE` per ogni duplicazione completata.

Questa modifica deve restare generica:

```text
bulk infrastructure -> successful action results
ProductController    -> interpreta i risultati come sourceId => ProductId
activity logging     -> resta fuori dai command/bulk handler
```

Restano da verificare manualmente:

```text
bulk completamente riuscito
bulk con successo parziale
record ps_log per ogni duplicazione riuscita
nessun log per gli elementi falliti
```

---

## ACTIVATE / DEACTIVATE Product

Il comportamento storico include anche i cambi di stato Product, ad esempio:

```text
Product deactivated: <productId>
```

Sono stati aggiunti i tipi:

```text
ACTIVATE
DEACTIVATE
```

e il logging è stato integrato nei punti comuni del `ProductController`:

```text
updateProductStatusByShopConstraint()
toggleProductStatusByShopConstraint()
bulkUpdateProductStatus()
```

Per i bulk status viene usata la stessa strategia del BULK DELETE:

```text
ID selezionati - ID falliti = ID riusciti
```

e viene prodotto un activity log per ogni Product aggiornato con successo.

Lo stato corrente è:

```text
ACTIVATE / DEACTIVATE singolo -> implementato, verifica manuale pending
BULK ACTIVATE / DEACTIVATE   -> implementato, verifica manuale pending
```

Prima di considerarli chiusi verificare in `ps_log` almeno:

```text
message
object_type
object_id
severity
allow_duplicate
employee
```

In particolare il formato storico completo di ACTIVATE e i metadata del record
devono ancora essere confermati.

---

## Prossimo step immediato

Prima di considerare Product completo:

1. verificare manualmente DUPLICATE singolo;
2. verificare BULK DUPLICATE, incluso un caso di successo parziale;
3. verificare ACTIVATE e DEACTIVATE singoli;
4. verificare BULK ACTIVATE e BULK DEACTIVATE;
5. confrontare i metadata prodotti con lo storico;
6. aggiungere/completare i test automatici;
7. verificare esplicitamente scope Back Office / API / CLI;
8. ripulire TODO temporanei.

Dopo la validazione completa del Product, valutare l'adozione da parte di altre
entità usando il pattern documentato sopra.

---

## Regole da mantenere

- Product è il primo consumer completo.
- Non allargare il perimetro ad altre entità troppo presto.
- Non usare middleware globale CommandBus.
- Non usare hook ObjectModel come soluzione generica.
- Non reintrodurre un evento Symfony soltanto per arrivare al logger.
- API e CLI non devono produrre questi activity log.
- I bulk devono preservare i successi parziali.
- Il logging non deve rompere l'operazione principale.
- Verificare sempre il comportamento storico reale prima di fissare message,
  object_id, severity o altri metadata.

---

## File di documentazione

Nel branch vengono mantenuti temporaneamente:

```text
.contesto_modifica/descrizione.md
.contesto_modifica/step-pr-crud-logging.md
```

Sono file di sviluppo da eliminare prima della PR finale.

`descrizione.md` contiene le decisioni architetturali.

`step-pr-crud-logging.md` contiene lo stato operativo/checklist.

Questo `CONTEXT-HANDOFF.md` serve invece per trasferire il lavoro tra chat e non
è necessariamente un file da committare nel repository.
