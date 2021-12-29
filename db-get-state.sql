select
	max(t) as t,
	text.x,
	text.y,
	c
from
	text

where
	(:timestamp is null or t > :timestamp)

group by
	x, y
