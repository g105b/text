-- The client table represents a browser that has connected to the application.
-- This allows us to render the cursor positions of everyone who is currently
-- connected, identify future connections, and potentially remove the entries
-- made by individual clients if necessary.
create table client
(
	id integer not null
		constraint client_pk
			primary key autoincrement,
	ip text not null,
	port integer not null,
	t integer not null,
	x integer,
	y integer
);

-- The IP and port are the identifiers used when new data is received by the
-- server, so index them for fast retrieval of the ID.
create index client_ip_index
	on client (ip);
create index client_port_index
	on client (port);

-- The text table records every change a client makes over time. Recording the
-- time allows a timelapse to be generated. An important thing to know is that
-- this table has a composite primary key of t, x and y rather than an auto
-- incremented key.
create table text
(
	t integer not null,
	x integer not null,
	y integer not null,
	c integer,
	client integer not null
		constraint text_client_id_fk
			references client,
	constraint text_pk
		primary key (t, x, y)
);

-- There is an additional index added here which allows faster selects based on
-- the latest text entered.
create index text_t_index
	on text (t desc);
