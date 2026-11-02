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

```{.text .output}
SELECT "origin", "hours", "temp", "precip", "gust" FROM (SELECT "origin", "hours", "temp", "precip", "gust", ROW_NUMBER() OVER (ORDER BY ("origin" IS NULL), "origin") AS "__phibble_row" FROM (SELECT "origin" AS "origin", COUNT(*) AS "hours", (CASE WHEN COUNT(*) > COUNT("temp") THEN NULL ELSE (CASE WHEN COUNT("temp") = 0 THEN NULL ELSE AVG(CAST("temp" AS REAL)) END) END) AS "temp", (CASE WHEN COUNT(*) > COUNT("precip") THEN NULL ELSE COALESCE(SUM("precip"), 0) END) AS "precip", (CASE WHEN COUNT("wind_gust") = 0 THEN -9e999 ELSE MAX("wind_gust") END) AS "gust" FROM (SELECT * FROM (SELECT *, rowid AS "__phibble_row" FROM "weather") AS q1 WHERE (NOT ("temp" IS NULL))) AS q2 GROUP BY "origin") AS q3) AS q4 ORDER BY ("precip" IS NULL), "precip" DESC, ("__phibble_row" IS NULL), "__phibble_row"

# A tibble: 3 × 5
  origin hours  temp precip  gust
  <chr>  <int> <dbl>  <dbl> <dbl>
1 EWR     8702  55.5   43.8  58.7
2 LGA     8706  55.8   38.1  62.1
3 JFK     8706  54.5   34.7  66.7
```
