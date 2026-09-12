# Contesto — Admin Bar nel Front Office

### Stato della discussione — 5 settembre 2026

Questo file serve a riprendere il lavoro in altre chat senza dipendere dalla cronologia della conversazione. Leggerlo per acquisire il contesto non implica avviare automaticamente l'implementazione: seguire la richiesta della chat corrente.

* Il contesto è stato letto e discusso: obiettivo, separazione degli step e distinzione fra routing e autenticazione sono chiari.
* Primo incremento dello **Step 1** implementato: scrittura di `admin_path` nel BO e confronto del suo valore nella diagnostica FO. Consultati `config/config.inc.php`, `classes/Cookie.php`, `classes/PhpEncryption.php`, `classes/PhpEncryptionEngine.php` ed `EmployeeSessionSubscriber`, oltre alla documentazione pertinente in `.ai`. La scrittura è ora condizionata dalla feature flag dell'Admin Bar.
* Il BO crea `Cookie('psAdmin', '', ...)`: il path deriva dalla `physical_uri` dello shop e il nome HTTP effettivo è `PrestaShop-<hash>`. Il contenuto è cifrato e il cookie è HttpOnly. Il cookie nel Context FO è invece quello cliente.
* Prima della modifica Core, l'utente ha completato la prova nel browser: ricezione, decifratura, formato, checksum e presenza dei campi employee/sessione hanno dato esito positivo; solo `admin_path` risultava assente, come previsto. Questo verifica la lettura del cookie BO nella sua installazione, non la validità della sessione employee.
* Resta da effettuare la prova nel browser dopo la modifica Core, verificando il valore effettivo di `admin_path`. Non considerare ancora completato lo Step 1 e non estendere automaticamente l'esito ad altri domini, percorsi o configurazioni multistore. Il lettore applicativo definitivo FO non è ancora stato introdotto: questo incremento usa la diagnostica per dimostrare il trasferimento del dato.
* Il campo scelto è `admin_path`. Rappresenta esclusivamente il percorso URL del BO, non il percorso filesystem di `_PS_ADMIN_DIR_` e non una prova di autenticazione.

### Implementazione corrente dello Step 1

* Modifica minima in `src/PrestaShopBundle/EventListener/Admin/EmployeeSessionSubscriber.php`, metodo `updateLegacyCookie()`: quando `front_office_admin_bar` è attiva, `$legacyCookie->admin_path = $request->getBasePath() . '/';`.
* Il subscriber è già registrato in `app/config/admin/services.yml`. Il metodo viene chiamato dopo il login riuscito e durante le richieste BO autenticate, quindi aggiorna anche i cookie di sessioni già aperte e un eventuale percorso precedente. La scrittura HTTP resta affidata al ciclo BO esistente.
* `Request::getBasePath()` ricava il percorso URL base del BO, includendo la sottocartella dell'installazione ed escludendo `index.php` e la route corrente. Il FO non deve conoscere `_PS_ADMIN_DIR_`. Nessuna modifica a domain/path/flag del cookie, al bootstrap FO o alle definizioni dei servizi.
* Test aggiunto: `tests/Unit/PrestaShopBundle/EventListener/Admin/EmployeeSessionSubscriberTest.php`. Superati 11 test / 41 asserzioni con PHP 8.1: login e richieste autenticate per root, sottocartella, URL legacy/Symfony con e senza `index.php`, aggiornamento del vecchio percorso dopo rinomina e assenza del nuovo campo per richieste anonime. PHPUnit segnala soltanto lo schema XML preesistente deprecato.
* PHP CS Fixer sui due file Core/test non ha richiesto correzioni; PHPStan mirato agli stessi file con la configurazione del progetto è passato senza errori. Sintassi della diagnostica verificata e sei casi sintetici superati, incluso il confronto con un percorso atteso errato.

### Stato dello Step 2 — validazione employee FO

È in corso un componente separato `AdminEmployeeContextProvider`, senza barra o azioni FO. La validazione non considera mai sufficiente `psAdmin`, `admin_path` o i campi del cookie Admin: legge il token Symfony già conservato nella sessione PHP lato server e lo confronta con database e configurazione correnti.

