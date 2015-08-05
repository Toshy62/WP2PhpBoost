<?php
class IOCliManager implements IOManager {
    public function write($str = '') {
        echo $str;
    }

    public function writeln($str = '') {
        echo $str . PHP_EOL;
    }

    public function read($id) {
        return trim(fgets(STDIN));
    }

}