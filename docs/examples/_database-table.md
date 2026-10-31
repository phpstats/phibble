```php
<?php

require __DIR__.'/vendor/autoload.php';

use Phibble\Sql\SqlTable;

use function Phibble\tbl;
use function Phibble\Expr\{col, desc, n, sum};

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE orders (region TEXT, product TEXT, units INTEGER, price REAL)');
$pdo->exec("INSERT INTO orders VALUES
    ('east', 'lamp', 3, 24.0), ('east', 'desk', 1, 180.0), ('west', 'lamp', 5, 24.0),
    ('west', 'chair', 4, 65.0), ('north', 'desk', 2, 180.0), ('north', 'lamp', 1, 24.0),
    ('west', 'desk', 1, 180.0), ('east', 'chair', 0, 65.0)");

$orders = tbl($pdo, 'orders');

$revenue = $orders |>
    (fn (SqlTable $t) => $t->filter(col('units')->gt(0))) |>
    (fn (SqlTable $t) => $t->mutate(['revenue' => col('units')->mul(col('price'))])) |>
    (fn (SqlTable $t) => $t->groupBy(['region'])) |>
    (fn (SqlTable $t) => $t->summarize(['orders' => n(), 'revenue' => sum(col('revenue'))])) |>
    (fn (SqlTable $t) => $t->arrange([desc(col('revenue'))]));

echo $revenue->showQuery(), "\n\n";
echo $revenue->collect(), "\n";
```

```{.text .output}
SELECT "region", "orders", "revenue" FROM (SELECT "region", "orders", "revenue", ROW_NUMBER() OVER (ORDER BY ("region" IS NULL), "region") AS "__phibble_row" FROM (SELECT "region" AS "region", COUNT(*) AS "orders", (CASE WHEN COUNT(*) > COUNT("revenue") THEN NULL ELSE COALESCE(SUM("revenue"), 0) END) AS "revenue" FROM (SELECT "region", "product", "units", "price", ("units" * "price") AS "revenue", "__phibble_row" FROM (SELECT * FROM (SELECT *, rowid AS "__phibble_row" FROM "orders") AS q1 WHERE ("units" > ?)) AS q2) AS q3 GROUP BY "region") AS q4) AS q5 ORDER BY ("revenue" IS NULL), "revenue" DESC, ("__phibble_row" IS NULL), "__phibble_row"

# A tibble: 3 × 3
  region orders revenue
  <chr>   <int>   <dbl>
1 west        3     560
2 north       2     384
3 east        2     252
```
