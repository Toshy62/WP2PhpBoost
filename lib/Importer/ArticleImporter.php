<?php

class ArticleImporter extends Importer {
    public function getImporterName() {
        return 'Article';
    }

    public function getImporterDescription() {
        return 'Gère l\'importation des articles';
    }

    public function getImporterVersion() {
        return '4.1.0-alpha';
    }

    public function getImporterDependency() {
        return array('User', 'Cat');
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
            if($this->importMedia($media, $authorUserId, $wordPressAccess, $phpBoostAccess)) {
                $io->writeln('Info: Media ' . $media->path . ' importé.');
            } else {
                $io->writeln('Erreur lors de l\'importation de ' . $media->path . ', soit la destination existe déjà où la source est inexistante.');
            }
            $post->post_content = str_replace($media->url, FILESYSTEM_IMPORT_LOCATION . $media->path, $post->post_content);
        }

        // Nettoyage du code des images
        $post->post_content = preg_replace('#<img (.+)src="([^\"]+)"(.+)/>#', '<img src="$2" alt="" />', $post->post_content);
        // Gestion du caption
        $post->post_content = preg_replace('#\[caption(.+)align="align(center|left|right)"(.+)\](.+)</a>(.+)\[\/caption\]#', '<p style="text-align:$2">$4</a><br>$5</p>', $post->post_content);

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

        $newsID = $phpBoostAccess->getSql()->lastInsertId();

        // Gestion des tags
        $tags = $wordPressAccess->getPostsTags($post->ID);
        foreach($tags as $tag) {
            $this->addTag($phpBoostAccess, $newsID, $tag);
        }
    }

    public function addTag(PHPBoostAccess $PHPBoostAccess, $newsID, $tag) {
        // Verification que le tag existe
        $id = $PHPBoostAccess->createTagIfNotExist($tag->name, $tag->slug);

        // Ajout du tag à l'article
        $insert = $PHPBoostAccess->getSql()->prepare('
            INSERT IGNORE INTO '.$PHPBoostAccess->getPrefix().'keywords_relations(id_in_module, module_id, id_keyword)
            VALUES (:id_in_module, :module_id, :id_keyword)
        ');

        $insert->execute(array(
            'id_in_module' => $newsID,
            'module_id' => 'news',
            'id_keyword' => $id
        ));
    }

    public function importMedia(stdClass $media, $userId, WordPressAccess $wordPressAccess, PHPBoostAccess $phpBoostAccess) {
        $src = $wordPressAccess->getPath() . 'wp-content/uploads/' . $media->path;
        $dest = $phpBoostAccess->getPath() . FILESYSTEM_IMPORT_LOCATION . $media->path;

        if(!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
        if(!file_exists($dest) && file_exists($src)) {
            copy($src, $dest);

            $file = pathinfo($dest);

            // Ajout dans la base de données
            $insert = $phpBoostAccess->getSql()->prepare('
                INSERT IGNORE INTO '.$phpBoostAccess->getPrefix().'upload(name, path, user_id, size, type, timestamp)
                VALUES (:name, :path, :user_id, :size, :type, :timestamp)
            ');
            $insert->execute(array(
                'name' => $file['basename'],
                'path' => str_replace('upload/', '', FILESYSTEM_IMPORT_LOCATION . $media->path),
                'user_id' => $userId,
                'size' => round(filesize($dest) / 1024),
                'type' => $file['extension'],
                'timestamp' => filemtime($dest)
            ));

            return true;
        }

        return false;
    }
}