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
                        'dependency' => $importer->getImporterDependency(),
                        'className' => $className
                    );
                }
            }
        }

        return $availableImporter;
    }

    public static function run(IOManager $io, $wpPath, $pBoostPath, array $importerList) {
        $availableImporter = self::getAvailableImporter();
        $importerList = self::sortImporterList($importerList);
        $phpBoostAccess = new PHPBoostAccess($pBoostPath, $io);
        $wordPressAccess = new WordPressAccess($wpPath, $io);

        foreach($importerList as $importer) {
            if(array_key_exists($importer, $availableImporter)) {
                $object = new $availableImporter[$importer]['className']();
                $object->import($io, $wordPressAccess, $phpBoostAccess);
            }
        }
    }

    private static function sortImporterList($importerList) {
        $availableImporter = self::getAvailableImporter();

        $importerListWithInformation = array();

        foreach($importerList as $importerName) {
            $importerListWithInformation[$importerName] = $availableImporter[$importerName];
        }

        uasort($importerListWithInformation, function($importerA, $importerB) {
            if(in_array($importerA['name'], $importerB['dependency'])) {
                // Si l'importateur B dépends de l'importateur A
                return -1;
            } elseif(in_array($importerB['name'], $importerA['dependency'])) {
                // Si l'importateur A dépends de l'importateur B
                return 1;
            } else {
                return 0;
            }
        });

        return array_keys($importerListWithInformation);
    }

    public abstract function import(IOManager $io, WordPressAccess $wordPressAccess, PHPBoostAccess $phpBoostAccess);
    public abstract function getImporterName();
    public abstract function getImporterDescription();
    public abstract function getImporterVersion();
    public abstract function getImporterDependency();
}