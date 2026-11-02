```php
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
```

```{.text .output}
{"type":"call","fn":"lag","args":[{"type":"col","name":"body_mass_g"}],"named":{"n":{"type":"lit","value":2},"default":{"type":"lit","value":0},"order_by":{"type":"col","name":"year"}}}
bool(true)
```
