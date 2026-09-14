// TODO <cnc> ########## PR Extract reusable product page presentation service ########## contest.md - DA ELIMINARE PRIMA DI PUBBLICARE LA PR

## Contesto

Vogliamo creare una PR nel core PrestaShop chiamata:

**Extract reusable product page presentation service**

La root locale del repository PrestaShop `develop` è:

```text
D:\cms\Prestashop\_Github\PrestaShop-dev
```

Attualmente `ProductController` contiene parte della logica necessaria a costruire il prodotto così come viene realmente usato nella product page. Un modulo o un altro controller che deve ottenere lo stesso risultato è costretto a replicare manualmente logica relativa a combinazione, quantità minima, quantità nel carrello, `quantity_required`, `quantity_wanted`, customization, `Product::getProductProperties()`, presenter e `filterProductContent`.

Il caso reale che ha evidenziato il problema è LiteSpeed Cache: un controller ESI deve ricostruire il prodotto necessario a `product-add-to-cart.tpl` duplicando parte della logica di `ProductController`.

### Obiettivo

Estrarre questa responsabilità in un servizio/componente core riutilizzabile.

Requisiti principali:

* `ProductController` deve usare il nuovo servizio, così da mantenere una sola source of truth;
* il servizio deve poter essere usato anche da moduli e altri controller;
* gli input dipendenti dalla request (`id_product_attribute`, `quantity_wanted`, customization, ecc.) devono preferibilmente essere passati esplicitamente e non letti internamente con `Tools::getValue()`;
* deve essere preservato il comportamento attuale della product page, incluso `filterProductContent`;
* evitare refactoring non necessari del presenter/LazyArray layer;
* la PR `#39953` è correlata ma non deve diventare una dipendenza salvo reale necessità.

### Modulo demo

Esiste un modulo demo minimale che riproduce il problema senza LiteSpeed.

Attualmente usa `ProductPagePresentationFallback`, che ricostruisce manualmente il prodotto.

Nel file principale del modulo è presente questo TODO:

```php
/**
 * // TODO <cnc> ########## PR Extract reusable product page presentation service ########## Modulo test
 * DA FARE dopo aver creato la PR:
 * aggiungere al modulo l'utilizzo del service/classe creato e usare "ProductPagePresentationFallback" appunto come fallback
 */
```

Dopo aver implementato la PR core, il modulo demo dovrà:

* usare il nuovo servizio core quando disponibile;
* usare `ProductPagePresentationFallback` solo come fallback;
* preferire feature detection (`class_exists`, disponibilità del service, ecc.) invece di controllare direttamente la versione di PrestaShop.

Architettura attesa:

```text
                    Core product page presentation service
                         ▲                       ▲
                         │                       │
                ProductController        Moduli / altri controller
```

L'obiettivo non è creare un semplice wrapper di `ProductPresenter`, ma rendere riutilizzabile la reale pipeline di preparazione del prodotto oggi legata a `ProductController`.

### Technical decisions (regression analysis)

* `filterProductContent` is separate from product building. The provider will expose `getProduct()` and `filterProductContent()`; only `initContent` applies the filter.
* The hook keeps chained `Hook::exec()` semantics.
* The public provider receives only the product, context, resolved combination and requested quantity.
* Quantity discounts use a shared dependency that preserves the new pricing engine and caches request results.
* New services must be imported through `config/services/common.yml` for FO legacy availability.
