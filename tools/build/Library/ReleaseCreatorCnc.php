<?php

/**
 * Questa classe estende la classe ReleaseCreator e serve per far funzionare
 * NOTA: sono riuscito ad ottenere lo ZIP uguale, ci sono solo 2 differenze, nel file ZIP creato con Windows (quindi questo script modificato):
 * - c'è in più il file themes/classic/assets/js/theme.js.LICENSE.txt
 * - il file themes/classic/assets/js/theme.js è leggermente diverso come dimensione
 */
class ReleaseCreatorCnc extends ReleaseCreator
{
    /**
     * Create a new release.
     *
     * @return $this
     * @throws BuildException
     */
    public function createRelease()
    {
        if (!file_exists($this->destinationDir) && !mkdir($this->destinationDir, 0777, true)) {
            throw new BuildException("ERROR: can not create directory '{$this->destinationDir}'");
        }
        $startTime = date('H:i:s');
        $this->consoleWriter->displayText(
            "--- Script started at {$startTime}{$this->lineSeparator}{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );

        $this->createTmpProjectDir()
            ->setFilesConstants()
            ->setupShopVersion()
            ->generateLicensesFile()
            ->generateCachedirFiles()
            ->runComposerInstall()
            ->runBuildAssets()
            ->createPackage();
        $endTime = date('H:i:s');
        $this->consoleWriter->displayText(
            "{$this->lineSeparator}--- Script ended at {$endTime}{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );

        if ($this->useZip) {
            $releaseSize = $this->humanFileSize(filesize("{$this->destinationDir}/{$this->zipFileName}"));
        } else {
            $releaseSize = $this->humanFileSize(filesize("{$this->destinationDir}"));
        }
        $this->consoleWriter->displayText(
            "--- Release size: {$releaseSize}{$this->lineSeparator}",
            ConsoleWriter::COLOR_GREEN
        );

        return $this;
    }

    private function humanFileSize(int $bytes, int $decimals = 1): string
    {
        $size = ['B', 'K', 'M', 'G', 'T', 'P'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $size[$factor];
    }

    /**
     * Copy current user PrestaShop dir to a tmp directory
     * where we'll clean it for the release.
     *
     * @return $this
     */
    protected function createTmpProjectDir()
    {
        $this->consoleWriter->displayText("Copy project in {$this->tempProjectPath}...", ConsoleWriter::COLOR_YELLOW);
        $argProjectPath = escapeshellarg($this->projectPath);

        // Elimina la cartella temporanea
        if (file_exists($this->tempProjectPath)) {
            exec("rmdir /S /Q " . str_replace('/', '\\', $this->tempProjectPath));
        }

        // Crea la cartella temporanea
        exec("mkdir " . str_replace('/', '\\', $this->tempProjectPath));

        // Crea archivio zip del progetto con git
        $archivePath = $this->tempProjectPath . DIRECTORY_SEPARATOR . 'project.zip';
        $archivePathEscaped = escapeshellarg($archivePath);

        exec("git -C {$argProjectPath} archive --format=zip HEAD -o {$archivePathEscaped}", $output, $result);
        if ($result !== 0) {
            throw new \RuntimeException("Errore durante la creazione dell'archivio git.");
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath) === true) {
            $zip->extractTo($this->tempProjectPath);
            $zip->close();
        } else {
            throw new \RuntimeException("Impossibile aprire l'archivio ZIP.");
        }

        // Rimuovi lo zip
        unlink($archivePath);

        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);
        return $this;
    }

    /**
     * Install all dependencies.
     *
     * @return $this
     * @throws BuildException
     */
    protected function runComposerInstall()
    {
        $this->consoleWriter->displayText("Scarico Composer localmente...", ConsoleWriter::COLOR_YELLOW);
        $composerPath = $this->downloadComposer();

        $this->consoleWriter->displayText("Running composer install...", ConsoleWriter::COLOR_YELLOW);
        $argProjectPath = escapeshellarg($this->tempProjectPath);
        $autoloaderSuffix = md5($this->version);

        $this->setComposerBinCompat(true);
        $command =
            "php {$composerPath} config autoloader-suffix {$autoloaderSuffix} --working-dir={$argProjectPath} && " .
            "php {$composerPath} install --no-dev --optimize-autoloader --no-interaction --working-dir={$argProjectPath} 2>&1";
        
        exec($command, $output, $returnCode);

        $this->setComposerBinCompat(false);

        if (!empty($output)) {
            $logPath = __DIR__ . '/../../../var/logs/composer-install.log';
            file_put_contents($logPath, implode(PHP_EOL, $output));
        }

        if ($returnCode !== 0) {
            throw new BuildException('Unable to run composer install.');
        }

        // elimino composer.phar
        unlink($composerPath);

        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Build assets.
     *
     * @return $this
     * @throws BuildException
     */
    protected function runBuildAssets()
    {
        $this->consoleWriter->displayText("Running build assets... {$this->lineSeparator}", ConsoleWriter::COLOR_YELLOW);

        // ***** aggiorno il file "package.json"
        // NOTA: le seguenti istruzioni servono per il template "classic" che ha problemi su windows per la compilazione degli assets
        $packageJSONPath = $this->tempProjectPath . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'classic' . DIRECTORY_SEPARATOR . '_dev' . DIRECTORY_SEPARATOR . 'package.json';
        $content = file_get_contents($packageJSONPath);

        // Sostituisci solo se trovi esattamente quella stringa
        $search = '"build": "NODE_ENV=production webpack --progress"';
        $replace = '"build": "set NODE_ENV=production && webpack --progress"';

        if (strpos($content, $search) !== false) {
            $content = str_replace($search, $replace, $content);
            file_put_contents($packageJSONPath, $content);
        }

        // ***** aggiorno il file "webpack.config.js"
        $webpackPath = $this->tempProjectPath . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'classic' . DIRECTORY_SEPARATOR . '_dev' . DIRECTORY_SEPARATOR . 'webpack.config.js';
        $webpackContent = file_get_contents($webpackPath);

        $search = "mode: process.env.NODE_ENV || 'development',";
        $replace = "mode: 'production',";

        if (strpos($webpackContent, $search) !== false) {
            $webpackContent = str_replace($search, $replace, $webpackContent);
            file_put_contents($webpackPath, $webpackContent);
        }

        $arry = [
            // templates admin
            $this->tempProjectPath . DIRECTORY_SEPARATOR . 'admin-dev' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'default',
            $this->tempProjectPath . DIRECTORY_SEPARATOR . 'admin-dev' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'new-theme',
            // templates front
            $this->tempProjectPath . DIRECTORY_SEPARATOR . 'themes',
            $this->tempProjectPath . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'classic' . DIRECTORY_SEPARATOR . '_dev',
            $this->tempProjectPath . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'hummingbird',
        ];

        foreach ($arry as $path) {
            if (!is_dir($path)) {
                die("Attenzione la direrectory \"$path\" non esiste");
            }

            $this->consoleWriter->displayText("--- Build assets in $path...", ConsoleWriter::COLOR_YELLOW);

            chdir($path);

            // installo le dipendenze
            $command = 'npm install 2>&1';

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new BuildException('Problema installazione npm su path: ' . $path);
            }

            // eseguo la compilazione degli assets
            $command = 'npm run build 2>&1';
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new BuildException('Problema creazione assets su path: ' . $path);
            }

            // elimino le dipendenze
            $command = 'rmdir /S /Q ' . escapeshellarg($path . DIRECTORY_SEPARATOR . 'node_modules');
            exec($command, $output, $returnCode);

            $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);
        }

