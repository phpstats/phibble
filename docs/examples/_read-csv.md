```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

echo $penguins;
```

```{.text .output}
# A tibble: 344 × 9
   rownames species island    bill_length_mm bill_depth_mm flipper_length_mm
      <int> <chr>   <chr>              <dbl>         <dbl>             <int>
 1        1 Adelie  Torgersen           39.1          18.7               181
 2        2 Adelie  Torgersen           39.5          17.4               186
 3        3 Adelie  Torgersen           40.3          18                 195
 4        4 Adelie  Torgersen           NA            NA                  NA
 5        5 Adelie  Torgersen           36.7          19.3               193
 6        6 Adelie  Torgersen           39.3          20.6               190
 7        7 Adelie  Torgersen           38.9          17.8               181
 8        8 Adelie  Torgersen           39.2          19.6               195
 9        9 Adelie  Torgersen           34.1          18.1               193
10       10 Adelie  Torgersen           42            20.2               190
# ℹ 334 more rows
# ℹ 3 more variables: body_mass_g <int>, sex <chr>, year <int>
```
