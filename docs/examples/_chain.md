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

```{.text .output}
# A tibble: 3 × 2
  island        n
  <chr>     <int>
1 Biscoe      168
2 Dream       124
3 Torgersen    52
```
