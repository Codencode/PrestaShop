// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## - descrizione.md - DA ELIMINARE ALLA FINE

# BACK OFFICE CRUD LOGGING

// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## ------------------------ NOTA

Questa nota descrive la seconda parte del lavoro relativo a:

https://github.com/PrestaShop/PrestaShop/issues/42814

La prima parte è gestita separatamente in:

https://github.com/PrestaShop/PrestaShop/pull/42864

Lo sviluppo di questa parte era stato inizialmente sospeso in attesa di chiarire
l'approccio architetturale adottato nella PR #42864.

Alla luce delle modifiche introdotte nella #42864, questa implementazione deve
seguire lo stesso principio generale:

il codice moderno non deve dipendere direttamente da `LegacyLogger`,
`PrestaShopLogger` o `ps_log`.

Il codice moderno deve usare `Psr\Log\LoggerInterface`; la configurazione Monolog
e il legacy log handler esistente devono occuparsi dell'integrazione con il
sistema storico di logging.

Il logging delle attività CRUD deve inoltre essere best-effort: un problema nel
meccanismo di activity logging non deve trasformare un'operazione CRUD già
riuscita in un errore applicativo.

L'obiettivo è ripristinare il logging CRUD storico del Back Office per le pagine
migrate a Symfony, mantenendo però il meccanismo generico e progressivamente
riutilizzabile da più entità del Back Office.

Esempi:

- Product
- Category
- Customer
- Manufacturer
- Supplier
- altre entità progressivamente migrate al Back Office Symfony

Product deve essere usato come primo consumer completo per validare
l'architettura, ma l'infrastruttura non deve essere Product-specific.

// TODO \<cnc> Valutare solo dopo Product se includere nella stessa PR una seconda
// entità semplice. Evitare di ampliare il perimetro prima di avere validato
// create/update/delete/duplicate/bulk su Product.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Stato attuale del branch

Branch di lavoro:

```text
fix/42814-restore-back-office-crud-logging
```

Nel branch è già presente una prima versione dell'infrastruttura generica:

- `BackOfficeCrudOperationReporterInterface`;
- `CrudOperation`;
- `CrudOperationType`;
- una prima implementazione del reporter;
- una prima integrazione nel generic `IdentifiableObject\FormHandler`;
- un meccanismo per limitare il reporting al contesto Back Office.

L'implementazione non è però ancora completa.

In particolare:

- Product non è ancora completamente configurato come consumer del meccanismo;
- il wiring della `FormHandlerFactory` deve essere completato;
- nel `FormHandler` è stato iniziato soltanto il reporting dell'update;
- create non è ancora collegato;
- delete, duplicate e operazioni bulk non sono ancora implementate.

La parte architetturale relativa al logging è stata invece riallineata alla
direzione emersa nella #42864:

- `LegacyCrudActivitySubscriber` è stato eliminato;
- `BackOfficeCrudOperationSucceededEvent` è stato eliminato;
- `SymfonyBackOfficeCrudOperationReporter` non dispatcha più un evento soltanto
  per raggiungere il logger;
- il reporter usa direttamente `Psr\Log\LoggerInterface`;
- la costruzione del messaggio storico e del context (`object_type`,
  `object_id`, `allow_duplicate`) è stata spostata nel reporter;
- il nuovo codice CRUD non dipende direttamente da `LegacyLogger`.

La pipeline corrente è quindi:

```text
CrudOperation
    -> BackOfficeCrudOperationReporterInterface
    -> SymfonyBackOfficeCrudOperationReporter
    -> LoggerInterface
    -> Monolog
    -> legacy log handler esistente
    -> LegacyLogger / PrestaShopLogger
    -> ps_log
```

Le occorrenze residue di `LegacyLogger` nel progetto appartengono
all'infrastruttura legacy esistente e non al nuovo codice CRUD.

Resta da verificare che il livello/canale Monolog utilizzato dal reporter
instradi effettivamente i record verso `ps_log` senza alterare la severity
storica.

//////////////////////////////////////////////////////////////////////////////////////////////////////

## Obiettivo

Ripristinare il comportamento storico di logging delle operazioni CRUD eseguite
dal Back Office, garantendo che:

