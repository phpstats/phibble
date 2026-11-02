```php
use Phibble\Sql\SqlTable;

use function Phibble\tbl;
use function Phibble\Expr\{col, desc, max, mean, n, sum};

$weather = tbl($pdo, 'weather');

$airports = $weather |>
    (fn (SqlTable $t) => $t->filter(col('temp')->isNa()->not())) |>
    (fn (SqlTable $t) => $t->groupBy(['origin'])) |>
    (fn (SqlTable $t) => $t->summarize([
        'hours' => n(),
        'temp' => mean(col('temp')),
        'precip' => sum(col('precip')),
        'gust' => max(col('wind_gust'), na_rm: true),
    ])) |>
    (fn (SqlTable $t) => $t->arrange([desc(col('precip'))]));

echo $airports->showQuery(), "\n\n";
echo $airports->collect(), "\n";
```
