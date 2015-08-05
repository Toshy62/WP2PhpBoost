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
        define('PATH_TO_ROOT', $phpBoostPath);
        require_once $phpBoostPath . 'kernel/db/config.php';
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