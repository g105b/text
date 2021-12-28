<?php
class Canvas {
	const SECONDS_TO_ROUND = 10;

	/**
	 * @var array<int, array<int, string>> A multidimensional
	 * array representing the numbered Y rows and X columns. The nested
	 * string value is the character at that point.
	 */
	private array $data;
	/**
	 * @var array<int, array<int, string>> A cache of data that is unread,
	 * following the same format as the $data array.
	 */
	private array $newData;

	public function __construct() {
		$this->data = [];
		$this->newData = [];
	}

	public function setData(int $x, int $y, ?string $c):void {
		if(!isset($this->data[$y])) {
			$this->data[$y] = [];
		}
		$this->data[$y][$x] = $c;

		if(!isset($this->newData[$y])) {
			$this->newData[$y] = [];
		}
		$this->newData[$y][$x] = $c;
	}

	public function getData(bool $getNew = false):array {
		if($getNew) {
			$newData = $this->newData;
			$this->newData = [];
			return $newData;
		}

		return $this->data;
	}
}