- la soluzione sia generica;
- l'adozione sia opt-in e progressiva;
- vengano loggate solo operazioni eseguite dal Back Office;
- API, CLI e altri entry point non vengano coinvolti;
- il codice moderno non dipenda direttamente dal sistema di logging legacy;
- il logging sia best-effort e non possa rompere una CRUD già riuscita;
- i successi parziali nelle operazioni bulk vengano preservati;
- messaggi e metadati storici restino compatibili con il comportamento precedente;
- il comportamento storico relativo all'employee associato al log venga verificato,
  senza introdurre preventivamente una nuova dipendenza o un nuovo requisito se il
  legacy logging lo gestisce già correttamente.

La pipeline prevista è:

```text
operazione BO
    -> BackOfficeCrudOperationReporterInterface
    -> BackOfficeCrudOperationReporter
    -> LoggerInterface
    -> Monolog
    -> legacy log handler esistente
    -> LegacyLogger / PrestaShopLogger
    -> ps_log
```

`LegacyLogger`, `PrestaShopLogger` e `ps_log` appartengono soltanto alla parte
infrastrutturale già esistente della pipeline.

Il nuovo codice non deve dipendere direttamente da essi.

---

## Contratto generico delle operazioni CRUD

Mantenere in Core un port generico:

```php
interface BackOfficeCrudOperationReporterInterface
{
    public function report(CrudOperation $operation): void;
}
```

Mantenere un piccolo DTO immutabile `CrudOperation`.

Il DTO deve contenere almeno:

- tipo di operazione:
  - `create`
  - `update`
  - `delete`
  - `duplicate`
- object type;
- ID sorgente / ID dell'oggetto;
- eventuale nuovo ID;
- ID da associare alla voce di log;
- eventuale informazione relativa a operazioni bulk.

Il DTO deve mantenere separati:

- source ID;
- new ID;
- log object ID.

Questo è importante perché l'ID necessario per costruire il messaggio storico
può differire dall'`object_id` effettivamente salvato in `ps_log`.

Esempio duplication Product:

```text
Product duplicated: (from <sourceId> to <newId>).
```

ma l'`object_id` storico può comunque dover fare riferimento all'oggetto
sorgente.

Il contratto moderno deve usare terminologia neutra e non esporre dettagli
dell'implementazione legacy.

Preferire quindi nomi come:

```text
crudActivityObjectType
```

invece di:

```text
legacyObjectType
```

// TODO \<cnc> Valutare il naming definitivo di `CrudOperation`, delle relative
// factory statiche e di `crudActivityObjectType` prima di aprire la PR.

---

## Implementazione del reporter

Fornire un'implementazione Symfony di:

```php
BackOfficeCrudOperationReporterInterface
```

L'implementazione deve dipendere da:

```php
Psr\Log\LoggerInterface
```

e non deve chiamare direttamente:

```text
LegacyLogger
PrestaShopLogger
ps_log
```

La configurazione Monolog esistente e il relativo legacy handler devono
occuparsi di inoltrare il messaggio al sistema storico di logging di PrestaShop.

Il reporter deve essere responsabile di:

1. verificare che l'operazione corrente appartenga al Back Office;
2. convertire `CrudOperation` nel messaggio storico corretto;
3. costruire il context di logging corretto;
4. costruire soltanto i metadati necessari al comportamento storico;
5. chiamare `LoggerInterface`;
6. non fare nulla fuori dal contesto Back Office;
7. non propagare errori del sistema di activity logging verso l'operazione CRUD.

Esempio di context:

```php
[
    'object_type' => $operation->objectType,
    'object_id' => $operation->logObjectId,
    'allow_duplicate' => true,
]
```

I valori esatti devono preservare il comportamento storico per ogni operazione.

Il logging deve essere protetto in modo analogo a quanto fatto nella #42864:
un errore del logger, del legacy handler o della persistenza in `ps_log` non deve
rompere un'operazione CRUD già completata.

Concettualmente:

```php
public function report(CrudOperation $operation): void
{
    if (!$this->isBackOfficeRequest()) {
        return;
    }

    try {
        $this->logger->info(
            $this->buildMessage($operation),
            $this->buildContext($operation)
        );
    } catch (\Throwable) {
        // Activity logging must never break a successful CRUD operation.
    }
}
```

