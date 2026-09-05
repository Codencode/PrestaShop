# Contesto — Admin Bar nel Front Office

### Stato della discussione — 5 settembre 2026

Questo file serve a riprendere il lavoro in altre chat senza dipendere dalla cronologia della conversazione. Leggerlo per acquisire il contesto non implica avviare automaticamente l'implementazione: seguire la richiesta della chat corrente.

* Il contesto è stato letto e discusso: obiettivo, separazione degli step e distinzione fra routing e autenticazione sono chiari.
* È iniziata l'analisi del Core per lo **Step 1**: consultati `config/config.inc.php`, `classes/Cookie.php`, `classes/PhpEncryption.php` e `classes/PhpEncryptionEngine.php`, oltre alla documentazione generale e al contesto Cookie in `.ai`. Nessuna funzionalità Admin Bar è stata implementata.
* Il BO crea `Cookie('psAdmin', '', ...)`: il path deriva dalla `physical_uri` dello shop e il nome HTTP effettivo è `PrestaShop-<hash>`. Il contenuto è cifrato e il cookie è HttpOnly. Il cookie nel Context FO è invece quello cliente.
* L'utente ha completato la prova nel browser: ricezione, decifratura, formato, checksum e presenza dei campi employee/sessione hanno dato esito positivo; solo `admin_path` risulta assente, come previsto. La lettura del cookie BO dal FO è quindi verificata nella sua installazione; non equivale alla validazione della sessione employee.
* La prova conferma la fattibilità del canale di lettura nella configurazione provata, non il completamento dello Step 1 o dell'intera Admin Bar. Restano da implementare e verificare la scrittura di `admin_path` nel BO e la sua lettura applicativa nel FO. Non estendere automaticamente l'esito ad altri domini, percorsi o configurazioni multistore.
* Il nome `admin_path` è una proposta. Il suo valore deve rappresentare esclusivamente il percorso URL del BO, non il percorso filesystem di `_PS_ADMIN_DIR_` e non una prova di autenticazione.

### Valutazione di sicurezza discussa

Il riuso di `psAdmin` per il solo percorso BO è una soluzione ragionevole da approfondire, mantenendo invariata l'esposizione attuale del cookie. Nella configurazione provata il cookie è già inviato al FO: aggiungere `admin_path` non richiede di ampliare domain/path. La prova diagnostica conferma la fattibilità tecnica, non certifica la sicurezza dell'implementazione futura.

* Conoscere il percorso BO non deve concedere privilegi: autenticazione, autorizzazione e protezioni CSRF del BO restano necessarie. Non basare la sicurezza sulla segretezza del nome della directory Admin.
* Il BO deve derivare `admin_path` dal proprio percorso attendibile. Validarlo come percorso URL locale dell'installazione, non come URL arbitrario o destinazione esterna (inclusi valori che iniziano con `//`). Applicare l'escaping appropriato quando verrà inserito nei link.
* Non esporre il contenuto decifrato del cookie o i token di sessione in HTML, JavaScript o log. Il lettore applicativo deve restituire soltanto i dati necessari allo step corrente.
* La lettura deve essere priva di effetti collaterali anche con cookie invalido: nessuna riscrittura, rinnovo o logout Admin. Lo script diagnostico non è il lettore definitivo; prima dell'integrazione valutare il riuso della logica Core ed evitare duplicazioni inutili della crittografia e del formato del cookie.
* Conservare le protezioni del cookie e verificare HTTPS e `Secure` nell'ambiente di destinazione, insieme a `HttpOnly` e alla politica `SameSite` applicabile. `HttpOnly` impedisce la lettura da JavaScript ma non elimina XSS o CSRF. Non modificare questi attributi per facilitare la funzionalità.

Riferimenti consultati: [OWASP — Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) per protezioni, ambito e ciclo di vita dei cookie; [MDN — Set-Cookie](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Set-Cookie) per il comportamento degli attributi. I controlli specifici di sessione, cache e permessi restano negli step successivi, senza anticiparne l'implementazione nello Step 1.

### Verifica temporanea della lettura FO