* Errore corretto durante lo sviluppo: il primo tentativo di rendere disponibile `PrestaShopBundle\Entity\Repository\EmployeeRepository` nel container FO legacy falliva perché dipende da `InternationalizedDomainNameConverter`, definito nel servizio Core non importato in tale container. Anche se fosse possibile importarlo, questo accoppierebbe il FO al repository Doctrine e al provider BO senza necessità.
* Decisione: non usare `EmployeeRepository` né `EmployeeProvider` nel FO. Le loro definizioni sono rimaste nei file BO originali `bundle/repository.yml` e `bundle/security.yml`; non sono registrate in `bundle/common.yml`.
* Il provider FO usa invece `doctrine.dbal.default_connection` e QueryBuilder con query parametrizzata su `{prefix}employee` e `{prefix}employee_session`. Verifica employee esistente e attivo, id, email, password hash, profilo, id/token della sessione persistita; una modifica password/profilo, disattivazione o revoca della sessione invalida il contesto FO.
* `NativeAdminSessionReader` legge la sessione PHP esistente in sola lettura, senza cookie, cache headers, garbage collection, `write()` o `updateTimestamp()`. Il token è deserializzato solo dal server e accettato esclusivamente per i token BO conosciuti del firewall `main`. Valori non conformi falliscono chiusi.
* Interfaccia FO legacy: `AdminEmployeeContextProvider::getContext()` non richiede una `Request` Symfony. Il provider e il reader usano internamente `$_COOKIE` e `$_SERVER['REMOTE_ADDR']`, già disponibili nel bootstrap legacy.
* Uso temporaneo in un controller FO legacy, dopo il bootstrap:

  ```php
  /** @var PrestaShop\PrestaShop\Adapter\Security\AdminEmployeeContextProvider $provider */
  $provider = $this->get(PrestaShop\PrestaShop\Adapter\Security\AdminEmployeeContextProvider::class);
  $adminEmployeeContext = $provider->getContext();

  if ($adminEmployeeContext !== null) {
      $employeeId = $adminEmployeeContext->getEmployeeId();
      $profileId = $adminEmployeeContext->getProfileId();
  }
  ```

  `null` significa che non esiste una sessione BO valida. Non passare una `Request`, non leggere il cookie Admin dal `Context` FO e non esporre id o profilo al browser. Questo esempio non definisce ancora il punto condiviso dell'Admin Bar nello Step 3.
* Quando `front_office_admin_bar` è attiva, `EmployeeSessionSubscriber` salva `LAST_ADMIN_ACTIVITY` esclusivamente sulle richieste BO autenticate. Il provider FO la confronta con il lifetime BO senza aggiornarla; questo evita che la navigazione FO prolunghi la validità usata dall'Admin Bar. Resta da valutare separatamente l'effetto preesistente del bootstrap FO sulla durata della sessione PHP condivisa.
* Se `PS_COOKIE_CHECKIP` è attivo, il provider richiede la corrispondenza con l'IP memorizzato dal BO. Non usa `Employee::isLoggedBack()`.
* Verifica reale eseguita dall'utente: in `controllers/front/IndexController.php` ha aggiunto temporaneamente il recupero del servizio con `$this->get(AdminEmployeeContextProvider::class)` e la chiamata `getContext()`, senza `Request`; il risultato è stato verificato nel FO. Il codice diagnostico è stato poi rimosso dalla home.
* Test presenti in `tests/Unit/Adapter/Security/`: 24 scenari di validazione e 3 del reader di sessione, più gli 11 test dello Step 1. Ultima esecuzione: 42 test, 150 asserzioni superate; PHP CS Fixer applicato. Il container Admin espone il servizio correttamente. L'ultima analisi PHPStan è stata rifiutata automaticamente dall'ambiente dopo la correzione finale; ripeterla quando disponibile.

La verifica base reale del provider nel FO è completata. Il codice diagnostico da `IndexController` è stato rimosso e lo Step 3 usa ora il punto condiviso del Front Office. Cache e autorizzazioni delle azioni restano Step 3 e 4.

### Valutazione di sicurezza discussa

Il riuso di `psAdmin` per il solo percorso BO è una soluzione ragionevole da approfondire, mantenendo invariata l'esposizione attuale del cookie. Nella configurazione provata il cookie è già inviato al FO: aggiungere `admin_path` non richiede di ampliare domain/path. La prova diagnostica conferma la fattibilità tecnica, non certifica la sicurezza dell'implementazione futura.