// TODO \<cnc> Verificare i messaggi legacy esatti per create, update, delete e
// duplicate per Product.

// TODO \<cnc> Verificare quali metadati vengono realmente consumati dal legacy
// Monolog handler e quali devono essere esplicitamente passati nel context.

---

## Employee associato al log

La #42864 introduce la possibilità di propagare esplicitamente `id_employee`
attraverso il context di `LoggerInterface`, perché nel caso specifico del login
l'employee appena autenticato non è necessariamente ancora disponibile nel
legacy `Context`.

Per le normali operazioni CRUD del Back Office la situazione è diversa:
l'operazione viene eseguita all'interno di una sessione Back Office già
autenticata e il legacy logging può essere già in grado di associare
correttamente l'employee corrente.

Per questo motivo `id_employee` non deve essere considerato un requisito
obbligatorio di questa PR.

Il context iniziale del CRUD logging dovrebbe quindi limitarsi ai metadati
necessari già supportati dal bridge esistente:

```php
[
    'object_type' => $operation->objectType,
    'object_id' => $operation->logObjectId,
    'allow_duplicate' => true,
]
```

Durante i test bisogna verificare che l'`id_employee` salvato in `ps_log`
corrisponda correttamente all'employee Back Office che ha eseguito
l'operazione.

Solo se questa verifica fallisce bisogna valutare se:

1. fare affidamento sulla modifica introdotta dalla #42864, se già disponibile
   nel branch target;
2. oppure estendere anche qui il bridge `LegacyLogger` per supportare
   `id_employee` esplicito.

Non duplicare preventivamente nella CRUD PR modifiche al bridge legacy già
introdotte dalla #42864 senza una necessità dimostrata.

// TODO \<cnc> Verificare nei test create/update/delete/duplicate che `ps_log`
// contenga l'employee corretto senza passare esplicitamente `id_employee`.

---

## Rilevamento del contesto Back Office

Il meccanismo di reporting deve essere limitato al Back Office.

Non bisogna basarsi soltanto sull'assunzione che un determinato servizio venga
attualmente usato solo da un controller BO.

La Request corrente deve essere esplicitamente riconoscibile come appartenente
al Back Office.

Nel branch esiste già una prima implementazione basata su uno scope/marker.
Il principio può essere mantenuto, ma prima di introdurre o consolidare un
attributo custom bisogna verificare se `develop` / `9.2.x` espongono già un
meccanismo affidabile per riconoscere una Request Back Office.

Se non esiste un meccanismo riutilizzabile, un subscriber su:

```php
KernelEvents::CONTROLLER
```

può analizzare il controller risolto e impostare un attributo sulla Request
quando il controller appartiene al Back Office.

Concettualmente:

```text
KernelEvents::CONTROLLER
        |
        v
controller Back Office?
        |
       sì
        |
        v
request.attributes['_prestashop_back_office'] = true
```

Il reporter verifica poi questo marker.

Esempio concettuale:

```php
public function report(CrudOperation $operation): void
{
    $request = $this->requestStack->getCurrentRequest();

    if (
        null === $request
        || true !== $request->attributes->get('_prestashop_back_office')
    ) {
        return;
    }

    // Conversione dell'operazione nel messaggio storico e logging.
}
```

Comportamento atteso:

```text
Back Office -> log
API         -> no-op
CLI         -> nessuna Request corrente -> no-op
altri entry point -> no-op
```

Questo permette di usare il reporter anche da handler più bassi nello stack,
senza rischiare di loggare accidentalmente operazioni provenienti da API o CLI.

// TODO \<cnc> Verificare se esiste già nel progetto un marker o un meccanismo
// affidabile per distinguere le Request BO prima di introdurne uno nuovo.

---

## Create e update

Le operazioni create e update devono sfruttare l'infrastruttura generica
`IdentifiableObject\FormHandler`.

Estendere:

```text
IdentifiableObject\FormHandler
```

e la relativa factory con supporto opt-in al reporting delle attività CRUD.

