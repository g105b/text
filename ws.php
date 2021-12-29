<?php
chdir(__DIR__);
require("server.php");
require("state.php");
require("canvas.php");

$db = new PDO("sqlite:text.db");
if(empty($db->query("select `name` FROM `sqlite_schema` where `type` = 'table'")->fetchAll())) {
	$db->exec(file_get_contents("db.sql"));
	echo "Database created.", PHP_EOL;
}

$ws = new Server("0.0.0.0", 10500);
$sendFunction = fn(Socket $client, object|string $data)
	=> $ws->send($client, $data);

$canvas = new Canvas();
$state = new State($db, $canvas);

$ws->loop(
	onConnect: fn(Socket $socket)
		=> $state->clientConnection($socket, $canvas, $sendFunction),
	onData: fn(Socket $socket, string $data)
		=> $state->clientData($socket, $data),
	getData: fn(?int $timestamp = null) => $state->getData($timestamp),
);
