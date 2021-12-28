<?php
class State {
	/**
	 * @var array<string, int> A cache of clients, so we do not need to
	 * look up the ID of clients that have recently connected. The array key
	 * is the IP:port of the client, the array value is the database ID.
	 */
	private array $clientIdCache;

	public function __construct(
		private PDO $db,
		private Canvas $canvas,
	) {
		$this->clientIdCache = [];
		$this->getData();
	}

	public function clientConnection(
		Socket $socket,
		Canvas $canvas,
		callable $sendFunction,
	):void {
		socket_getpeername($socket, $address, $port);
		$newId = $this->query("new-client", [
			"ip" => $address,
			"port" => $port,
			"timestamp" => (int)(microtime(true) * 100),
		]);
		$this->clientIdCache["$address:$port"] = $newId;
		call_user_func($sendFunction, $socket, (object)[
			"type" => "update",
			"data" => $canvas->getData(),
		]);
	}

	public function getData(?int $timestamp = null):array {
		$params = ["timestamp" => $timestamp];
		foreach($this->query("get-state", $params) as $row) {
			$this->canvas->setData(
				$row["x"],
				$row["y"],
				$row["c"]
			);
		}

		return $this->canvas->getData((bool)$timestamp);
	}

	public function clientData(Socket $socket, string $data):void {
		$obj = json_decode($data, true);
		socket_getpeername($socket, $address, $port);
		$obj["id"] = $this->clientIdCache["$address:$port"];
		$obj["timestamp"] = (int)(microtime(true) * 100);

		if(array_key_exists("c", $obj)) {
			$this->query("set-text", $obj);
		}
		else {
			$this->query("update-cursor", $obj);
		}
	}

	/**
	 * @param array<string, int|string> $data
	 * @return int|array<string, mixed>
	 */
	private function query(string $name, array $data = []):int|array {
		$sql = file_get_contents("db-$name.sql");
		$stmt = $this->db->prepare($sql);
		$bindings = [];
		foreach($data as $key => $value) {
			$bindings[":" . $key] = $value;
		}
		try {
			$stmt->execute($bindings);
		}
		catch(PDOException $exception) {
			echo "Error " . $exception->getCode() . " " . $exception->getMessage(), PHP_EOL;
		}
		if($stmt->columnCount() > 0) {
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
		else {
			return $this->db->lastInsertId() ?: $stmt->rowCount();
		}
	}
}
