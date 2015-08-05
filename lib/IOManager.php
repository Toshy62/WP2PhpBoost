<?php
interface IOManager {
    public function write($str = '');
    public function writeln($str = '');
    public function read($id);
}