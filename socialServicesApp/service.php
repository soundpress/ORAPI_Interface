<?php
require_once __DIR__ . '/include/loader.php';

$v = View::engine();
$id = $_GET['id'];
if (!$id)
	$v->back();
$m = Model::engine();
$data = $m->getService($id);
$data = DataMapper::serviceCard($data);
$v->servicePage($id, $data);