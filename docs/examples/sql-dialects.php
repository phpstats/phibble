<?php

use Phibble\Sql\Compiler;
use Phibble\Sql\MysqlDialect;
use Phibble\Sql\PostgresDialect;
use Phibble\Sql\SqliteDialect;

$expressions = [
    'mean, NA aware' => mean(col('body_mass_g')),
    'ratio' => col('bill_length_mm')->div(col('bill_depth_mm')),
    'contains' => str_detect(col('island'), 'Dream'),
    'in, with NA' => col('sex')->isIn(['female', null]),
];

$dialects = [
    new SqliteDialect(),
    new PostgresDialect(),
    new MysqlDialect(),
];

foreach ($dialects as $dialect) {
    echo "-- {$dialect->name()}\n";
    $compiler = new Compiler($dialect, mode: 'summary');
    foreach ($expressions as $label => $expr) {
        $sql = $compiler->compile($expr)->sql;
        echo \str_pad($label, 16), $sql, "\n";
    }
    echo "\n";
}

$window = new Compiler(
    new PostgresDialect(),
    groups: ['species'],
    order: [[col('year'), false]],
);
echo $window->compile(cumsum(col('body_mass_g')))->sql, "\n";
