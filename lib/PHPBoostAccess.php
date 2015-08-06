<?php
/**
 * Class PHPBoostInterface
 */
class PHPBoostAccess {
    /**
     * @var string
     */
    private $phpBoostPath;

    /**
     * @var PDO
     */
    private $sqlAccess;

    /**
     * @var IOManager
     */
    private $io;

    /**
     * @var string
     */
    private $prefix;

    public function __construct($phpBoostPath, IOManager $io) {
        $this->phpBoostPath = $phpBoostPath;
        $this->io = $io;
        if(!defined('PATH_TO_ROOT')) define('PATH_TO_ROOT', $phpBoostPath);
        require $phpBoostPath . 'kernel/db/config.php';
        try {
            $this->sqlAccess = new PDO('mysql:host=' . $db_connection_data['host'] . ';dbname=' . $db_connection_data['database'], $db_connection_data['login'], $db_connection_data['password']);
            $this->sqlAccess->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            $this->io->writeln($e->getMessage());
            exit();
        }
        $this->prefix = PREFIX;
    }

    public function getAllUsers() {
        static $users;

        if(is_null($users)) {
            $users = array();
            $result = $this->sqlAccess->query('SELECT * FROM ' . $this->getPrefix() . 'member');
            while($user = $result->fetch(PDO::FETCH_OBJ)) {
                $users[$user->login] = $user;
            }
        }
        
        return $users;
    }

    public function getAllPosts() {
        static $posts;

        if(is_null($posts)) {
            $posts = array();
            $result = $this->sqlAccess->query('SELECT * FROM ' . $this->getPrefix() . 'news');
            while($post = $result->fetch(PDO::FETCH_OBJ)) {
                $posts[$post->rewrited_name] = $post;
            }
        }

        return $posts;
    }

    public function getAllNewsCats() {
        $cats = array();
        $result = $this->sqlAccess->query('SELECT * FROM ' . $this->getPrefix() . 'news_cats');
        while($cat = $result->fetch(PDO::FETCH_OBJ)) {
            $cats[$cat->rewrited_name] = $cat;
        }
        return $cats;
    }

    /**
     * Recherche/Crée un tag et retourne son ID
     * @param $tag
     * @param $rewrited_name
     * @return int
     */
    public function createTagIfNotExist($tag, $rewrited_name) {
        // Verification si le tag existe dans la table keywords
        $query = $this->sqlAccess->prepare('SELECT id FROM ' . $this->getPrefix() . 'keywords WHERE rewrited_name = ?');
        $query->execute(array($rewrited_name));
        $res = $query->fetch(PDO::FETCH_OBJ);
        if($query->rowCount() < 1) {
            // Si le tag n'existe pas création
            $insert = $this->sqlAccess->prepare('INSERT INTO ' . $this->getPrefix() . 'keywords(name,rewrited_name) VALUES(:name,:rewrited_name)');
            $insert->execute(array(
                'name' => $tag,
                'rewrited_name' => $rewrited_name
            ));
            return $this->sqlAccess->lastInsertId();
        }

        return $res->id;
    }

    public function getPrefix() {
        return $this->prefix;
    }

    /**
     * @return PDO
     */
    public function getSql() {
        return $this->sqlAccess;
    }

    public function getPath() {
        return $this->phpBoostPath;
    }
}