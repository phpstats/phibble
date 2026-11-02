```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$heavy = $penguins
    ->select(['species', 'island', 'body_mass_g', 'year'])
    ->filter(col('body_mass_g')->gt(5500))
    ->mutate(['kg' => col('body_mass_g')->div(1000)])
    ->arrange([desc(col('body_mass_g'))])
    ->head(5);

echo $heavy;
```

```{.text .output}
# A tibble: 5 × 5
  species island body_mass_g  year    kg
  <chr>   <chr>        <int> <int> <dbl>
1 Gentoo  Biscoe        6300  2007  6.3 
2 Gentoo  Biscoe        6050  2007  6.05
3 Gentoo  Biscoe        6000  2008  6   
4 Gentoo  Biscoe        6000  2009  6   
5 Gentoo  Biscoe        5950  2008  5.95
```