* Conoscere il percorso BO non deve concedere privilegi: autenticazione, autorizzazione e protezioni CSRF del BO restano necessarie. Non basare la sicurezza sulla segretezza del nome della directory Admin.
* Il BO deve derivare `admin_path` dal proprio percorso attendibile. Validarlo come percorso URL locale dell'installazione, non come URL arbitrario o destinazione esterna (inclusi valori che iniziano con `//`). Applicare l'escaping appropriato quando verrà inserito nei link.
* Non esporre il contenuto decifrato del cookie o i token di sessione in HTML, JavaScript o log. Il lettore applicativo deve restituire soltanto i dati necessari allo step corrente.
* La lettura deve essere priva di effetti collaterali anche con cookie invalido: nessuna riscrittura, rinnovo o logout Admin. Lo script diagnostico non è il lettore definitivo; prima dell'integrazione valutare il riuso della logica Core ed evitare duplicazioni inutili della crittografia e del formato del cookie.
* Conservare le protezioni del cookie e verificare HTTPS e `Secure` nell'ambiente di destinazione, insieme a `HttpOnly` e alla politica `SameSite` applicabile. `HttpOnly` impedisce la lettura da JavaScript ma non elimina XSS o CSRF. Non modificare questi attributi per facilitare la funzionalità.

Riferimenti consultati: [OWASP — Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) per protezioni, ambito e ciclo di vita dei cookie; [MDN — Set-Cookie](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Set-Cookie) per il comportamento degli attributi. I controlli specifici di sessione, cache e permessi restano negli step successivi, senza anticiparne l'implementazione nello Step 1.

### Verifica temporanea della lettura FO

* Script conservato su richiesta dell'utente in `._note/admin-cookie-check.php`, con bootstrap aggiornato alla nuova posizione. Per la nuova prova, visitare prima una pagina BO autenticata e poi aprire `._note/admin-cookie-check.php` dall'URL base dell'installazione FO, se il server consente l'accesso alla cartella, nello stesso browser. Inserire il nome HTTP completo del cookie BO e il percorso BO atteso, ad esempio `/shop/adminXYZ/`, senza dominio o `index.php` e con slash finale.
* Lo script usa il bootstrap FO e legge direttamente il cookie selezionato: mostra soltanto esiti booleani di ricezione, decifratura, formato, checksum e presenza di alcuni campi. Non istanzia il cookie Admin e non ne riscrive o rinnova il valore; il bootstrap mantiene il normale comportamento FO.
* La lettura diagnostica riprende il formato di `Cookie::update()` senza richiamare i suoi effetti collaterali: su checksum non valido quel metodo può chiamare `logout()`.
* Risultato iniziale riportato dall'utente, prima della modifica: tutti i controlli positivi tranne la presenza di `admin_path`. Ora devono risultare positivi anche la presenza di `admin_path` e il confronto con il percorso atteso. La validità della sessione resta fuori da questa prova.
* Verifiche locali superate: `php -l admin-cookie-check.php` e cinque casi sintetici con la crittografia del progetto (cookie valido, assente, cifrato invalido, checksum invalido, formato invalido). Nessun cookie o segreto reale usato nei test.
* La prima prova reale nel browser è stata completata con esito positivo prima dello spostamento dello script. La nuova prova sul percorso salvato dal BO è ancora da eseguire. Controllare anche che la risposta diagnostica non emetta un `Set-Cookie` per il nome HTTP di `psAdmin`; eventuali cookie FO appartengono al normale bootstrap del negozio.
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

L'Admin Bar è una nuova funzionalità sperimentale, protetta dalla feature flag beta `front_office_admin_bar`, disattivata per impostazione predefinita (`state="0"`). È registrata in `install-dev/data/xml/feature_flag.xml` come `env,dotenv,db` ed esposta dalla costante `FeatureFlagSettings::FEATURE_FLAG_FRONT_OFFICE_ADMIN_BAR`. Ogni codice introdotto esclusivamente per l'Admin Bar, sia nel FO sia nel BO, deve essere eseguito soltanto quando questa flag è attiva; servizi e classi possono restare registrati se non producono effetti né vengono chiamati. Il controllo avviene prima di chiamare `AdminEmployeeContextProvider`, così quando la funzione è disattivata non vengono letti la sessione BO né eseguita la query di validazione. Il medesimo controllo nel subscriber BO impedisce la scrittura di `admin_path` e `LAST_ADMIN_ACTIVITY`. Se la flag non è disponibile o non è leggibile, il FO non mostra la barra e il BO non salva questi dati. La stessa flag deve governare anche le future azioni contestuali dello Step 4.

In questa fase verificare solamente:

* employee BO valido → barra visibile;
* visitatore/customer normale → barra assente;
* sessione BO scaduta → barra assente;
* base path BO correttamente disponibile.

