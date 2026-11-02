```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)
    ->dropNa(['body_mass_g']);

$centered = $penguins
    ->groupBy(['species'])
    ->mutate([
        'vs_species' => col('body_mass_g')
            ->sub(mean(col('body_mass_g'))),
    ])
    ->select(['species', 'body_mass_g', 'vs_species'])
    ->head(4);

echo $centered;
```

```{.text .output}
# A tibble: 4 × 3
# Groups:   species [1]
  species body_mass_g vs_species
  <chr>         <int>      <dbl>
1 Adelie         3750       49.3
2 Adelie         3800       99.3
3 Adelie         3250     -451. 
4 Adelie         3450     -251.
```
