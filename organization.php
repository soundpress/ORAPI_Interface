<?php
require_once __DIR__ . '/include/loader.php';

$v = View::engine();
$m = Model::engine();
$data = $m->getServices($_GET);
/*
?><pre><?php
var_dump($data);die();	*/
list($markers, $mapCenter) = DataMapper::markers($data['items']);
file_put_contents(__DIR__ . '/resources/markers_org.txt', $markers);
if (isset($data['message']) && $data['message'] == 'Rate limit exceeded')
	$v->alertPage($_GET, 'API rate limit exceeded');
else
	$v->orgPage($_GET, $data, $mapCenter);