* Script conservato su richiesta dell'utente in `._note/admin-cookie-check.php`, con bootstrap aggiornato alla nuova posizione. Per ripetere la prova, aprire `._note/admin-cookie-check.php` dall'URL base dell'installazione FO, se il server consente l'accesso alla cartella, nello stesso browser del login BO e inserire solo il nome HTTP completo del cookie BO già identificato.
* Lo script usa il bootstrap FO e legge direttamente il cookie selezionato: mostra soltanto esiti booleani di ricezione, decifratura, formato, checksum e presenza di alcuni campi. Non istanzia il cookie Admin e non ne riscrive o rinnova il valore; il bootstrap mantiene il normale comportamento FO.
* La lettura diagnostica riprende il formato di `Cookie::update()` senza richiamare i suoi effetti collaterali: su checksum non valido quel metodo può chiamare `logout()`.
* Risultato riportato dall'utente: tutti i controlli positivi, tranne la presenza di `admin_path`, non ancora implementato. I campi employee/sessione sono leggibili, ma la validità della sessione non è stata verificata.
* Verifiche locali superate: `php -l admin-cookie-check.php` e cinque casi sintetici con la crittografia del progetto (cookie valido, assente, cifrato invalido, checksum invalido, formato invalido). Nessun cookie o segreto reale usato nei test.
* Prova reale nel browser completata con esito positivo prima dello spostamento dello script. Prossimo passo: scegliere il punto BO in cui scrivere `admin_path` e il modo minimo di leggerlo nel FO senza modificare il cookie Admin.
* Chiarimento discusso: `Context::getContext()->cookie` nel FO espone il cookie cliente (`ps-s<ID>` oppure `ps-sg<ID>`), non `psAdmin`. La lettura Admin deve essere separata, senza sostituire il cookie cliente nel Context.

### Documentazione PrestaShop da usare

I percorsi seguenti sono relativi alla radice del repository `D:\cms\Prestashop\_Github\PrestaShop-dev`.

**A ogni nuova chat, dopo aver letto questo file, passare brevemente in rassegna la cartella `.ai`:** consultarne la struttura e le indicazioni generali in `.ai/CONTEXT.md` e `.ai/STRUCTURE.md`, individuare i documenti pertinenti allo step corrente e approfondire solo quelli necessari. Non occorre leggere integralmente tutta la cartella. Le letture indicate sotto descrivono quanto fatto nella discussione precedente e non sostituiscono questa ricognizione nella nuova chat.

Documenti generali già consultati:

* `.ai/CONTEXT.md`: architettura, convenzioni di codice, test e indice dei contesti specifici.
* `.ai/STRUCTURE.md`: organizzazione della documentazione e lettura mirata dei contesti pertinenti.
* `.ai/CONTAINERS.md`: differenze fra kernel Symfony e container FO legacy, disponibilità dei servizi e posizione delle definizioni YAML.
* `.ai/GOTCHAS.md`: particolarità e insidie trasversali del progetto.

Indicazione architetturale utile emersa da questa lettura: il BO, anche legacy, usa il container completo di AdminKernel; il FO legacy usa un container costruito separatamente. Esiste inoltre FrontKernel per le route FO Symfony. Non presumere quindi che servizi o contesto di sicurezza disponibili nel BO siano disponibili anche nel FO; verificare il punto di esecuzione e gli import dei servizi.

Contesti individuati, da leggere quando pertinenti allo step in lavorazione:

* `.ai/Component/Cookie/CONTEXT.md` e `.ai/Component/Context/CONTEXT.md` per cookie e contesto.
* `.ai/Domain/Employee/CONTEXT.md` e `.ai/Domain/Security/CONTEXT.md` per la successiva validazione employee; già consultati durante la valutazione di sicurezza.
* `.ai/Component/Link/CONTEXT.md` per la successiva costruzione dei link BO.
* `.ai/MULTISTORE.md` quando occorre valutare lo scoping multistore.

Usare `.ai` come mappa del progetto, riscontrando le indicazioni nel codice effettivo prima di scegliere l'implementazione. Aggiornare questo file dopo ogni step con modifiche, verifiche effettuate, dubbi aperti e prossimo passo.

### Obiettivo

Implementare in PrestaShop una **Admin Bar nel Front Office** con sviluppo incrementale, suddiviso in step piccoli e verificabili.

L'obiettivo finale è mostrare nel Front Office, solo agli employee autorizzati, azioni contestuali verso il Back Office come modifica prodotto, categoria o pagina CMS.

L'implementazione non deve essere monolitica: ogni step deve introdurre una sola responsabilità principale.

### Step 1 — Rendere disponibile al Front Office il path del Back Office

Il primo problema da risolvere è che il Front Office non conosce `_PS_ADMIN_DIR_` e quindi non può sapere quale sia il percorso reale del Back Office.

Il Front Office non deve:

* hardcodare `/admin-dev/` o percorsi equivalenti;
* tentare di ricostruire autonomamente `_PS_ADMIN_DIR_`;
* introdurre una configurazione duplicata del percorso Admin.

Il Back Office, che conosce già `_PS_ADMIN_DIR_`, deve memorizzare nel browser un'informazione di routing utilizzabile successivamente dal Front Office.

Preferibilmente riutilizzare il cookie Admin esistente `psAdmin`, aggiungendo un valore come:

`admin_path`

Esempio:

`/adminXYZ/`

Valutare il punto più corretto del bootstrap/login Back Office in cui valorizzare questo dato, evitando modifiche non necessarie.

Il percorso deve includere l'eventuale sottocartella dell'installazione, per esempio `/shop/adminXYZ/`. La lettura FO deve avvenire lato server, senza rendere il cookie accessibile a JavaScript, sostituire il cookie cliente nel Context o riscrivere/rinnovare il cookie Admin.

