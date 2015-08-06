<?php
abstract class Importer {
    public static function getAvailableImporter() {
        $availableImporter = array();
        $importers = scandir(__DIR__ . '/Importer/');
        foreach($importers as $importer) {
            if(substr($importer, -4) === '.php') {
                $className = substr($importer, 0, -4);
                require_once __DIR__ . '/Importer/' . $className . '.php';
                if(class_exists($className) && is_subclass_of($className, __CLASS__)) {
                    $importer = new $className();
                    $availableImporter[$importer->getImporterName()] = array(
                        'name' => $importer->getImporterName(),
                        'version' => $importer->getImporterVersion(),
                        'description' => $importer->getImporterDescription(),
                        'className' => $className
                    );
                }
            }
        }

        return $availableImporter;
    }

    public static function run(IOManager $io, $wpPath, $pBoostPath, array $importerList) {
        $availableImporter = self::getAvailableImporter();
        $phpBoostAccess = new PHPBoostAccess($pBoostPath, $io);
        $wordPressAccess = new WordPressAccess($wpPath, $io);

        foreach($importerList as $importer) {
            if(array_key_exists($importer, $availableImporter)) {
                $object = new $availableImporter[$importer]['className']();
                $object->import($io, $wordPressAccess, $phpBoostAccess);
            }
        }
    }

    public abstract function import(IOManager $io, WordPressAccess $wordPressAccess, PHPBoostAccess $phpBoostAccess);
    public abstract function getImporterName();
    public abstract function getImporterDescription();
    public abstract function getImporterVersion();
}