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
        // Récupération de la totalité des commentaires du Wordpress
        $wpComments = $wordPressAccess->getAllComments();
        // Récupération de la totalité des news de PHPBoost
        $phpBoostNews = $phpBoostAccess->getAllPosts();

        foreach($wpComments as $newsSlug => $comments) {
            if(!array_key_exists($newsSlug, $phpBoostNews)) {
                $io->writeln('Erreur: Commentaire existant pour la news "'.$newsSlug.'" mais celle-ci n\'existe pas');
                continue;
            }
            $io->write('Importation des commentaires pour la news "'.$newsSlug.'"');
            $news = $phpBoostNews[$newsSlug];

            // On vérifie que la news existe dans comments_topic
            $topic_id = $this->createCommentsTopic($phpBoostAccess, $news);

            foreach($comments as $comment) {
                // On ajoute chaque commentaire
                $this->addComment($phpBoostAccess, $topic_id, $comment);
                $io->write('.');
            }

            $io->writeln();
            $io->writeln('Mise à jour du nombre de commentaire...');
            $this->updateCommentsCount($phpBoostAccess, $topic_id);
        }
    }

    public function createCommentsTopic(PHPBoostAccess $PHPBoostAccess, stdClass $news) {
        $count = $PHPBoostAccess->getSql()->prepare('
            SELECT id_topic
            FROM ' . $PHPBoostAccess->getPrefix(). 'comments_topic
            WHERE module_id="news" AND id_in_module = :id
        ');

        $count->execute(array('id' => $news->id));

        $nb = $count->rowCount();

        if($nb < 1) {
            $path = '/news/?url=/' . $news->category_id . '-' . $news->category_slug . '/' . $news->id . '-' . $news->rewrited_name . '/';
            // Creation du comments topic
            $insert = $PHPBoostAccess->getSql()->prepare('
                INSERT INTO ' . $PHPBoostAccess->getPrefix(). 'comments_topic(module_id, topic_identifier, id_in_module, is_locked, number_comments, path)
                VALUES (:module_id, :topic_identifier, :id_in_module, :is_locked, :number_comments, :path)
            ');

            $insert->execute(array(
                'module_id' => 'news',
                'topic_identifier' => 'default',
                'id_in_module' => $news->id,
                'is_locked' => 0,
                'number_comments' => 0,
                'path' => $path
            ));

            return $PHPBoostAccess->getSql()->lastInsertId();
        } else {
            return $count->fetchObject()->id_topic;
        }
    }

    public function addComment(PHPBoostAccess $PHPBoostAccess, $topic_id, stdClass $comment) {
        $users = $PHPBoostAccess->getAllUsers();

        $userId = -1;
        if($comment->user_id != 0 && array_key_exists($comment->comment_author, $users)) {
            $userId = $users[$comment->comment_author]->user_id;
        }

        $insert = $PHPBoostAccess->getSql()->prepare('
            INSERT INTO ' . $PHPBoostAccess->getPrefix(). 'comments(id_topic, message, user_id, pseudo, user_ip, note, timestamp)
            VALUES (:id_topic, :message, :user_id, :pseudo, :user_ip, :note, :timestamp)
        ');

        $insert->execute(array(
            'id_topic' => $topic_id,
            'message' => $comment->comment_content,
            'user_id' => $userId,
            'pseudo' => $comment->comment_author,
            'user_ip' => $comment->comment_author_IP,
            'note' => 0,
            'timestamp' => (new DateTime($comment->comment_date))->getTimestamp()
        ));
    }

    public function updateCommentsCount(PHPBoostAccess $PHPBoostAccess, $topic_id) {
        $update = $PHPBoostAccess->getSql()->prepare('
            UPDATE ' . $PHPBoostAccess->getPrefix(). 'comments_topic ct
            SET number_comments = (SELECT COUNT(*) FROM ' . $PHPBoostAccess->getPrefix(). 'comments c WHERE c.id_topic = ct.id_topic)
            WHERE id_topic = :id_topic
        ');

        $update->execute(array(
            'id_topic' => $topic_id
        ));
    }
}