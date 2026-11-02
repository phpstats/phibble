```php
$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$monthly = DataFrame::fromCsv("{$data}/weather.csv")
    ->filter(col('origin')->eq('JFK'))
    ->groupBy(['month'])
    ->summarize(['precip' => sum(col('precip'))]);

echo $monthly->mutate([
    'prev' => lag(col('precip')),
    'change' => col('precip')->sub(lag(col('precip'))),
    'running' => cumsum(col('precip')),
    'rank' => min_rank(desc(col('precip'))),
    'half' => ntile(col('precip'), 2),
]);
```

```{.text .output}
# A tibble: 12 × 7
   month precip  prev change running  rank  half
   <int>  <dbl> <dbl>  <dbl>   <dbl> <int> <int>
 1     1   2.44 NA    NA        2.44     7     1
 2     2   2.73  2.44  0.290    5.17     4     2
 3     3   2.23  2.73 -0.500    7.4      9     1
 4     4   1.78  2.23 -0.450    9.18    11     1
 5     5   3.28  1.78  1.50    12.5      3     2
 6     6   7.95  3.28  4.67    20.4      1     2
 7     7   2.26  7.95 -5.69    22.7      8     1
 8     8   2.73  2.26  0.470   25.4      5     2
 9     9   1.92  2.73 -0.810   27.3     10     1
10    10   0.32  1.92 -1.6     27.6     12     1
11    11   2.55  0.32  2.23    30.2      6     2
12    12   4.50  2.55  1.95    34.7      2     2
```
