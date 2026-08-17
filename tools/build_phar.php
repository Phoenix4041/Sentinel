<?php

/**
 * Packages this plugin into a .phar the same way DevTools does: plugin.yml,
 * src/ and resources/ at the phar root. Run with phar.readonly=0, e.g.
 *   php -d phar.readonly=0 tools/build_phar.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root . DIRECTORY_SEPARATOR . "build";
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$pluginYmlText = file_get_contents($root . DIRECTORY_SEPARATOR . "plugin.yml");
if (
    $pluginYmlText === false
    || !preg_match('/^name:\s*(\S+)/m', $pluginYmlText, $nameMatch)
    || !preg_match('/^version:\s*(\S+)/m', $pluginYmlText, $versionMatch)
) {
    fwrite(STDERR, "Could not read name/version from plugin.yml\n");
    exit(1);
}

$pharPath = $outDir . DIRECTORY_SEPARATOR . $nameMatch[1] . "_v" . $versionMatch[1] . ".phar";
if (file_exists($pharPath)) {
    unlink($pharPath);
}

$phar = new Phar($pharPath);
$phar->setStub('<?php __HALT_COMPILER();');
$phar->startBuffering();

$phar->addFile($root . DIRECTORY_SEPARATOR . "plugin.yml", "plugin.yml");
if (file_exists($root . DIRECTORY_SEPARATOR . "LICENSE")) {
    $phar->addFile($root . DIRECTORY_SEPARATOR . "LICENSE", "LICENSE");
}

foreach (["src", "resources"] as $dir) {
    $path = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $relative = $dir . DIRECTORY_SEPARATOR . substr($file->getPathname(), strlen($path) + 1);
        $phar->addFile($file->getPathname(), $relative);
    }
}

$phar->stopBuffering();

echo "Built: $pharPath\n";
