```php
$weather = tbl($pdo, 'weather');

$out = $weather
    ->filter(col('month')->between(6, 8))
    ->groupBy(['origin', 'month'])
    ->summarize(['precip' => sum(col('precip'))])
    ->groupBy(['origin'])
    ->arrange(['origin', 'month'])
    ->mutate([
        'running' => cumsum(col('precip')),
        'of_wettest' => col('precip')
            ->div(max(col('precip'), na_rm: true)),
    ]);

echo $out->showQuery(), "\n\n";
echo $out->collect();
```