Verificare inoltre che la cache delle pagine non renda visibili barra o link amministrativi ad altri visitatori.

Non aggiungere ancora azioni contestuali.

Implementazione temporanea presente: `FrontController::smartyOutputContent()` verifica prima la feature flag e poi invoca il provider dopo il rendering completo della pagina; se restituisce un contesto valido, inserisce una barra con formattazione inline subito prima di `</body>`. Il metodo conserva il TODO `FrontController::smartyOutputContent() - codice temporaneo`, che indica la futura integrazione nel layout Smarty. Non usare `echo` separati nei controller specifici.

### Step 4 — Prime azioni contestuali

A causa del contesto FO, non generare direttamente URL di route BO: `Link::getAdminLink()` restituisce esplicitamente una stringa vuota quando `_PS_ADMIN_DIR_` non è definito. Anche se il router Symfony FO conoscesse una route BO, non possiede il contesto del prefisso Admin né può generare in modo affidabile il token URL BO. Per ogni azione dell'Admin Bar il FO deve quindi costruire soltanto il link verso un endpoint BO stabile, usando `admin_path`; un controller BO dedicato deve validare l'azione richiesta, generare la route finale nel proprio contesto e reindirizzare.

Il controller BO non deve mai accettare una route Symfony arbitraria dal parametro della richiesta. Deve usare una whitelist/mappa server-side, per esempio `product_edit` → `admin_product_form`, e validare i parametri strettamente necessari (come l'id prodotto). L'autenticazione e le autorizzazioni BO devono restare applicate sia al redirector sia alla destinazione finale.

#### Stato reale dell'implementazione

**Gia usato:** `FrontController::smartyOutputContent()` mostra la barra solo con feature flag attiva e `AdminEmployeeContextProvider` valido. Nelle pagine prodotto, categoria, pagina CMS e categoria CMS chiama anche `AdminBarPageContextFactory`, `AdminBarActionResolver` e i rispettivi action provider.

Il provider prodotto legge la risorsa con `ProductControllerCore::getProduct()` e restituisce `product_edit` solo al profilo che possiede il ruolo BO di aggiornamento prodotti. Il provider categoria restituisce `category_edit` solo con il ruolo BO di aggiornamento categorie. Il provider CMS restituisce `cms_edit` e `cms_category_edit` con il ruolo BO `AdminCmsContent` Update; la categoria CMS radice non e modificabile e non mostra il pulsante. Questi controlli FO servono solo a decidere la visibilita dei pulsanti: le stesse autorizzazioni vengono controllate di nuovo nel BO.

Il renderer temporaneo usa `AdminBarActionUrlProvider`, che contiene la whitelist tra nome azione, parametro e endpoint BO. Il `FrontController` non conosce piu le azioni concrete.

I pulsanti puntano esclusivamente agli endpoint `admin_path/_admin-bar/{risorsa}/{id}` per prodotto, categoria, pagina CMS e categoria CMS. Le route BO iniziano con `_`, quindi non richiedono il token URL al primo accesso; non sono pubbliche: `AdminSecurity` richiede il rispettivo permesso Update. Se la feature flag e disattiva, il controller restituisce 404. Dopo il controllo il controller reindirizza alle route ufficiali `admin_product_form`, `admin_categories_edit`, `admin_cms_pages_edit` e `admin_cms_pages_category_edit`; il router BO aggiunge il token URL della destinazione.

Il nome della rotta finale Symfony non arriva mai dal FO. Oggi esistono soltanto le azioni esplicite `product_edit`, `category_edit`, `cms_edit` e `cms_category_edit`; ogni nuova azione dovra avere una mappatura server-side, parametri strettamente validati e il proprio permesso BO. Non estendere ancora ai moduli.

In multistore verificare anche l'accesso dell'employee al negozio della risorsa. Il redirector non modifica dati e la pagina BO di destinazione resta responsabile dei controlli Core sulla risorsa.

**Predisposto per le prossime azioni:** `AdminPathProvider` e l'interfaccia `AdminBarActionProviderInterface`. La visualizzazione resta temporaneamente in `FrontController::smartyOutputContent()` con stile inline: dovra essere spostata in un template Smarty.

Verifica manuale degli incrementi Core:

* feature flag attiva, employee con permesso Update prodotti, pagina prodotto FO: appare `Modifica prodotto` e apre lo stesso prodotto nel BO;
* feature flag attiva, employee con permesso Update categorie, pagina categoria FO: appare `Modifica categoria` e apre la stessa categoria nel BO;
* feature flag attiva, employee con permesso Update pagine CMS, pagina CMS FO: appare Modifica pagina CMS e apre la stessa pagina nel BO;
* feature flag attiva, employee con permesso Update pagine CMS, categoria CMS FO diversa dalla radice: appare Modifica categoria CMS e apre la stessa categoria nel BO;
* employee senza il rispettivo permesso: il pulsante non appare; anche richiamando manualmente l'endpoint BO, `AdminSecurity` nega l'accesso;
* feature flag disattiva: barra e endpoint non sono disponibili.

### Step 5 - Estendibilita

L'architettura a provider resta riservata alle azioni Core. Per i moduli e stato introdotto l'hook legacy `actionAdminBarGetActions`, registrato anche in `install-dev/data/xml/hook.xml`. La scelta dell'hook evita di dipendere da servizi Symfony dei moduli nel container FO legacy, che non li carica.

`AdminBarActionResolver` esegue l'hook globalmente tramite `PrestaShop\PrestaShop\Adapter\HookManager`, non tramite `Hook::exec()` diretto. I moduli restituiscono una lista di `AdminBarAction` con endpoint BO locale esplicito; `AdminBarActionUrlProvider` accetta soltanto endpoint nel formato `/_admin-bar/...`, quindi non puo ricevere URL esterni, path traversal o query string arbitrarie.

Il contesto dell'hook contiene:

* `pageContext`: il contesto completo usato anche dai provider Core;
* `employeeContext`: employee BO validato;
* `ownerModule`: nome del modulo proprietario del `ModuleFrontController`, oppure `null` per le pagine Core;
* `controller`: nome normalizzato del controller corrente. Per un modulo e ottenuto dal page name `module-{modulo}-{controller}`.

L'hook resta globale: ogni modulo agganciato puo aggiungere una voce anche alle pagine di un altro modulo o alle pagine Core. Il modulo proprietario non deve usare `instanceof` sulle proprie classi PHP: confronta invece `ownerModule` e `controller`.

#### Modulo di prova `ps_test_adminbar`

Il modulo minimale in `modules/ps_test_adminbar` e usato per verificare la PR:

* ha due `ModuleFrontController`, `first` e `second`, che usano il layout FO PrestaShop e mostrano quindi la barra;
* ha un controller admin legacy e uno Symfony. Quello Symfony estende `PrestaShopAdminController`, e registrato come servizio con `autowire`, `autoconfigure` e tag `controller.service_arguments`, e renderizza il layout BO standard;
* crea due Tab nascoste durante l'installazione, necessarie alla risoluzione del controller legacy e ai permessi BO;
* nel proprio hook usa `ownerModule === 'ps_test_adminbar'` e `controller`: `first` restituisce l'azione verso il controller admin legacy, `second` quella verso il controller Symfony;
* le route dei due endpoint iniziano con `_` e impostano `_disable_module_prefix: true`: non ricevono il prefisso automatico `/modules` e seguono il modello delle route Core dell'Admin Bar, escluse dal controllo del token URL al primo accesso. Restano protette da autenticazione e `AdminSecurity`.

Per il controller legacy l'endpoint BO Symfony reindirizza all'URL legacy tokenizzato generato nel contesto BO. Il controller Symfony viene aperto direttamente. Dopo modifiche a route, servizi o installazione del modulo svuotare la cache BO/Symfony; per creare le Tab in un modulo gia installato, eseguire reset/reinstallazione.

Verifiche eseguite: lint PHP dei file interessati, validazione YAML e `git diff --check`; il test unitario `AdminBarActionResolverTest` passa (1 test, 3 asserzioni) e verifica che `ownerModule` e `controller` siano inoltrati all'hook.

Resta da aggiungere una verifica automatica specifica per `AdminBarPageContextFactory`, che controlli l'estrazione di `ownerModule` e `controller` da un `ModuleFrontController`, e una verifica manuale con un secondo modulo che aggiunga un'azione a una pagina di `ps_test_adminbar`.

### Metodo di lavoro

Per ogni step:

1. analizzare prima il codice Core esistente;
2. individuare il punto di integrazione meno invasivo;
3. proporre la modifica minima;
4. implementarla;
5. aggiungere un modo semplice per verificarne il comportamento;
6. non anticipare funzionalità appartenenti agli step successivi.

Preferire modifiche incrementali e facilmente separabili in commit distinti.