        $this->consoleWriter->displayText("Build assets", ConsoleWriter::COLOR_YELLOW);
        $this->consoleWriter->displayText(" COMPLETED{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Clean project with unwanted files and folders, generate a checksum xml file,
     * zip the directory and move it to the final destination.
     *
     * @return $this
     */
    protected function createPackage()
    {
        $this->tempProjectPath = str_replace(['/', '\\'], '/', $this->tempProjectPath);
        parent::createPackage();

        return $this;
    }

    /**
     * Return the directory structure of a given path as an array.
     *
     * @param string $path
     * @return array
     */
    protected function getDirectoryStructure($path)
    {
        $structure = parent::getDirectoryStructure($path);

        $normalize = function ($node) use (&$normalize) {
            if (is_array($node)) {
                $out = [];
                foreach ($node as $key => $value) {
                    // normalizza la chiave solo se è stringa (directory)
                    if (is_string($key)) {
                        $newKey = str_replace(['\\', '/'], '/', $key);
                        $out[$newKey] = $normalize($value);
                    } else {
                        // valori indicizzati (file) mantengono l'indice numerico
                        $out[] = $normalize($value);
                    }
                }
                // opzionale: ordina per coerenza, dato che le chiavi sono cambiate
                ksort($out);
                return $out;
            }

            // normalizza i valori foglia (percorsi file)
            if (is_string($node)) {
                return str_replace(['\\', '/'], '/', $node);
            }

            return $node;
        };

        return $normalize($structure);
    }

    /**
     * Delete unwanted files and folders in the PrestaShop tmp directory.
     *
     * @param array $filesList
     * @param array $filesRemoveList
     * @param array $foldersRemoveList
     * @param array $patternsRemoveList
     * @param string $folder
     * @return $this
     * @throws BuildException
     */
    protected function removeUnnecessaryFiles(
        array &$filesList,
        array &$filesRemoveList,
        array &$foldersRemoveList,
        array &$patternsRemoveList,
        $folder
    ) {
        $tmpDir = str_replace(['/', '\\'], '/', $this->tempProjectPath);
        $folder = str_replace(['/', '\\'], '/', $folder);

        $tmpDirPathLength = strlen($tmpDir);

        foreach ($filesList as $key => $value) {
            $pathToTest = $value;

            if (!is_string($pathToTest)) {
                $pathToTest = $key;
            }

            if (substr($pathToTest, 0, $tmpDirPathLength) != $tmpDir) {
                throw new BuildException("Trying to delete a file somewhere else than in $tmpDir, path: $pathToTest");
            }

            if (is_numeric($key)) {
                $argValue = $value;

                // Remove files.
                foreach ($filesRemoveList as $file_to_remove) {
                     if ($folder.'/'.$file_to_remove == $value) {
                        unset($filesList[$key]);
                        $this->rmFile($argValue);

                        continue 2;
                    }
                }

                // Remove folders.
                foreach ($foldersRemoveList as $folder_to_remove) {
                    if ($folder.'/'.$folder_to_remove == $value) {
                        unset($filesList[$key]);
                        $this->rmRf($argValue);

                        continue 2;
                    }
                }

                // Pattern to remove.
                foreach ($patternsRemoveList as $pattern_to_remove) {
                    if (preg_match('#'.$pattern_to_remove.'#', $value) == 1) {
                        unset($filesList[$key]);
                        $this->rmRf($argValue);

                        continue 2;
                    }
                }
            } else {
                $argKey = $key;
                // Remove folders.
                foreach ($foldersRemoveList as $folder_to_remove) {
                    if ($folder.'/'.$folder_to_remove == $key) {
                        unset($filesList[$key]);
                        $this->rmRf($argKey);

                        continue 2;
                    }
                }

                // Pattern to remove.
                foreach ($patternsRemoveList as $pattern_to_remove) {
                    if (preg_match('#'.$pattern_to_remove.'#', $key) == 1) {
                        unset($filesList[$key]);
                        $this->rmRf($argKey);

                        continue 2;
                    }
                }
                $this->removeUnnecessaryFiles($filesList[$key], $filesRemoveList, $foldersRemoveList, $patternsRemoveList, $folder);
            }
        }

        return $this;
    }

    /**
     * Zip the release if needed.
     *
     * @return $this
     */
    protected function createZipArchive()
    {
        if (!$this->useZip) {
            return $this;
        }
        $this->consoleWriter->displayText("--- Creating zip archive...", ConsoleWriter::COLOR_YELLOW);
        $installerZipFilename = self::INSTALLER_ZIP_FILENAME;
        $argProjectPath = escapeshellarg($this->projectPath);

        $this->zipFolder($this->tempProjectPath, $installerZipFilename);

        if ($this->useInstaller) {
            exec("pushd {$argProjectPath}/tools/build/Library/InstallUnpacker && php compile.php {$this->version} && popd");

            $zip = new ZipArchive();
            $zip->open("{$this->tempProjectPath}/{$this->zipFileName}", ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile("{$this->tempProjectPath}/{$installerZipFilename}", $installerZipFilename);
            $zip->addFile("{$this->projectPath}/tools/build/Library/InstallUnpacker/index.php", 'index.php');

            // add docs at the root
            $zip->addGlob(
                "{$this->projectPath}/tools/build/doc/*",
                0,
                array('remove_all_path' => true)
            );

            $zip->close();
            $pathFile = $this->projectPath . "/tools/build/Library/InstallUnpacker/index.php";
            $this->rmFile($pathFile);
        } else {
            rename(
                "{$this->tempProjectPath}/$installerZipFilename",
                "{$this->tempProjectPath}/{$this->zipFileName}"
            );
        }
        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * Move the final release to the desired location.
     *
     * @return $this
     */
    protected function movePackage()
    {
        $this->consoleWriter->displayText("--- Move package...", ConsoleWriter::COLOR_YELLOW);
        $tmpDir = sys_get_temp_dir();
        $argTempProjectPath = escapeshellarg($this->tempProjectPath);

        if ($this->useZip) {
            rename(
                "{$this->tempProjectPath}/{$this->zipFileName}",
                "{$this->destinationDir}/prestashop_$this->version.zip"
            );
        } else {
            $argDestinationDir = escapeshellarg($this->destinationDir);
            exec("mv {$argTempProjectPath} {$argDestinationDir}");
        }

        rename(
            "{$tmpDir}/prestashop_$this->version.xml",
            "{$this->destinationDir}/prestashop_$this->version.xml"
        );

        $this->rmRf($this->tempProjectPath);

        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this;
    }

    /**
     * 🔹 NUOVO METODO (aggiunto in questa classe che estende l'implementazione originale)
     * 
     * Rimuove uno o più file/link simbolici.
     *
     * Accetta una stringa (percorso singolo) oppure un array di percorsi (anche annidati).
     * Replica il comportamento di `rm -f`:
     *  - Se il percorso è un file o un symlink esistente → lo elimina.
     *  - Se non esiste → ignora senza errori.
     *  - Forza i permessi di scrittura se necessario.
     *
     * @param string|array $path Percorso o lista di percorsi di file/symlink da eliminare.
     * @return void
     */
    private function rmFile(string|array $path): void
    {
        if (is_array($path)) {
            foreach ($path as $p) {
                $this->rmFile($p);
            }
            return;
        }

        if ($path === '' || $path === null) {
            return;
        }

        // Non usare realpath qui: i symlink non validi tornano false
        if (is_link($path) || is_file($path)) {
            if (!is_writable($path)) { @chmod($path, 0666); }
            @unlink($path);
        }
    }

    /**
     * 🔹 NUOVO METODO (aggiunto in questa classe che estende l'implementazione originale)
     * 
     * Rimuove ricorsivamente file e directory (emula `rm -rf` di Linux).
     *
     * Comportamento:
     *  - Accetta una stringa (percorso singolo) o un array di percorsi (anche annidati).
     *  - File o symlink → eliminati direttamente (forza permessi se necessario).
     *  - Directory → elimina ricorsivamente tutto il contenuto e infine la directory stessa.
     *  - Percorsi inesistenti → ignorati senza errori.
     *  - Forza i permessi di scrittura prima della rimozione per evitare errori su file/cartelle read-only (es. su Windows).
     *
     * @param string|array $path Percorso o lista di percorsi (file/dir) da eliminare.
     * @return void
     */
    private function rmRf(string|array $path): void
    {
        if (is_array($path)) {
            foreach ($path as $p) {
                $this->rmRf($p);
            }
            return;
        }

        if ($path === '' || $path === null) {
            return;
        }

        // Se è file o symlink
        if (is_link($path) || is_file($path)) {
            if (!is_writable($path)) { @chmod($path, 0666); }
            @unlink($path);
            return;
        }

        // Se è directory
        if (is_dir($path)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($it as $item) {
                $p = $item->getPathname();
                if ($item->isDir()) {
                    if (!is_writable($p)) { @chmod($p, 0777); }
                    @rmdir($p);
                } else {
                    if (!is_writable($p)) { @chmod($p, 0666); }
                    @unlink($p);
                }
            }

            // 🔹 Elimina sempre la directory root dopo il loop
            if (is_dir($path)) {
                if (!is_writable($path)) { @chmod($path, 0777); }
                @rmdir($path);
            }
        }
    }

    /**
     * 🔹 NUOVO METODO (aggiunto in questa classe che estende l'implementazione originale)
     * Imposta o rimuove la configurazione `bin-compat` nel `composer.json`.
     *
     * Quando `bin-compat` è impostato a **"proxy"**, Composer non genera i file wrapper `.bat`
     * nella cartella `vendor/bin` su Windows. Al loro posto crea piccoli script PHP
     * che fungono da proxy verso i binari reali.
     *
     * Questo consente di evitare la proliferazione dei file `.bat` mantenendo comunque
     * la possibilità di eseguire i comandi dei pacchetti installati.
     *
     * @param bool $add Se `true`, aggiunge `"bin-compat": "proxy"` alla sezione `config`;
     *                  se `false`, rimuove l’impostazione ripristinando il comportamento predefinito.
     */
    protected function setComposerBinCompat(bool $add): void
    {
        $file = $this->tempProjectPath . DIRECTORY_SEPARATOR . 'composer.json';
        $json = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        if (!is_array($json)) { $json = []; }
        if (!isset($json['config']) || !is_array($json['config'])) { $json['config'] = []; }
        if ($add) {
            $json['config']['bin-compat'] = 'proxy';
        } else {
            unset($json['config']['bin-compat']);
        }

        file_put_contents(
            $file,
            json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }

    /**
     * 🔹 NUOVO METODO (aggiunto in questa classe che estende l'implementazione originale)
     * Scarica ed installa una copia locale di Composer nella cartella temporanea del progetto.
     *
     * Le seguenti istruzioni sono state prese dalla documentazione ufficiale:
     * https://getcomposer.org/download/
     *
     * Il metodo esegue i seguenti passi:
     *  1. Scarica lo script di installazione ufficiale (`composer-setup.php`)
     *     dal sito getcomposer.org.
     *  2. Verifica l'integrità del file tramite hash SHA-384 confrontandolo
     *     con la firma ufficiale (per evitare manomissioni).
     *  3. Esegue lo script di setup con l'interprete PHP corrente,
     *     installando `composer.phar` all’interno della cartella temporanea.
     *  4. Rimuove lo script di setup per pulizia.
     *
     * In caso di errore durante la verifica o l'esecuzione dello script,
     * viene sollevata un'eccezione `RuntimeException`.
     *
     * @return string Percorso completo al file `composer.phar` installato.
     *
     * @throws \RuntimeException Se il file scaricato non supera il controllo hash
     *                           o se l’installer non viene eseguito correttamente.
     */
    protected function downloadComposer()
    {
        $composerSetupPath = $this->tempProjectPath . DIRECTORY_SEPARATOR . 'composer-setup.php';
        copy('https://getcomposer.org/installer', $composerSetupPath);
        if (!hash_file('sha384', $composerSetupPath) === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') {
            unlink($composerSetupPath); 
            throw new \RuntimeException("Installer corrupt:");
        }

        $installDir = $this->tempProjectPath . DIRECTORY_SEPARATOR; // o qualsiasi altro percorso
        $phpExecutable = escapeshellarg(PHP_BINARY);

        // Costruisci il comando con opzioni
        $composerCmd = $phpExecutable . ' ' 
            . escapeshellarg($composerSetupPath)
            . ' --install-dir=' . escapeshellarg($installDir)
            . ' --filename=composer.phar';
        exec($composerCmd, $output, $result);

        if ($result !== 0) {
            throw new \RuntimeException("Errore nell'esecuzione di composer-setup.php:\n" . implode("\n", $output));
        }

        unlink($composerSetupPath);

        $this->consoleWriter->displayText(" DONE{$this->lineSeparator}", ConsoleWriter::COLOR_GREEN);

        return $this->tempProjectPath . DIRECTORY_SEPARATOR . 'composer.phar';
    }

    /**
     * 🔹 NUOVO METODO (aggiunto in questa classe che estende l'implementazione originale)
     *
     * Crea un archivio ZIP a partire dal contenuto di una cartella sorgente.
     *
     * Il metodo utilizza l'estensione `ZipArchive` di PHP per:
     *  - aprire/creare un archivio ZIP nel percorso specificato,
     *  - scansionare ricorsivamente la directory sorgente,
     *  - aggiungere file e sottocartelle mantenendo la struttura delle directory,
     *  - restituire `true` in caso di chiusura corretta dell'archivio.
     *
     * @param string $source      Percorso della cartella sorgente da comprimere.
     * @param string $destination Nome (o percorso relativo alla sorgente) del file ZIP da creare.
     *
     * @return bool `true` se l'archivio viene creato e chiuso correttamente, `false` altrimenti.
     *
     * @throws \Exception Se l'estensione `zip` non è disponibile.
     */
    private function zipFolder($source, $destination)
    {
        if (!extension_loaded('zip')) {
            throw new Exception('La libreria ZIP per PHP non esiste');
        }

        $zip = new ZipArchive();
        if ($zip->open($source . DIRECTORY_SEPARATOR .$destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $source = realpath($source);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = realpath($file);
            $relativePath = substr($filePath, strlen($source) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    }
}