Verificare installazioni in root e in sottocartella, aggiornamento del dato dopo la rinomina della directory Admin e comportamento con cookie o campo assente/invalido: il FO deve continuare normalmente senza percorso BO disponibile. Definire gli scenari supportati per domini e multistore; non ampliare automaticamente domain/path del cookie di autenticazione per renderlo condivisibile.

In questo step:

1. il Back Office deve salvare il proprio admin path;
2. il valore deve essere disponibile nello stesso browser anche durante la navigazione Front Office, quando cookie domain/path lo consentono;
3. verificare che il Front Office sia tecnicamente in grado di leggere tale valore;
4. il valore deve essere considerato esclusivamente **routing metadata**.

Non deve essere usato come prova che un employee sia autenticato.

**Non implementare ancora:**

* validazione della sessione employee;
* controllo dei permessi;
* Admin Bar;
* azioni prodotto/categoria/CMS;
* generazione completa degli URL BO.

Lo scopo dello Step 1 è esclusivamente dimostrare che il Front Office può conoscere in modo affidabile il base path del Back Office senza conoscere `_PS_ADMIN_DIR_`.

### Step 2 — Validare la sessione employee nel Front Office

Dopo aver consolidato lo Step 1, introdurre un componente dedicato, ad esempio:

`AdminEmployeeContextProvider`

che legga il contesto Admin disponibile nel browser e verifichi lato server:

* `id_employee`;
* `session_id`;
* `session_token`;
* validità della sessione;
* employee esistente e attivo.

La semplice presenza del cookie Admin o di `admin_path` non deve essere sufficiente per considerare autenticato l'employee.

Ricavare le condizioni di validità dal Core effettivo, verificando scadenza, logout/revoca e impostazioni di sicurezza applicabili. I campi elencati non sono, da soli, una specifica sufficiente di autenticazione.

Riscontro già effettuato in `src/PrestaShopBundle/EventListener/Admin/EmployeeSessionSubscriber.php`: `updateLegacyCookie()` copia nel cookie i dati employee e sessione dal contesto di sicurezza Symfony per compatibilità legacy; `onKernelRequest()` verifica la presenza della sessione nel token, la corrispondenza con la sessione persistita tramite `hasSession()` e, se `PS_COOKIE_CHECKIP` è attivo, l'IP. Questo conferma il ruolo di compatibilità del cookie descritto in `.ai/Component/Cookie/CONTEXT.md`, ma non esaurisce l'analisi dell'autenticazione BO: prima di scegliere il validatore FO completare la verifica di scadenza, revoca e controlli applicati dagli altri componenti.

La validazione non deve prolungare automaticamente la durata della sessione BO durante la navigazione Front Office.

In `classes/Employee.php` è stato verificato che `Employee::isLoggedBack()` usa `SymfonyContainer` e `prestashop.user_provider`: non adottarlo direttamente come validatore FO presumendo che il contesto BO sia disponibile.

### Step 3 — Admin Bar minimale

Mostrare una barra minimale nel Front Office solamente quando lo Step 2 restituisce un employee BO valido.

In questa fase verificare solamente:

* employee BO valido → barra visibile;
* visitatore/customer normale → barra assente;
* sessione BO scaduta → barra assente;
* base path BO correttamente disponibile.

Verificare inoltre che la cache delle pagine non renda visibili barra o link amministrativi ad altri visitatori.

Non aggiungere ancora azioni contestuali.

### Step 4 — Prime azioni contestuali

Aggiungere progressivamente:

1. modifica prodotto;
2. modifica categoria;
3. modifica pagina CMS.

Per ogni azione:

* identificare la risorsa FO corrente;
* verificare il permesso dell'employee;
* mostrare l'azione solo se autorizzato;
* generare il link corretto verso il BO.

Centralizzare la costruzione dei link BO ed evitare path hardcodati sparsi nel Front Office.

Verificare anche l'accesso dell'employee al negozio della risorsa nel contesto multistore. Il BO deve comunque applicare i propri controlli di autenticazione e autorizzazione all'apertura del link.

### Step 5 — Estendibilità

Solo dopo che il comportamento Core è funzionante, valutare un'architettura basata su provider, ad esempio:

`AdminBarActionProviderInterface`

con implementazioni dedicate a prodotto, categoria e CMS.

L'architettura dovrà essere predisposta per consentire in futuro ai moduli di registrare proprie azioni, senza necessariamente implementare questa possibilità nella prima versione.

### Metodo di lavoro

Per ogni step:

1. analizzare prima il codice Core esistente;
2. individuare il punto di integrazione meno invasivo;
3. proporre la modifica minima;
4. implementarla;
5. aggiungere un modo semplice per verificarne il comportamento;
6. non anticipare funzionalità appartenenti agli step successivi.

Preferire modifiche incrementali e facilmente separabili in commit distinti.
