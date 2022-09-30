An experiment with WebSockets and the human condition.
======================================================

I wanted to learn how to use WebSockets in pure PHP and JavaScript, so I came up with the simplest project to build that would only take a few hours to put together.

The concept: a WebSocket server that communicates state between all connected clients, and persists state to a SQLite database. The client consists of an infinitely scrollable 2D grid that can have text typed into it.

The human condition: I am fully aware that this will be abused, because I wanted to keep the communication channels completely anonymous. It would be impossible to censor input completely anyway, so let's just see what mess this will produce. Wait, it's not mess - it's **art**.

~~You can view the live project at: https://www.walloftext.art~~ I had to take the service offline due to abuse, but I'll bring it back one rainy day.

Getting set up.
---------------

You can run this project locally if you have PHP 8 installed with the `sqlite` extension. Nothing else is required to run - I've purposefully ignored any build tools, even Composer. All the files you need are in the repository.

To serve the project in a web browser, use any server you are familiar with or just run `php -S 0.0.0.0:8080` to serve the directory using PHP's inbuilt server - then navigate to http://localhost:8080 in your browser.

You'll be able to click around and type into the grid, but until the WebSocket server is running, nothing will persist. To run the WebSocket server, while the web server is still running, run `php ws.php`. This will run a new server, listening on port 10500.

The database runs using SQLite, so instead of having a separate database server running, the database stores itself within the `text.db` file. This file will be created automatically, and if there are no "tables" contained within it, the start of `ws.php` will run `db.sql` which contains the `create table` commands.

If something isn't working for you, [open an issue](https://github.com/g105b/text/issues), and I'll be happy to help.

***

How WebSockets work.
--------------------

There are three main components of a WebSocket connection, from a PHP developer's perspective.

1) The client - some JavaScript that makes a connection to an endpoint, like `ws://example.com/ws.php`, which can send and receive text messages once connected.
2) The endpoint - the PHP script that the client connects to. Note: the connection is just a plain HTTP connection at first, like any other GET request, but the script needs to send some headers to "upgrade" the connection to a persistent WebSocket connection that will be trusted by the client.
3) The loop - once the client is connected to the endpoint, the script needs to stay running forever in an infinite loop. Within the loop, new messages can be read by PHP, or messages can be sent to individual client connections. This bit is probably the most different from typical PHP development because of the long-running script, but there's no reason PHP can't do this kind of task really well.

### The client.

The client starts life as `index.html`, viewed in the browser like any other webpage. There's not too much to it, just the basic `<head>` elements and a single `<canvas>` where the grid is to be drawn.

`script.js` is loaded in the head with the `defer` attribute that is the modern day replacement of the document.ready function (deferred scripts will load as soon as possible, but only execute when the document is ready).

At the top of `script.js`, I have defined the `WebSocket` object, then any variables that will be used for the drawing and interaction with the 2D grid. In fact, the majority of the JavaScript in this project is just to draw the text in the canvas, and control scrolling around, etc. The only WebSocket-specific code is right at the bottom of the script: `socket.onmessage` is a function that updates the `data` object with the text coordinates as they are sent from the server.

### The endpoint.

`ws.php` is a simple looking script. It's the bootstrap of the server-side code. It constructs any objects that are required for this project to work, then injects them where needed.

PDO is used to persist data to the `text.db` file. Then a `Server` object is created, where most of the work will happen, along with a `Canvas` for representing the grid of text and `State` for persisting the Canvas to the Database.

The last step of the endpoint is the initiate the infinite loop. Once the script enters the loop, it will never exit until the script is terminated. Three functions are passed in which control the behaviour of a new client connection, new client data, and getting the latest data from the current State.

The actual initialisation of the WebSocket connection is done within the `Server` object, which is also where the loop lives.

### The loop.

`server.php` initialises the WebSocket connection and handles the two-way communication, along with keeping track of all the connected clients.

In the constructor, a new `Socket` is created that listens on port 10500.

The loop function is simply an infinite loop (`while(true)`) that constantly calls the `tick` function, after breathing for a few milliseconds.

Within the `tick` function, any new clients are handled first. When there's a new client, the incoming connection looks like a standard HTTP request. To satisfy the client connection, a particular type of response needs to be sent back (explained in more detail within the code comments).

Successfully connected clients are stored in the `$clientSocketArray` variable, which makes it easy to loop over all clients to check for new messages. Messages from WebSocket clients are "masked" as a security measure to help servers identify real client messages.

When unmasking a message, it's important to know that more than one message from the client can be sent within the same packet of data (this is [Nagle's algorithm](https://en.wikipedia.org/wiki/Nagle%27s_algorithm)). Because the loop has a 100ms imposed delay (to keep my CPU cool), and because every character typed is sent in its own message, fast typists will notice in the browser's network inspector that more than one message can be sent within the same frame of data.

This is probably where I spent the most time debugging this project, because for ages I didn't realise that more than one message can be sent within the same frame, and code snippets I had seen online all failed to mention it. Essentially, the WebSocket protocol defines that the length of the message should be sent in byte 1, but this may be less than the total number of bytes in the message. This is all done within the `unmask` function, which will continue to call itself recursively until the entire frame of data is processed, returning an array of all unmasked strings.

### Database.

Every character change is recorded to the database. This is probably really inefficient, but this project was intended to learn WebSockets, not produce optimal SQL.

There are only two tables, `client` and `text`, and this is all persisted into a single `text.db` file using SQLite. The client table is intended to keep track of the client connections, and also allows individual client's changes to be removed (in case the inevitable graffiti is too much). The text table stores the `x` and `y` location of every `c` character at `t` timestamp for each `client` ID. This data means it's possible to replay the entire canvas, character by character. This could be fun.

Final thoughts.
---------------

In terms of optimisations, only the biggest wins have been implemented. For instance, the server keeps track of the timestamp of the updates it hands out to clients, so only new data is sent across the wire as it is made. However, clients receive all data on the grid as soon as they connect. I know this is only text data, but seeing as clients can only see a small section of the grid at any one time, this is probably the best place to look for future optimisations (pull requests welcome). 

On the client-side, only the characters that are in view are drawn to screen. It would be nice to improve things so that clients only know about the characters directly around them, but I've spent enough time on this already, so that can be for another day (never).

Sponsor me?
-----------

If you found this repository helpful, [please consider sponsoring me on Github Sponsors](https://github.com/sponsors/g105b). It would mean so much to me!
