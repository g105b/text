const socketUrl = `ws://${location.hostname}:10500/ws.php`;
const socket = new WebSocket(socketUrl);

const canvas = document.querySelector("canvas");
const ctx = canvas.getContext("2d");
const debounceTimeouts = {};
const mobileInput = document.getElementById("mobile-input");

const cursor = {
	x: null,
	y: null,
	startX: null,
};
const camera = {
	x: 0,
	y: 0,
};
const mouse = {
	x: 0,
	y: 0,
	down: false
};
const grid = {
	width: 16,
	height: 24,
};
const data = {
	"0": {
		"0": "h",
		"1": "e",
		"2": "l",
		"3": "l",
		"4": "o",
	},
	"1": {
		"0": "w",
		"1": "o",
		"2": "r",
		"3": "l",
		"4": "d",
	}
};

canvas.addEventListener("pointerdown", mouseDown);
canvas.addEventListener("pointerup", mouseUp);
canvas.addEventListener("pointermove", mouseMove);
window.addEventListener("resize", () => debounce(resizeCanvas));
window.addEventListener("keydown", keyDown);
if(navigator.maxTouchPoints > 0) {
	document.body.classList.add("mobile");
}

function debounce(callback) {
	if(debounceTimeouts[callback.name]) {
		return;
	}
	else {
		callback();
	}

	debounceTimeouts[callback.name] = setTimeout(callback, 100);
	setTimeout(function() {
		delete debounceTimeouts[callback.name];
	}, 100);
}

function resizeCanvas() {
	canvas.width = window.innerWidth;
	canvas.height = window.innerHeight;
	if(document.body.classList.contains("mobile")) {
		mobileInput.focus();
	}
	else {
	}
	ctx.font = "20px monospace";
}

function mouseDown(e) {
	if(!mouse.down) {
		mouse.down = {};
	}

	mouse.down.x = e.clientX;
	mouse.down.y = e.clientY;
	mouse.x = mouse.down.x;
	mouse.y = mouse.down.y;
}

function mouseUp(e) {
	if(Math.abs(mouse.x - mouse.down.x) < grid.width / 2 && Math.abs(mouse.y - mouse.down.y) < grid.height / 2) {
		click(
			Math.floor( mouse.x / grid.width) + Math.ceil(camera.x / grid.width),
			Math.ceil( mouse.y / grid.height) + Math.ceil(camera.y / grid.height)
		);
	}
	mouse.down = false;
	if(document.body.classList.contains("mobile")) {
		mobileInput.focus();
	}
}

function mouseMove(e) {
	if(mouse.down) {
		console.log(mouse.x - e.clientX);
		let dragVector = {
			x: mouse.x - e.clientX,
			y: mouse.y - e.clientY,
		};
		camera.x += dragVector.x;
		camera.y += dragVector.y;
	}

	mouse.x = e.clientX;
	mouse.y = e.clientY;
}

function keyDown(e) {
	if(e.ctrlKey) {
		return;
	}

	e.preventDefault();

	if(e.key.length === 1 && e.key.match(/[a-z0-9!"£$€ß¢%^&*()\-=_+\[\]{};:'@#~,<.>/?\\|`¬ ]/i)) {
		setChar(cursor.x, cursor.y, e.key[0]);
		cursor.x++;
	}
	else {
		if(e.key === "Enter") {
			cursor.y++;
			cursor.x = cursor.startX;
		}
		else if(e.key === "Backspace") {
			if(cursor.x > cursor.startX) {
				cursor.x--;
				setChar(cursor.x, cursor.y, null);
			}
		}
		else if(e.key === "ArrowLeft") {
			cursor.x --;
		}
		else if(e.key === "ArrowRight") {
			cursor.x ++;
		}
		else if(e.key === "ArrowUp") {
			cursor.y --;
		}
		else if(e.key === "ArrowDown") {
			cursor.y ++;
		}
		else if(e.key === "Escape") {
			cursor.x = cursor.y = null;
		}
		else {
			// console.log(e);
		}
	}

	constrainView(cursor.x, cursor.y);
	send(cursor.x, cursor.y);
}

function constrainView(x, y) {
	x *= grid.width;
	y *= grid.height;

	while(x - camera.x < 0) {
		camera.x -= grid.width * 10;
	}
	while(x - camera.x > canvas.width) {
		camera.x += grid.width * 10;
	}
	while(y - camera.y < 0) {
		camera.y -= grid.height * 10;
	}
	while(y - camera.y > canvas.height) {
		camera.y += grid.height * 10;
	}
}

function setChar(x, y, c) {
	if(data[y] === undefined) {
		data[y] = {};
	}

	if(c === null) {
		delete data[y][x];
		if(data[y].length === 0) {
			// console.log(data[y]);
			delete data[y];
		}
	}
	else {
		data[y][x] = c;
	}
	send(x, y, c);
}

function click(x, y) {
	select(x, y);
}

function select(x, y) {
	cursor.x = x;
	cursor.y = y;
	cursor.startX = x;
	send(x, y);
}

function loop(dt) {
	update(dt);
	draw(dt);
	requestAnimationFrame(loop);
}

function update(dt) {

}

function draw() {
	ctx.clearRect(0, 0, canvas.width, canvas.height);
	drawSelection();
	drawData();
	drawMouse();
}

function drawData() {
	const border = -20;

	for(let y in data) {
		for(let x in data[y]) {
			let screenX = Math.floor(((x * grid.width) - camera.x) / grid.width) * grid.width;
			let screenY = -4 + Math.floor(((y * grid.height) - camera.y) / grid.height) * grid.height;

			if(screenX < border || screenX > canvas.width - border || screenY < border || screenY > canvas.height - border) {
				continue;
			}

			if(data[y][x] === null) {
				continue;
			}

			ctx.fillStyle = "black";
			if(cursor.x === parseInt(x) && cursor.y === parseInt(y)) {
				ctx.fillStyle = "white";
			}
			ctx.fillText(data[y][x], screenX, screenY);
		}
	}
}

function drawMouse() {
	let cellX = (Math.floor(mouse.x / grid.width) * grid.width);
	let cellY = (Math.floor(mouse.y / grid.height) * grid.height);
	ctx.strokeRect(cellX, cellY, grid.width, grid.height);
}

function drawSelection() {
	if(cursor.x === null || cursor.y === null) {
		return;
	}

	let screenX = (cursor.x * grid.width) - (Math.ceil(camera.x / grid.width) * grid.width);
	let screenY = (cursor.y * grid.height) - ((Math.ceil(camera.y / grid.height) + 1) * grid.height);

	ctx.fillStyle = "black";
	ctx.fillRect(screenX, screenY, grid.width, grid.height);
}

function send(x, y, c) {
	let obj = {
		x: parseInt(x),
		y: parseInt(y),
	};
	if(c !== undefined) {
		obj.c = c;
	}
	socket.send(JSON.stringify(obj));
}

resizeCanvas();
camera.x -= Math.floor(canvas.width / 2);
camera.y -= Math.floor(canvas.height / 2);

socket.onmessage = function(e) {
	let obj = JSON.parse(e.data);
	for(let i in obj.data) {
		if(!obj.data.hasOwnProperty(i)) {
			continue;
		}

		let y = i.toString();

		for(let j in obj.data[i]) {
			let x = j.toString();

			if(!data[y]) {
				data[y] = {};
			}
			data[y][x] = obj.data[i][j];
		}
	}
};

loop(0);
