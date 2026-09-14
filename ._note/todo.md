// TODO <cnc> ===== Front admin bar ===== todo.md - DA FARE
- creare PR sul modulo autoupgrade per aggiungere hook `actionAdminBarGetActions`
- creare PR sul repository hummingbird per aggiungere visualizzazione barra in front-office
- il file `ps_test_adminbar.zip` contiene il modulo di test


# // TODO <cnc> ===== Front admin bar ===== todo.md - NOTE PER PR

- Chiedere conferma sull'uso del cookie Admin esistente per salvare il percorso URL del Back Office (`admin_path`), oppure se esiste un meccanismo Core preferibile.
- Il container FO non può istanziare EmployeeRepository perché non carica le sue dipendenze BO, quindi ho usato query DBAL limitata ai dati necessari per validare employee e sessione BO persistita
- L'estensione per i moduli usa l'hook `actionAdminBarGetActions`, scelto per semplicità e compatibilità con moduli legacy. Il container Front Office carica però anche `config/front/services.yml` dei moduli: provider Symfony taggati sono quindi tecnicamente possibili. Chiedere ai reviewer quale API pubblica preferiscono: hook legacy oppure servizi Front Office taggati per i moduli moderni.
