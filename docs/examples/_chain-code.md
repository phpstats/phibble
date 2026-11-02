```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$counts = $penguins
    ->groupBy(['island'])
    ->summarize(['n' => n()])
    ->arrange([desc(col('n'))]);

echo $counts;
```
