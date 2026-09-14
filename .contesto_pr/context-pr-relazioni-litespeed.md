// TODO <cnc> ########## PR Extract reusable product page presentation service ########## context-pr-relazioni-litespeed.md - DA ELIMINARE PRIMA DI PUBBLICARE LA PR

# Collegamenti con PR correlate e adattamento LiteSpeed

## PR core principale

La nuova PR PrestaShop ha come obiettivo:

**Extract reusable product page presentation service**

L'obiettivo è estrarre da `ProductController` la pipeline necessaria a preparare il prodotto nel contesto della product page, rendendola disponibile anche a moduli e altri controller.

`ProductController` deve usare il nuovo servizio/componente, così da mantenere una sola source of truth.

## PR e discussioni correlate

### PrestaShop #39953

PR recente relativa all'estensibilità dei Presenter/LazyArray.

È collegata perché migliora l'accesso ai dati interni di `ProductLazyArray`, ma risolve un problema diverso:

- `#39953`: estendere/interagire con un `ProductLazyArray` già creato;
- nuova PR: ottenere correttamente il prodotto presentato fuori da `ProductController`.

La nuova PR deve quindi rimanere indipendente da `#39953`, salvo emerga una reale dipendenza tecnica durante l'implementazione.

### PrestaShop #39865

PR precedente che ha contribuito alla nascita di `#39953`.

Può essere citata come contesto storico sull'estensibilità dei presented objects, ma non è una dipendenza.

### PrestaShop #29193 / #29194 / Discussion #32268

Riferimenti più vecchi relativi alla difficoltà per i moduli di utilizzare correttamente il product presenter senza duplicare boilerplate.

Sono utili per dimostrare che il problema della riusabilità del presentation layer era già stato sollevato, ma la nuova PR ha uno scopo più ampio: estrarre la reale pipeline della product page, non soltanto semplificare l'istanziazione del `ProductPresenter`.

## Caso reale: LiteSpeed Cache #107

La PR:

**litespeedtech/lscache_prestashop#107**

è il caso reale che ha evidenziato il problema.

Il controller ESI deve renderizzare il fragment `product-add-to-cart` fuori dal normale `ProductController`.

Poiché PrestaShop non espone attualmente un servizio pubblico che restituisca il prodotto preparato come nella product page, `controllers/front/esi.php` deve ricostruire manualmente parte della pipeline, inclusi:

- validazione di `id_product_attribute`;
- customization corrente;
- minimal quantity;
- cart quantity;
- `quantity_required`;
- `quantity_wanted`;
- `Product::getProductProperties()`;
- `ProductPresenterFactory` / presenter;
- `filterProductContent`.

Questa logica è un workaround e deve rimanere allineata manualmente a `ProductController`.

## Come modificare LiteSpeed dopo la PR core

Dopo l'introduzione del nuovo servizio nel core, `esi.php` dovrebbe essere semplificato.

La logica di ricostruzione del prodotto attualmente presente nel controller deve essere estratta in una classe dedicata del modulo, ad esempio:

```text
classes/DynamicFragmentProductPresenter.php
```

oppure in una classe con nome equivalente.

`esi.php` deve limitarsi a chiedere il prodotto a questa classe:

```php
$product = $this->productPresentation->present($item);
```

La classe LiteSpeed deve scegliere automaticamente tra il nuovo servizio core e il fallback manuale.

Architettura attesa:

```text
esi.php
   |
   v
DynamicFragmentProductPresenter
   |
   +-- servizio core disponibile
   |      |
   |      v
   |   nuovo Product Page Presentation service
   |
   +-- servizio core non disponibile
          |
          v
      fallback manuale
```

## Fallback per vecchie versioni PrestaShop

Il codice che oggi si trova in `esi.php` non deve essere eliminato immediatamente.

Deve essere spostato nella classe dedicata e utilizzato solo quando il nuovo servizio core non è disponibile.

In pratica:

```php
public function present($item)
{
    if ($this->isCoreProductPagePresentationAvailable()) {
        return $this->presentUsingCoreService($item);
    }

    return $this->presentUsingFallback($item);
}
```

Il fallback deve contenere la logica attuale relativa a:

```text
getPresentedProductFromItem()
getCurrentCustomizationId()
getProductMinimalQuantity()
```

e alle relative operazioni di validazione/presentazione.

## Feature detection

Non basare la scelta esclusivamente su `_PS_VERSION_`.

Preferire feature detection, per esempio verificando:

- esistenza della classe/interfaccia introdotta dal core;
- disponibilità del servizio nel container;
- eventuale metodo pubblico previsto dall'API finale.

Esempio concettuale:

```php
if (class_exists(ProductPagePresenterInterface::class)
    && $container->has(ProductPagePresenterInterface::class)) {
    // usa il nuovo servizio core
}
```

Il codice esatto dipenderà dall'API definita nella PR PrestaShop.

## Risultato finale desiderato

Con PrestaShop nuovo:

```text
LiteSpeed ESI
    -> servizio core
    -> stesso prodotto preparato da ProductController
```

Con PrestaShop precedente:

```text
LiteSpeed ESI
    -> fallback del modulo
    -> ricostruzione manuale attuale
```

Quando LiteSpeed in futuro smetterà di supportare versioni PrestaShop prive del nuovo servizio, il fallback potrà essere rimosso completamente.

## Relazione con Hummingbird #1101

La PR:

**PrestaShop/hummingbird#1101**

rimane indipendente.

Hummingbird espone soltanto il marker semantico `data-ps-fragment` sui fragment dinamici.

Non deve conoscere LiteSpeed, ESI o il nuovo product presentation service.

La separazione attesa resta:

```text
Hummingbird
    -> identifica semanticamente il fragment

PrestaShop core
    -> prepara correttamente il prodotto

LiteSpeed
    -> usa il fragment e il servizio core quando disponibili
       mantenendo il fallback per le versioni precedenti
```
