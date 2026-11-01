```php
$totals = [];
DataFrame::fromCsvChunked('penguins.csv', function (DataFrame $chunk) use (&$totals) {
    $counts = $chunk->groupBy(['species'])->summarize(['n' => n()]);
    foreach ($counts as $row) {
        $totals[$row->species] = ($totals[$row->species] ?? 0) + $row->n;
    }
}, chunkSize: 100);
ksort($totals);

echo tibble(species: array_keys($totals), n: array_values($totals));
```

```{.text .output}
# A tibble: 3 × 2
  species       n
  <chr>     <int>
1 Adelie      152
2 Chinstrap    68
3 Gentoo      124
```
