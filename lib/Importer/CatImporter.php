<?php
class CatImporter extends Importer {
    public function getImporterName() {
        return 'Cat';
    }

    public function getImporterDescription() {
        return 'Importe les catégories';
    }

    public function getImporterVersion() {
        return 'dev';
    }

    public function import(IOManager $io, WordPressAccess $wordPressAccess, PHPBoostAccess $phpBoostAccess) {
        // Récupération de la liste des categories existants dans PHPBoost
        $phpBoostCat = $phpBoostAccess->getAllNewsCats();

        // Récupération de la liste des categories existants dans Wordpress
        $wordPressCat = $wordPressAccess->getAllTerms();

        // Parcours des différents utilisateurs WordPress
        foreach($wordPressCat as $cat) {
            if(!array_key_exists($cat->slug, $phpBoostCat)) {
                // Si l'utilisateur n'existe pas
                $this->add($phpBoostAccess, $cat);
                $io->writeln('Info: Categorie ' . $cat->slug . ' ajouté.');
            } else {
                // Si l'utilisateur existe
                $io->writeln('Erreur: La catégorie ' . $cat->slug . ' existe déjà.');
            }
        }
    }

    protected function add(PHPBoostAccess $pba, stdClass $cat) {
        // Gestion de la catégorie parente
        $parent_id = 0;
        if(!is_null($cat->parent_slug)) {
            $cats = $pba->getAllNewsCats();
            if(array_key_exists($cat->parent_slug, $cats)) {
                $parent_id = $cats[$cat->parent_slug]->id;
            }
        }

        $query = $pba->getSql()->prepare('
            INSERT INTO ' . $pba->getPrefix() . 'news_cats(name, rewrited_name, description, c_order, image, id_parent)
            VALUES(:name, :rewrited_name, :description, :c_order, :image, :id_parent)
        ');

        $query->execute(array(
            'name' => $cat->name,
            'rewrited_name' => $cat->slug,
            'description' => $cat->description,
            'c_order' => 1,
            'image' => PHPBOOST_CAT_IMAGE,
            'id_parent' => $parent_id
        ));
    }
}