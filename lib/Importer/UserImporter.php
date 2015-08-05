<?php
class UserImporter extends Importer {
    public function getImporterName() {
        return 'User';
    }

    public function getImporterDescription() {
        return 'Importe les utilisateurs (sans mot de passe)';
    }

    public function getImporterVersion() {
        return 'dev';
    }

    public function import(IOManager $io, WordPressAccess $wordPressAccess, PHPBoostAccess $phpBoostAccess) {
        // Récupération de la liste des utilisateurs existants dans PHPBoost
        $phpBoostUsers = $phpBoostAccess->getAllUsers();

        // Récupération de la liste des utilisateurs existants dans Wordpress
        $wordPressUsers = $wordPressAccess->getAllUsers();

        // Parcours des différents utilisateurs WordPress
        foreach($wordPressUsers as $currentUser) {
            if(!array_key_exists($currentUser->user_login, $phpBoostUsers)) {
                // Si l'utilisateur n'existe pas
                $this->addUser($phpBoostAccess, $currentUser);
                $io->writeln('Info: Utilisateur ' . $currentUser->user_login . ' ajouté.');
            } else {
                // Si l'utilisateur existe
                $io->writeln('Erreur: L\'utilisateur ' . $currentUser->user_login . ' existe déjà.');
            }
        }
    }

    protected function addUser(PHPBoostAccess $pba, stdClass $wpUser) {
        $query = $pba->getSql()->prepare('INSERT INTO ' . $pba->getPrefix() . 'member(login, password, level, user_mail, user_show_mail, timestamp, last_connect) VALUES(:login, :password, :level, :user_mail, :user_show_mail, :timestamp, :last_connect)');

        $query->execute(array(
            'login' => $wpUser->user_login,
            'password' => '',
            'level' => '0',
            'user_mail' => substr($wpUser->user_email, 0, 50), // Le champs mail ne fait que 50 chez PHPBoost mais 100 chez Wordpress donc on raccourci)
            'user_show_mail' => 0,
            'timestamp' => (new DateTime($wpUser->user_registered))->getTimestamp(),
            'last_connect' => '0',
        ));
    }
}