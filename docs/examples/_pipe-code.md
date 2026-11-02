```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$counts = $penguins |>
    (fn (DataFrame $f) => $f->groupBy(['island'])) |>
    (fn (DataFrame $f) => $f->summarize(['n' => n()])) |>
    (fn (DataFrame $f) => $f->arrange([desc(col('n'))]));

echo $counts;
```
