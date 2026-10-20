<?php

$df = tibble(x: [1, 2, 3], y: ['a', 'b', 'c']);

var_dump($df->x === $df['x']);
echo json_encode($df->column('y')), "\n";

$row = $df->row(1);
echo $row->x, ' ', $row['y'], "\n";

foreach ($df as $i => $row) {
    echo "{$i}: {$row->y}\n";
}

try {
    $df->z;
} catch (Phibble\Exception\ColumnNotFound $e) {
    echo $e->getMessage(), "\n";
}
