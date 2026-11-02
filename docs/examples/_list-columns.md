```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)
    ->select(['species', 'island', 'body_mass_g']);

$nested = $penguins->nest(['island', 'body_mass_g'], into: 'data');
echo $nested, "\n";

$sizes = $nested->mutate(['n' => fn ($r) => $r->data->nrow()]);
echo $sizes->select(['species', 'n']), "\n";

echo $nested->unnest('data')->head(3);
```

```{.text .output}
# A tibble: 3 × 2
  species   data              
  <chr>     <list>            
1 Adelie    <tibble [152 × 2]>
2 Gentoo    <tibble [124 × 2]>
3 Chinstrap <tibble [68 × 2]> 
# A tibble: 3 × 2
  species       n
  <chr>     <int>
1 Adelie      152
2 Gentoo      124
3 Chinstrap    68
# A tibble: 3 × 3
  species island    body_mass_g
  <chr>   <chr>           <int>
1 Adelie  Torgersen        3750
2 Adelie  Torgersen        3800
3 Adelie  Torgersen        3250
```
