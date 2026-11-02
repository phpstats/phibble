<?php

$expr = lag(
    col('body_mass_g'),
    n: 2,
    default: 0,
    order_by: col('year'),
);
$json = json_encode($expr->toArray());

echo $json, "\n";

$back = Phibble\Expr\Expr::fromArray(json_decode($json, true));
var_dump($back == $expr);
