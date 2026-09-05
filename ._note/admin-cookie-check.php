<?php
/**
 * Front Office diagnostic retained in ._note after the browser verification.
 * Uses the FO bootstrap, but never constructs or writes an Admin Cookie object.
 */
declare(strict_types=1);

use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;

define('_PS_FRONT_DIR_', dirname(__DIR__));
require_once _PS_FRONT_DIR_ . '/config/config.inc.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

// This duplicates only the payload-reading format from Cookie::update().
// Do not use Cookie::update(): a bad checksum can trigger logout and writes.
$inspectCookie = static function (string $cookieName): array {
    $checks = [
        'Cookie ricevuto da PHP' => false,
        'Decifratura riuscita' => null,
        'Formato del contenuto valido' => null,
        'Checksum valido' => null,
    ];

    $encrypted = $_COOKIE[$cookieName] ?? null;
    if (!is_string($encrypted) || $encrypted === '') {
        return $checks;
    }
    $checks['Cookie ricevuto da PHP'] = true;

    $plaintext = (new PhpEncryption(_NEW_COOKIE_KEY_))->decrypt($encrypted);
    $checks['Decifratura riuscita'] = is_string($plaintext) && $plaintext !== '';
    if (!$checks['Decifratura riuscita']) {
        return $checks;
    }

    $parts = explode('¤', $plaintext);
    $checksumPart = explode('|', array_pop($parts));
    $checks['Formato del contenuto valido'] = count($checksumPart) === 2
        && $checksumPart[0] === 'checksum';
    if (!$checks['Formato del contenuto valido']) {
        return $checks;
    }

    $fields = [];
    foreach ($parts as $part) {
        $pair = explode('|', $part);
        if (count($pair) !== 2 || array_key_exists($pair[0], $fields) || $pair[0] === 'checksum') {
            $checks['Formato del contenuto valido'] = false;

            return $checks;
        }
        $fields[$pair[0]] = $pair[1];
    }

    $expectedChecksum = hash('sha256', _COOKIE_IV_ . implode('¤', $parts) . '¤');
    $checks['Checksum valido'] = hash_equals($expectedChecksum, $checksumPart[1]);
    if (!$checks['Checksum valido']) {
        return $checks;
    }

    foreach (['id_employee', 'session_id', 'session_token', 'admin_path'] as $field) {
        $checks['Campo ' . $field . ' presente e non vuoto'] = isset($fields[$field]) && $fields[$field] !== '';
    }

    return $checks;
};

$cookieName = $_POST['cookie_name'] ?? '';
$cookieName = is_string($cookieName) ? trim($cookieName) : '';
$checks = [];
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!preg_match('/\APrestaShop-[a-f0-9]{32}\z/', $cookieName)) {
        $error = 'Inserisci il nome completo del cookie BO: PrestaShop- seguito da 32 caratteri esadecimali.';
    } else {
        try {
            $checks = $inspectCookie($cookieName);
        } catch (BadFormatException | EnvironmentIsBrokenException $exception) {
            $error = 'Il componente crittografico non ha potuto completare il controllo.';
        }
    }
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="it">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verifica cookie Admin nel Front Office</title>
<h1>Verifica cookie Admin nel Front Office</h1>
<p>Inserisci solo il nome del cookie identificato nel BO, mai il suo valore.</p>
<form method="post">
    <label for="cookie-name">Nome cookie BO</label>
    <input id="cookie-name" name="cookie_name" value="<?= $escape($cookieName) ?>" size="48" maxlength="43" required autocomplete="off" spellcheck="false">
    <button type="submit">Verifica</button>
</form>
<?php if ($error !== ''): ?>
<p><?= $escape($error) ?></p>
<?php endif; ?>
<?php if ($checks !== []): ?>
<ul>
    <?php foreach ($checks as $label => $result): ?>
    <li><?= $escape($label) ?>: <strong><?= $result === null ? 'NON ESEGUITO' : ($result ? 'SÌ' : 'NO') ?></strong></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
<p>Questa prova verifica ricezione e lettura del cookie selezionato, non la validità della sessione employee.
Il campo admin_path può essere assente: la sua scrittura non è ancora stata implementata.</p>
</html>
