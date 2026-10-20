<?php

$df = tibble(x: [1, 2, 3]);
$doubled = $df->mutate(['x' => col('x')->mul(2)]);

echo json_encode($df->x), ' ', json_encode($doubled->x), "\n";
