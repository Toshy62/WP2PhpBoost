<?php

class ArticleImporter extends Importer {
    public function getImporterName() {
        return 'Article';
    }

    public function getImporterDescription() {
        return 'Gère l\'importation des articles';
    }

    public function getImporterVersion() {
        return 'dev';
    }

    public function import(IOManager $io, WordPressAccess $wordPressAccess, PHPBoostAccess $phpBoostAccess) {
        // Récupération de tous les articles Wordpress
        $wpPost = $wordPressAccess->getAllPosts();

        // Récupération de tous les articles PHPBoost existant
        $pboostPost = $phpBoostAccess->getAllPosts();

        foreach($wpPost as $post) {
            if (!array_key_exists($post->post_name, $pboostPost)) {
                // Si l'article n'existe pas, on le crée
                $this->addArticle($io, $phpBoostAccess, $post, $wordPressAccess);
                $io->writeln('Info: Article ' . $post->post_name . ' ajouté.');
            } else {
                // Si l'article existe
                $io->writeln('Erreur: L\'article ' . $post->post_name . ' existe déjà.');
            }
        }
    }

    protected function addArticle(IOManager $io, PHPBoostAccess $phpBoostAccess, stdClass $post, WordPressAccess $wordPressAccess) {
        $query = $phpBoostAccess->getSql()->prepare('
            INSERT INTO ' . $phpBoostAccess->getPrefix() . 'news(id_category, picture_url, name, rewrited_name, contents, short_contents, creation_date, updated_date, approbation_type, author_user_id)
            VALUES (:id_category, :picture_url, :name, :rewrited_name, :contents, :short_contents, :creation_date, :updated_date, :approbation_type, :author_user_id)
        ');

        // Gestion de l'auteur (si un utilisateur portant ce nom existe => On le prends)
        // Sinon on utilise le DEFAULT_AUTHOR_ID
        $users = $phpBoostAccess->getAllUsers();
        if(array_key_exists($post->author_name, $users)) {
            $authorUserId = $users[$post->author_name]->user_id;
        } else {
            $authorUserId = DEFAULT_AUTHOR_ID;
        }

        // Gestion des images/media
        $medias = $wordPressAccess->getMediaForPost($post->ID);
        foreach($medias as $media) {
            $src = $wordPressAccess->getPath() . 'wp-content/uploads/' . $media->path;
            $dest = $phpBoostAccess->getPath() . FILESYSTEM_IMPORT_LOCATION . $media->path;

            if(!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
            if(!file_exists($dest)) {
                $io->writeln('Info: Media ' . $media->path . ' importé.');
                copy($src, $dest);
            } else {
                $io->writeln('Erreur: Media ' . $media->path . ' déjà existant.');
            }

            $post->post_content = str_replace($media->url, FILESYSTEM_IMPORT_LOCATION . $media->path, $post->post_content);
        }

        // Nettoyage du code des images
        $post->post_content = preg_replace('#<img (.+)src="([^\"]+)"(.+)/>#', '<img src="$2" alt="" />', $post->post_content);

        // Gestion de la categorie
        $idCategory = 0;
        if(!is_null($post->term_slug)) {
            $cats = $phpBoostAccess->getAllNewsCats();
            if(array_key_exists($post->term_slug, $cats)) {
                $idCategory = $cats[$post->term_slug]->id;
            }
        }


        // Ajout de l'article dans la BDD
        $query->execute(array(
            'id_category' => $idCategory,
            'picture_url' => (!empty($post->thumbnail) ? FILESYSTEM_IMPORT_LOCATION . $post->thumbnail : ''),
            'name' => $post->post_title,
            'rewrited_name' => $post->post_name,
            'contents' => str_replace('<!--more-->', '', $post->post_content),
            'short_contents' => (count(explode('<!--more-->', $post->post_content)) > 1 ? explode('<!--more-->', $post->post_content)[0] : ''),
            'creation_date' => (new DateTime($post->post_date_gmt))->getTimestamp(),
            'updated_date' => (new DateTime($post->post_modified))->getTimestamp(),
            'approbation_type' => 1,
            'author_user_id' => $authorUserId
        ));
    }
}