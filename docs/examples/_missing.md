```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)->head(4)->select(['species', 'body_mass_g']);

echo $penguins->mutate([
    'mass_kg' => col('body_mass_g')->div(1000),
]), "\n";
echo $penguins->summarize([
    'mean' => mean(col('body_mass_g')),
    'mean_known' => mean(col('body_mass_g'), na_rm: true),
]);
```

```{.text .output}
# A tibble: 4 × 3
  species body_mass_g mass_kg
  <chr>         <int>   <dbl>
1 Adelie         3750    3.75
2 Adelie         3800    3.8 
3 Adelie         3250    3.25
4 Adelie           NA   NA   
# A tibble: 1 × 2
   mean mean_known
  <dbl>      <dbl>
1    NA       3600
```
