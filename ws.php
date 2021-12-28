<?php
chdir(__DIR__);
require("server.php");
require("state.php");
require("canvas.php");

$db = new PDO("sqlite:text.db");
$null = null;

$ws = new Server("0.0.0.0", 10500);

$st = microtime(true);
$lt = null;
$t = 0;

$sendFunction = fn(Socket $client, object|string $data) => $ws->send($client, $data);

$canvas = new Canvas();
$state = new State($db, $canvas);

$ws->loop(
	onConnect: fn(Socket $socket)
		=> $state->clientConnection($socket, $canvas, $sendFunction),
	onData: fn(Socket $socket, string $data)
		=> $state->clientData($socket, $data),
	getData: fn(?int $timestamp = null) => $state->getData($timestamp),
);
