<?php
class Server {
	/**
	 * What's the significance of this long string? It's just what was
	 * decided when the WebSocket protocol was defined. The browser will
	 * encode its transmissions with this string, as a safety measure to
	 * check that the server is a real WebSocket server.
	 * Read the spec here: https://datatracker.ietf.org/doc/html/rfc6455
	 */
	const HANDSHAKE = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

	private Socket $socket;
	/** @var array<Socket> */
	private array $clientSocketArray;
	/** @var float Number of seconds since the server was started  */
	private float $totalTime;
	private int $timestamp;

	public function __construct(
		private string $bindAddress = "0.0.0.0",
		private int $port = 10500,
		private float $frameDelay = 0.1
	) {
		$this->socket = socket_create(
			AF_INET,
			SOCK_STREAM,
			SOL_TCP
		);

		socket_set_option(
			$this->socket,
			SOL_SOCKET,
			SO_REUSEADDR,
			1
		);
		socket_bind($this->socket, $this->bindAddress, $this->port);
		socket_listen($this->socket);

		$this->clientSocketArray = [$this->socket];
		$this->totalTime = 0;
	}

	/**
	 * This function will never return. It acts as an infinite loop that
	 * constantly calls the tick function, pausing for 100ms to allow the
	 * server to catch its breath. Data sent within this pause will still
	 * be received, as the socket will buffer incoming messages.
	 * The two parameters allow a callback to be executed whenever there's
	 * a new connection or new data available.
	 */
	public function loop(
		?callable $onConnect = null,
		?callable $onData = null,
		?callable $getData = null,
	):void {
		// TODO: Add a mechanism for stopping the loop gracefully.
		$lastTime = null;
		while(true) {
			$deltaTime = is_null($lastTime)
				? 0
				: microtime(true) - $lastTime;
			$this->totalTime += $deltaTime;
			$lastTime = microtime(true);
			$this->tick($onConnect, $onData, $getData);
			usleep($this->frameDelay * 1_000_000);
		}
	}

	public function send($client, object|string $msg):void {
		$msg = $this->mask($msg);
		$length = strlen($msg);
		@socket_write($client, $msg, $length);
	}

	/**
	 * This is where the main work is done. Any incoming data will be
	 * processed here - there may be many messages to process, there may be
	 * no data to process.
	 */
	private function tick(
		?callable $onConnect,
		?callable $onData,
		?callable $getData,
	):void {
// First make a copy of the client list, so we can manipulate it without losing
// any references to connected clients.
		$readClientArray = $this->clientSocketArray;
		$writeClientArray = $exceptClientArray = null;
// Check to see if there is any new data on any of the read clients.
		socket_select(
			$readClientArray,
			$writeClientArray,
			$exceptClientArray,
			0
		);

		if(in_array($this->socket, $readClientArray)) {
// If the server's socket is in the read client array, it represents a new
// client connecting, which needs to be greeted with a handshake.
			echo "New socket incoming...";
// Accept the new client and add it to the client list for the next tick.
			$newSocket = socket_accept($this->socket);
			socket_getpeername($newSocket, $address, $port);
			array_push($this->clientSocketArray, $newSocket);

// At the moment, this connection is a plain HTTP connection from a web browser.
// The handshake is the procedure that upgrades the HTTP connection to a
// WebSocket connection.
			$headers = socket_read($newSocket, 1024);
			preg_match(
				"/^Host: (?P<HOST>[^:]+)/mi",
				$headers,
				$matches
			);
			$this->doHandshake(
				$headers,
				$newSocket,
				$matches["HOST"],
				$this->port
			);

			if($onConnect) {
				call_user_func($onConnect, $newSocket);
			}

			$newSocketIndex = array_search($this->socket, $readClientArray);
			unset($readClientArray[$newSocketIndex]);
			echo "... $address:$port connected!", PHP_EOL;
		}

		foreach($readClientArray as $client) {
			socket_getpeername($client, $address, $port);

			while(socket_recv($client, $socketData, 1024, 0) >= 1) {
				foreach($this->unmask($socketData) as $socketMessage) {
					echo "$address:$port <<< $socketMessage >>>", PHP_EOL;
					if($onData) {
						call_user_func($onData, $client, $socketMessage);
					}
				}
				break 2;
			}

			$socketData = @socket_read($client, 1024, PHP_NORMAL_READ);
			if($socketData === false) {
				$newSocketIndex = array_search($client, $this->clientSocketArray);
				unset($this->clientSocketArray[$newSocketIndex]);
			}
		}

		$data = null;
		if($this->clientSocketArray && $getData) {
			$data = call_user_func(
				$getData,
				$this->timestamp ?? null
			);
		}

		$this->timestamp = (int)(microtime(true) * 100);

		if($data) {
			foreach($this->clientSocketArray as $client) {
				$this->send($client, (object)[
					"type" => "update",
					"data" => $data,
				]);
			}
		}
	}

	private function mask(string|object $socketData):string {
		if(is_object($socketData)) {
			$socketData = json_encode($socketData);
		}

		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($socketData);

		if($length <= 125) {
			$header = pack("CC", $b1, $length);
		}
		elseif($length < 65536) {
			$header = pack("CCn", $b1, 126, $length);
		}
		else {
			$header = pack("CCN", $b1, 127, $length);
		}

		return $header . $socketData;
	}

	/** @return array<string> */
	private function unmask(string $maskedPayload):array {
		$length = ord($maskedPayload[1]) & 127;
		$maskLength = 4;

		if($length == 126) {
			$maskOffset = 4;
		}
		elseif($length == 127) {
			$maskOffset = 10;
		}
		else {
			$maskOffset = 2;
		}

		$masks = substr($maskedPayload, $maskOffset, $maskLength);
		$data = substr($maskedPayload, $maskOffset + $maskLength, $length);
		$overflow = substr($maskedPayload, $maskOffset + $maskLength + $length);

		$unmaskedArray = [""];

		for ($i = 0; $i < $length; $i++) {
			$unmaskedArray[0] .= $data[$i] ^ $masks[$i%4];
		}

		if($overflow) {
			array_push(
				$unmaskedArray,
				...$this->unmask($overflow)
			);
		}

		return $unmaskedArray;
	}

	private function doHandshake(
		string $rawHeaders,
		Socket $client,
		string $address,
		int $port
	):void {
		$headerArray = [];
		$lines = preg_split("/\r\n/", $rawHeaders);
		foreach($lines as $line) {
			$line = rtrim($line);
			if(preg_match('/\A(?P<NAME>\S+): (?P<VALUE>.*)\z/', $line, $matches)) {
				$headerArray[$matches["NAME"]] = $matches["VALUE"];
			}
		}

		$secKey = $headerArray["Sec-WebSocket-Key"];
		$secAccept = base64_encode(
			pack(
				"H*",
				sha1($secKey . self::HANDSHAKE)
			)
		);
		$buffer  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
			"Upgrade: websocket\r\n" .
			"Connection: Upgrade\r\n" .
			"WebSocket-Origin: $address\r\n" .
			"WebSocket-Location: ws://$address:$port/ws.php\r\n".
			"Sec-WebSocket-Accept:$secAccept\r\n\r\n";

		socket_write(
			$client,
			$buffer,
			strlen($buffer)
		);
	}
}
