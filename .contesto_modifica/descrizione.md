// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## - descrizione.md - DA ELIMINARE ALLA FINE

Obiettivo: ripristinare su `develop` il logging CRUD storico del Back Office in `ps_log`, mantenendo il comportamento limitato alle operazioni eseguite dal BO e senza coinvolgere API, CLI o altri entry point.

La soluzione deve essere generica e progressivamente riutilizzabile dalle varie entità migrate al nuovo Back Office Symfony.

Architettura proposta:

* Introdurre in `Core` un port `BackOfficeCrudOperationReporterInterface` e un DTO immutabile `CrudOperation`.
* `CrudOperation` deve descrivere almeno:

  * operazione (`create`, `update`, `delete`, `duplicate`);
  * `objectType`;
  * ID sorgente/oggetto;
  * eventuale nuovo ID;
  * ID da salvare in `ps_log`, che deve poter essere `null`;
  * eventuale indicazione bulk.
* Implementare in `PrestaShopBundle` un reporter Symfony che implementa il port Core.
* Il reporter deve emettere un evento Symfony solo quando la richiesta corrente appartiene al Back Office; in API/CLI deve essere un no-op.
* Il contesto BO deve essere identificato tramite un marker sulla `Request`, impostato da un subscriber su `KernelEvents::CONTROLLER` quando il controller appartiene al Back Office.
* Introdurre un evento comune, ad esempio `BackOfficeCrudOperationSucceededEvent`.
* Introdurre un unico `LegacyCrudActivitySubscriber` che ascolta l’evento e converte l’operazione nel corrispondente `LegacyLogger` / `ps_log`, preservando messaggi, `object_type`, `object_id`, severity e `allow_duplicate` legacy.

Per create/update:

* estendere `IdentifiableObject\FormHandler` e relativa factory con supporto opt-in;
* dopo `dataHandler->create()` o `update()` completato senza eccezioni, inviare l’operazione al reporter;
* ogni form handler deve poter dichiarare esplicitamente il proprio `legacyObjectType`;
* se non configurato, nessun log deve essere prodotto.

Per delete:

* il log deve essere emesso solo dopo una cancellazione effettivamente riuscita;
* per il single delete il controller BO può reportare l’operazione dopo il `dispatchCommand()` riuscito;
* per i bulk il report deve avvenire per ogni singolo elemento riuscito, nel punto in cui è noto l’esito individuale, così da preservare i successi parziali come nel legacy.

Per Product bulk delete, il punto è il flusso `BulkDeleteProductHandler::handleSingleAction()` dopo `deleteByShopConstraint()` completato con successo.

Per duplication:

* il log deve essere prodotto solo dopo una duplicazione completamente riuscita;
* nella duplicazione Product singola devono essere preservati i dati legacy:
  `Product duplicated: (from <sourceId> to <newId>).`
  con `object_type = Product` e `object_id = <sourceId>`;
* per la duplication bulk il report deve avvenire dopo ogni singola duplicazione riuscita, anche se il bulk termina successivamente con un’eccezione aggregata;
* il DTO deve separare l’ID necessario per costruire il messaggio dall’`object_id` effettivamente salvato in `ps_log`, perché i comportamenti legacy possono differire.

Non usare:

* middleware globale del CommandBus, perché coinvolgerebbe anche API/CLI;
* hook ObjectModel come `actionObject*DeleteAfter`, perché non garantiscono che l’intera operazione sia terminata con successo;
* chiamate dirette a `PrestaShopLogger` o `LegacyLogger` nei FormHandler/command handler.

La pipeline finale deve essere:

`BO operation -> BackOfficeCrudOperationReporterInterface -> Symfony event -> LegacyCrudActivitySubscriber -> LegacyLogger -> ps_log`

La prima implementazione può usare Product come consumer completo per verificare create, update, delete e duplicate, mantenendo però l’infrastruttura generica per poterla estendere successivamente alle altre entità Back Office.
