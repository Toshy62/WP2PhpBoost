<?php
class CommentImporter extends Importer {
    public function getImporterName() {
        return 'Comment';
    }

    public function getImporterDescription() {
        return 'Importe les commentaires';
    }

    public function getImporterVersion() {
        return 'dev';
    }

    public function getImporterDependency() {
        return array('Article');
    }

    public function import(IOManager $io, WordPressAccess $wordPressAccess, PHPBoostAccess $phpBoostAccess) {
        // Get all
    }
}