```php
$totals = [];
$count = function (DataFrame $chunk) use (&$totals) {
    $counts = $chunk
        ->groupBy(['species'])
        ->summarize(['n' => n()]);
    foreach ($counts as $row) {
        $totals[$row->species] ??= 0;
        $totals[$row->species] += $row->n;
    }
};

DataFrame::fromCsvChunked(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv', $count, chunkSize: 100);
ksort($totals);

echo tibble(
    species: array_keys($totals),
    n: array_values($totals),
);
```

```{.text .output}
# A tibble: 3 × 2
  species       n
  <chr>     <int>
1 Adelie      152
2 Chinstrap    68
3 Gentoo      124
```
