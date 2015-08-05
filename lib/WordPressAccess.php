<?php
class WordPressAccess {
    /**
     * @var string
     */
    private $wpPath;

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

    public function __construct($wpPath, IOManager $io) {
        $this->wpPath = $wpPath;
        $this->io = $io;
        require_once $wpPath . 'wp-config.php';
        try {
            $this->sqlAccess = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
            $this->sqlAccess->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            $this->io->writeln($e->getMessage());
            exit();
        }
        $this->prefix = $table_prefix;
    }

    public function getAllUsers() {
        static $users;

        if(is_null($users)) {
            $users = array();
            $result = $this->sqlAccess->query('SELECT * FROM ' . $this->getPrefix() . 'users');
            while($user = $result->fetch(PDO::FETCH_OBJ)) {
                $users[$user->user_login] = $user;
            }
        }

        return $users;
    }

    public function getAllPosts($type = 'post') {
        static $posts;
        if(is_null($posts)) $posts = array();

        if(!array_key_exists($type, $posts)) {
            $posts[$type] = array();

            $result = $this->sqlAccess->prepare('
                SELECT wp.*, wp_thumbnail.meta_value AS thumbnail, u.user_login AS author_name,
                	(
                        SELECT slug
                        FROM '.$this->getPrefix().'term_relationships wtr
                        LEFT JOIN '.$this->getPrefix().'term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
                        LEFT JOIN '.$this->getPrefix().'terms wt ON wt.term_id = wtt.term_id
                        WHERE object_id = wp.ID LIMIT 1
                    ) AS term_slug
                FROM '.$this->getPrefix().'posts wp
                LEFT JOIN '.$this->getPrefix().'postmeta wpm_ori
                    ON wpm_ori.post_id = wp.ID AND wpm_ori.meta_key = "_thumbnail_id"
                LEFT JOIN '.$this->getPrefix().'postmeta wp_thumbnail
                    ON wpm_ori.meta_value = wp_thumbnail.post_id AND wp_thumbnail.meta_key = "_wp_attached_file"
                LEFT JOIN wordpress.wp_users u
                    ON u.ID = wp.post_author
                WHERE wp.post_status = "publish" AND wp.post_type = ?
            ');
            $result->execute(array($type));
            while($post = $result->fetch(PDO::FETCH_OBJ)) {
                $posts[$type][$post->post_name] = $post;
            }
        }

        return $posts[$type];
    }

    public function getMediaForPost($postId) {
        $result = $this->sqlAccess->prepare('
            SELECT guid AS url, meta_value AS path
            FROM '.$this->getPrefix().'posts wp
            INNER JOIN '.$this->getPrefix().'postmeta wpm ON wpm.post_id = wp.ID AND meta_key = "_wp_attached_file"
            WHERE post_type = "attachment" AND post_parent = ?;
        ');

        $result->execute(array($postId));

        return $result->fetchAll(PDO::FETCH_OBJ);
    }

    public function getAllTerms() {
        static $terms;

        if(is_null($terms)) {
            $terms = array();
            $result = $this->sqlAccess->query('
                SELECT t.*, tt.description, t_parent.slug AS parent_slug
                FROM ' . $this->getPrefix() . 'terms t
                LEFT JOIN ' . $this->getPrefix() . 'term_taxonomy tt ON t.term_id = tt.term_id
                LEFT JOIN ' . $this->getPrefix() . 'terms t_parent ON t_parent.term_id = tt.parent
                ORDER BY tt.parent ASC
            ');
            while($term = $result->fetch(PDO::FETCH_OBJ)) {
                $terms[$term->slug] = $term;
            }
        }

        return $terms;
    }

    public function getAllPages() {
        return $this->getAllPosts('page');
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
        return $this->wpPath;
    }
}