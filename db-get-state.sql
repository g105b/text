select
	max(t) as t,
	x,
	y,
	c
from
	text

where
	(:timestamp is null or t > :timestamp)

group by
	x, y
