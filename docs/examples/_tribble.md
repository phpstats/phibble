```php
$airports = tribble(
    ['faa', 'name', 'lat', 'lon'],
    ['EWR', 'Newark Liberty Intl', 40.6925, -74.168667],
    ['JFK', 'John F Kennedy Intl', 40.639751, -73.778925],
    ['LGA', 'La Guardia', 40.777245, -73.872608],
);

echo $airports;
```

```{.text .output}
# A tibble: 3 × 4
  faa   name                  lat   lon
  <chr> <chr>               <dbl> <dbl>
1 EWR   Newark Liberty Intl  40.7 -74.2
2 JFK   John F Kennedy Intl  40.6 -73.8
3 LGA   La Guardia           40.8 -73.9
```
