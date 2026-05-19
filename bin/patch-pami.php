<?php
/**
 * Patches marcelog/pami v2.0.2 for PHP 8 compatibility.
 *
 * PAMI 2.0.2 calls implode() with the legacy argument order
 * (implode($array, $glue)), which PHP 8.0+ rejects with:
 *   TypeError: implode(): Argument #2 ($array) must be of type ?array, string given
 *
 * Runs automatically via composer post-install-cmd / post-update-cmd, and can
 * be run manually:  php bin/patch-pami.php
 *
 * Idempotent: safe to run repeatedly.
 */

$file = __DIR__ . '/../vendor/marcelog/pami/src/PAMI/Message/Event/Factory/Impl/EventFactoryImpl.php';

if (!is_file($file)) {
    // PAMI not installed (e.g. --no-dev on a host without it); nothing to do.
    fwrite(STDOUT, "patch-pami: PAMI not found, skipping ($file)\n");
    exit(0);
}

$contents = file_get_contents($file);
$search  = "implode(\$parts, '')";
$replace = "implode('', \$parts)";

if (strpos($contents, $search) === false) {
    fwrite(STDOUT, "patch-pami: already patched (or pattern absent)\n");
    exit(0);
}

$contents = str_replace($search, $replace, $contents);

if (file_put_contents($file, $contents) === false) {
    fwrite(STDERR, "patch-pami: FAILED to write $file (check permissions)\n");
    exit(1);
}

fwrite(STDOUT, "patch-pami: applied PHP 8 implode() fix to EventFactoryImpl.php\n");
exit(0);