Ogni FormHandler deve poter configurare esplicitamente il proprio object type
per il CRUD logging.

Esempio:

```text
crudActivityObjectType: 'Product'
```

Se non viene configurato alcun object type:

```text
crudActivityObjectType: null
```

non deve essere prodotto alcun report.

Questo mantiene il comportamento retrocompatibile e permette alle varie entità
di adottare il meccanismo progressivamente.

Il branch attuale contiene già una prima integrazione dell'update, ma il report
deve essere spostato nel punto in cui l'intera operazione del `FormHandler` è
terminata correttamente.

Il report non deve quindi avvenire immediatamente dopo:

```php
$this->dataHandler->update($id, $data);
```

se successivamente il `FormHandler` deve ancora processare extra properties,
hook o altre operazioni che fanno parte dello stesso aggiornamento.

Il flusso desiderato è:

```text
dataHandler->update()
    -> extra properties
    -> hook / post processing
    -> CRUD report
    -> return
```

La stessa regola vale per create:

```text
dataHandler->create()
    -> extra properties
    -> hook / post processing
    -> CRUD report
    -> return
```

Esempio concettuale:

```php
if (null !== $this->crudActivityObjectType) {
    $this->crudOperationReporter->report(
        CrudOperation::update(
            $this->crudActivityObjectType,
            (int) $id,
        )
    );
}
```

Il create deve essere aggiunto con la stessa logica.

Questo evita di duplicare il codice di logging create/update nei singoli
controller Back Office.

// TODO \<cnc> Completare il wiring della `FormHandlerFactory` con il reporter.

// TODO \<cnc> Configurare Product con `crudActivityObjectType: 'Product'`.

// TODO \<cnc> Verificare la posizione esatta del report nel `FormHandler` affinché
// avvenga soltanto dopo il completamento di tutte le operazioni considerate parte
// del create/update.

---

## Delete

Le operazioni delete devono usare lo stesso reporter generico, ma il report deve
avvenire nel punto in cui la cancellazione è certamente terminata con successo.

Non bisogna forzare delete dentro l'astrazione del FormHandler.

Per un delete singolo il report può avvenire immediatamente dopo il
completamento corretto del relativo command o nel punto applicativo che
rappresenta realmente il successo dell'operazione.

Esempio concettuale:

```php
$this->dispatchCommand(
    new DeleteProductCommand($productId)
);

$this->crudOperationReporter->report(
    CrudOperation::delete('Product', $productId)
);
```

Se il command lancia un'eccezione, la chiamata al reporter non viene raggiunta.

Il punto di integrazione può variare a seconda dell'entità, ma reporter e DTO
devono restare gli stessi.

// TODO \<cnc> Mappare il punto di successo effettivo del delete Product e
// verificare se il reporter deve essere invocato dal controller, dall'handler o
// da un altro servizio condiviso.

---

## Duplication

Anche la duplicazione deve usare il reporter generico.

Il log deve essere prodotto soltanto dopo che la duplicazione è terminata
completamente con successo.

L'operazione deve preservare tutte le informazioni richieste dal log storico.

Per Product:

```text
Product duplicated: (from <sourceId> to <newId>).
```

Il DTO deve quindi mantenere distinti:

- source ID;
- new ID;
- log object ID.

Esempio concettuale:

```php
$this->crudOperationReporter->report(
    CrudOperation::duplicate(
        objectType: 'Product',
        sourceId: $productId,
        newId: $newProductId,
        logObjectId: $productId,
    )
);
```

// TODO \<cnc> Verificare il valore storico di `object_id` per la duplicazione
// Product e se altre entità usano semantiche diverse.

---

## Operazioni bulk

Le operazioni bulk devono preservare i successi parziali.

Non bisogna produrre log soltanto quando l'intero bulk termina con successo.

Il comportamento deve essere:

```text
item 1 riuscito -> report
item 2 riuscito -> report
item 3 fallito  -> nessun report
item 4 riuscito -> report
```

Questo è necessario per mantenere il comportamento storico del Back Office.

### Product bulk delete

Per Product bulk delete il punto corretto deve essere la singola operazione
riuscita.

Nel branch originariamente analizzato, il candidato era:

