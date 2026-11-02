```php
use function Phibble\tbl;
use function Phibble\Expr\{col, desc, max, mean, n, sum};

$weather = tbl($pdo, 'weather');

$airports = $weather
    ->filter(col('temp')->isNa()->not())
    ->groupBy(['origin'])
    ->summarize([
        'hours' => n(),
        'temp' => mean(col('temp')),
        'precip' => sum(col('precip')),
        'gust' => max(col('wind_gust'), na_rm: true),
    ])
    ->arrange([desc(col('precip'))]);

echo $airports->showQuery(), "\n\n";
echo $airports->collect(), "\n";
```
