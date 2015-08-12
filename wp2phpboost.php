<?php
function imp_error($errno, $errstr, $errfile, $errline, $errcontext) {
    throw new Exception($errfile . '(' . $errline . ') : ' . $errstr, $errno);
}

set_error_handler('imp_error', E_ALL);

require_once __DIR__ . '/lib/IOManager.php';
require_once __DIR__ . '/lib/IOCliManager.php';
require_once __DIR__ . '/lib/Importer.php';
require_once __DIR__ . '/lib/PHPBoostAccess.php';
require_once __DIR__ . '/lib/WordPressAccess.php';

$io = new IOCliManager();

function checkPhpVersion() {
    if(phpversion() < '5.4') {
        throw new Exception('WP2PhpBoost require php 5.4 or never');
    }
}

/**
 * Get the wordpress instance path
 * @return bool
 */
function getWpPath() {
    global $io;

    $io->write('Chemin du l\'installation de Wordpress :');
    $wpPath = $io->read('wp-path');
    $wpPath = (substr($wpPath, -1) === '/') ? $wpPath : $wpPath . '/';

    if(file_exists($wpPath . 'wp-config.php')) {
        define('WP_PATH', $wpPath);
        return true;
    }
    return false;
}

/**
 * Get the PhpBoost Path
 * @return bool
 */
function getPhpBoostPath() {
    global $io;

    $io->write('Chemin du l\'installation de PHPBoost :');
    $pBoost = $io->read('pboost-path');
    $pBoost = (substr($pBoost, -1) === '/') ? $pBoost : $pBoost . '/';

    if(file_exists($pBoost . 'kernel/db/config.php')) {
        define('PBOOST_PATH', $pBoost);
        return true;
    }
    return false;
}

function getImporterList() {
    static $availableImporter;
    global $io;

    if(is_null($availableImporter)) {
        $availableImporter = Importer::getAvailableImporter();
    }

    $io->writeln('Liste des importers existants :');

    foreach($availableImporter as $importer) {
        $io->writeln('  - ' . $importer['name'] . ' (' . $importer['version'] . ') : ' . $importer['description']);
    }
    $io->writeln();

    $io->writeln('Lister les importateurs à utiliser (séparer les par une virgule) :');
    $importerListStr = $io->read('list-importer');

    $importerList = explode(',', $importerListStr);
    $importerList = array_map(function($str) { return trim($str); }, $importerList);

    foreach($importerList as $importer) {
        if(!array_key_exists($importer, $availableImporter)) {
            return false;
        }
    }

    define('IMPORTER_LIST', implode(',', $importerList));
    return true;
}
try {
    checkPhpVersion();

    if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

    while (!defined('WP_PATH')) getWpPath();
    while (!defined('PBOOST_PATH')) getPhpBoostPath();

    while (!defined('IMPORTER_LIST')) getImporterList();

// Récupération de la configuration par défaut
    $defaultConfig = require_once __DIR__ . '/config-default.php';
    foreach ($defaultConfig as $key => $value) {
        if (!defined($key)) {
            define($key, $value);
        }
    }

    Importer::run($io, WP_PATH, PBOOST_PATH, explode(',', IMPORTER_LIST));
    return true;
} catch(Exception $e) {
    $io->writeln($e->getMessage());
    return false;
}
?>