```text
BulkDeleteProductHandler::handleSingleAction()
```

Il report deve avvenire soltanto dopo che la cancellazione del singolo prodotto
è terminata correttamente.

Se la cancellazione del singolo prodotto lancia un'eccezione, non deve essere
creato alcun log per quel prodotto.

Poiché il reporter verifica il contesto Back Office, il fatto di richiamarlo da
un command handler non deve causare logging per API o CLI.

// TODO \<cnc> Ricontrollare la struttura attuale di `BulkDeleteProductHandler`
// su branch target prima di fissare definitivamente il punto di integrazione.

### Bulk duplication

La bulk duplication deve seguire la stessa regola.

Ogni elemento duplicato con successo deve essere riportato singolarmente, anche
se l'operazione bulk complessiva termina successivamente con un'eccezione
aggregata.

La coppia source/new ID dei successi non deve andare persa quando un altro
elemento fallisce.

Se l'infrastruttura bulk attuale scarta i risultati delle azioni riuscite quando
lancia una `BulkProductException`, estendere il modello del risultato o
dell'eccezione in modo che i risultati riusciti restino disponibili al chiamante.

Questa modifica deve rimanere generica e non deve contenere alcuna logica
specifica di logging o Back Office.

// TODO \<cnc> Verificare il comportamento corrente di `AbstractBulkHandler` e
// `BulkProductException` e scegliere la modifica minima necessaria per non perdere
// i risultati parzialmente riusciti.

---

## Messaggi storici e context

Il reporter deve preservare messaggi e metadati storici attesi dal vecchio
activity log del Back Office.

A seconda dell'operazione, questo comprende:

- message;
- `object_type`;
- `object_id`;
- severity;
- `allow_duplicate`.

L'`id_employee` deve essere verificato come parte della compatibilità storica,
ma non va aggiunto obbligatoriamente al context se il bridge legacy lo ricava
già correttamente dal contesto Back Office.

Il codice moderno nel Core non deve sapere come questi valori vengono
persistiti.

Il Core deve soltanto descrivere l'operazione CRUD.

L'implementazione Symfony del reporter deve invece tradurre l'operazione
generica nel messaggio e nel context richiesti dall'integrazione Monolog/legacy
esistente.

// TODO \<cnc> Centralizzare la costruzione dei messaggi storici evitando switch
// sparsi tra FormHandler, controller e command handler.

// TODO \<cnc> Verificare i messaggi legacy direttamente dal comportamento
// precedente e non ricostruirli soltanto a memoria.

---

## Collocazione delle classi

Separare chiaramente il contratto Core dall'implementazione Symfony.

Indicativamente:

```text
src/Core/BackOffice/Crud/
    BackOfficeCrudOperationReporterInterface.php
    CrudOperation.php
    CrudOperationType.php
```

e:

```text
src/PrestaShopBundle/BackOffice/Crud/
    BackOfficeCrudOperationReporter.php
    BackOfficeCrudOperationScope.php
```

Un eventuale subscriber necessario esclusivamente a marcare la Request BO può
restare sotto `PrestaShopBundle/EventSubscriber`.

Non mantenere classi fisicamente sotto `src/Core` con namespace
`PrestaShopBundle`.

Rimuovere inoltre i file/eventi non più necessari dopo il passaggio diretto a
`LoggerInterface`.

---

## Adozione progressiva

L'infrastruttura deve essere generica, ma la migrazione deve avvenire
progressivamente.

Primo consumer:

```text
Product
```

Product deve permettere di validare il flusso completo per:

- create;
- update;
- delete;
- duplicate;
- bulk delete;
- bulk duplicate.

Una volta validato il meccanismo, altre entità Back Office potranno aderire alla
stessa infrastruttura.

Esempi:

```text
Category
Customer
Manufacturer
Supplier
...
```

Nel reporter e nel DTO non deve esserci alcuna assunzione Product-specific.

Per la prima PR è preferibile evitare di ampliare il perimetro prima di avere
validato completamente Product.

---

## Da non usare

Non usare middleware globale del CommandBus.

Motivo:

```text
coinvolgerebbe anche API, CLI e altri entry point che usano il command bus.
```

