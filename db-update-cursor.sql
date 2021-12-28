update client
set
	x = :x,
	y = :y,
	t = :timestamp

where
	id = :id;
