```php
$df = tibble(species: ['Adelie', 'Adelie'], body_mass_g: [3750, null]);
echo $df->toJson(), "\n";
echo $df->toJson('columns'), "\n";
echo DataFrame::fromJson('{"faa": ["EWR", "JFK"], "alt": [18, 13]}');
```

```{.text .output}
[{"species":"Adelie","body_mass_g":3750},{"species":"Adelie","body_mass_g":null}]
{"species":["Adelie","Adelie"],"body_mass_g":[3750,null]}
# A tibble: 2 × 2
  faa     alt
  <chr> <int>
1 EWR      18
2 JFK      13
```
