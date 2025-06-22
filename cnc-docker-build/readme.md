# Descrizione
Questa cartella contiene i file per creare il container Docker

# Comandi
```bash
# creare il container
docker compose build --no-cache

# entrare nel container
docker compose run --rm prestashop-builder bash

# eseguire script PHP tools/build/CreateRelease.php per creare la versione 9.0.0 di PS
php tools/build/CreateRelease.php --version="9.0.0" --destination="tools/build/releases/" zip-name="prestashop_9.0.0.zip"  --zip
 ```