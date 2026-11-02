```php
$penguins = tibble(
    species: 'Adelie',
    island: ['Torgersen', 'Torgersen', 'Torgersen', 'Torgersen'],
    body_mass_g: [3750, 3800, 3250, null],
    heavy: fn (DataFrame $t) => array_map(
        fn ($m) => $m === null ? null : $m >= 3500,
        $t['body_mass_g'],
    ),
);

echo $penguins;
```

```{.text .output}
# A tibble: 4 × 4
  species island    body_mass_g heavy
  <chr>   <chr>           <int> <lgl>
1 Adelie  Torgersen        3750 TRUE 
2 Adelie  Torgersen        3800 TRUE 
3 Adelie  Torgersen        3250 FALSE
4 Adelie  Torgersen          NA NA
```