Non usare hook ObjectModel come:

```text
actionObject*DeleteAfter
```

Motivo:

```text
non garantiscono che l'intera operazione applicativa di livello superiore sia
terminata correttamente.
```

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
reporter CRUD
subscriber CRUD
```

Non introdurre un'infrastruttura specifica per Product.

Non introdurre un livello evento Symfony/subscriber soltanto per inoltrare
l'operazione al logger.

Un evento ha senso solo se l'attività CRUD diventa un vero evento applicativo
con consumer indipendenti dal logging.

---

## Architettura finale prevista

```text
                         CREATE / UPDATE
                               |
                    generic FormHandler
                               |
                               v
             BackOfficeCrudOperationReporterInterface
                               ^
                               |
            DELETE / DUPLICATE / BULK successful item
                               |
                    appropriate success point


              BackOfficeCrudOperationReporter
                               |
                    Back Office context check
                               |
                    build legacy-compatible
                      message + context
                               |
                        LoggerInterface
                               |
                            Monolog
                               |
                 existing legacy log handler
                               |
              LegacyLogger / PrestaShopLogger
                               |
                            ps_log
```

Il principio chiave è:

```text
Il Core descrive l'operazione.

L'infrastruttura Back Office decide se deve essere loggata e la traduce nel
messaggio/context compatibile con il comportamento storico.

Monolog gestisce l'integrazione con il sistema legacy di logging.

Un errore dell'activity logging non deve compromettere una CRUD già riuscita.
```

---

## Ordine suggerito per proseguire

La parte di riallineamento architetturale con la #42864 è stata completata per
quanto riguarda la rimozione della dipendenza diretta da `LegacyLogger`.

I prossimi passi sono:

1. verificare end-to-end che `LoggerInterface` / Monolog inoltrino il CRUD log a
   `ps_log` mantenendo la severity storica;
2. rendere definitivamente il reporting best-effort con protezione dagli errori
   del logger, se non già applicato;
3. sistemare eventuali namespace/collocazioni delle classi ancora incoerenti;
4. rinominare `legacyObjectType` in `crudActivityObjectType`, se ancora presente;
5. completare il wiring della `FormHandlerFactory`;
6. configurare Product come primo consumer;
7. completare create e update nel generic `FormHandler`;
8. implementare delete e duplicate nei rispettivi punti di successo;
9. implementare bulk delete e bulk duplicate preservando i successi parziali;
10. verificare nei test che `ps_log` mantenga correttamente anche l'employee
    storico senza richiedere `id_employee` esplicito;
11. aggiungere test unitari/integration/functional adeguati;
12. solo dopo la validazione completa di Product valutare l'adozione da parte di
    altre entità.

---

## Branch target

La #42864 è stata spostata da `develop` a `9.2.x` perché è una bug fix mirata.

Questa seconda parte è più ampia e introduce un'infrastruttura generica per il
CRUD logging.

Non assumere automaticamente che debba usare lo stesso branch target della
#42864.

Prima di aprire la PR:

- verificare il diff risultante su `9.2.x`;
- verificare il diff risultante su `develop`;
- valutare quale target sia coerente con il perimetro e con le indicazioni dei
  maintainer.

// TODO \<cnc> Decidere il branch target definitivo prima della PR.

---

## Riferimenti

Issue:

- https://github.com/PrestaShop/PrestaShop/issues/42814

Prima parte / PR collegata:

- https://github.com/PrestaShop/PrestaShop/pull/42864

Branch di lavoro:

- https://github.com/PrestaShop/PrestaShop/compare/develop...Codencode:PrestaShop:fix/42814-restore-back-office-crud-logging?expand=1

L'implementazione di questa seconda parte deve restare coerente con la direzione
emersa nella #42864:

- il codice moderno usa `LoggerInterface`;
- nessuna nuova dipendenza diretta dal layer di logging legacy;
- i metadati CRUD già supportati dal bridge vengono propagati tramite il context;
- eventuali estensioni del bridge, come `id_employee`, vengono aggiunte soltanto
  se necessarie e senza duplicare modifiche già presenti nella #42864;
- il logging è best-effort e non deve compromettere l'operazione principale.
