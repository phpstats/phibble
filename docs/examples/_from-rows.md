```php
$rows = DataFrame::fromRows([
    ['species' => 'Adelie', 'island' => 'Torgersen', 'body_mass_g' => 3750],
    ['species' => 'Adelie', 'island' => 'Torgersen'],
]);
$cols = DataFrame::fromColumns(
    ['species' => ['Adelie', 'Gentoo'], 'island' => ['Torgersen', 'Biscoe']],
    types: ['species' => 'factor'],
);

echo $rows, "\n\n", $cols;
```

```{.text .output}
# A tibble: 2 × 3
  species island    body_mass_g
  <chr>   <chr>           <int>
1 Adelie  Torgersen        3750
2 Adelie  Torgersen          NA

# A tibble: 2 × 2
  species island   
  <fct>   <chr>    
1 Adelie  Torgersen
2 Gentoo  Biscoe
```
