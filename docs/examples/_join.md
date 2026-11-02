```php
$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$weather = DataFrame::fromCsv("{$data}/weather.csv");
$airports = DataFrame::fromCsv("{$data}/airports.csv");

$stations = $weather
    ->distinct(['origin'])
    ->join($airports, [['origin', 'faa']], 'left')
    ->select(['origin', 'name', 'lat', 'lon', 'alt']);

echo $stations;
```

```{.text .output}
# A tibble: 3 × 5
  origin name                  lat   lon   alt
  <chr>  <chr>               <dbl> <dbl> <int>
1 EWR    Newark Liberty Intl  40.7 -74.2    18
2 JFK    John F Kennedy Intl  40.6 -73.8    13
3 LGA    La Guardia           40.8 -73.9    22
```
