insert or replace into text (
	t,
	x,
	y,
	c,
	client
)
values (
	:timestamp,
	:x,
	:y,
	:c,
	:id
)
on conflict(t, x, y) do update
set
	c = :c,
	client = :id
