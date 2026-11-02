```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$heavy = $penguins
    ->select(['species', 'island', 'body_mass_g', 'year'])
    ->filter(fn ($r) => $r->body_mass_g > 5500)
    ->mutate(['kg' => fn ($r) => $r->body_mass_g / 1000])
    ->arrange([['body_mass_g', 'desc']])
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
