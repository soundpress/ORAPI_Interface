<?php
require_once __DIR__ . '/include/loader.php';

$catData = json_decode(file_get_contents(__DIR__ . '/resources/taxonomyCatalog.json'), true);
$v = View::engine();
$v->searchPage($catData);