```php
$chunks = DataFrame::csvChunks(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/flights.csv',
    chunkSize: 50_000,
);

$kept = [];
foreach ($chunks as $chunk) {
    $kept[] = $chunk->filter(col('dest')->eq('LAX'));
}
$lax = DataFrame::concat($kept);

echo $lax->groupBy(['origin'])->summarize(['flights' => n()]);
```

```{.text .output}
# A tibble: 2 × 2
  origin flights
  <chr>    <int>
1 EWR       4912
2 JFK      11262
```
