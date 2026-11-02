```php
$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)->asFactor('species');

$mean = fn ($xs) => array_sum(array_filter($xs))
    / count(array_filter($xs));

$versions = [
    'sorted (default)' => $penguins,
    'fctRelevel Gentoo' => $penguins
        ->fctRelevel('species', ['Gentoo']),
    'fctInfreq' => $penguins->fctInfreq('species'),
    'fctReorder by body_mass_g' => $penguins
        ->fctReorder('species', 'body_mass_g', $mean),
    'fctRev' => $penguins->fctRev('species'),
    'fctRecode' => $penguins
        ->fctRecode('species', ['Chinstrap penguin' => 'Chinstrap']),
    'fctCollapse' => $penguins
        ->fctCollapse('species', ['brush-tailed' => ['Adelie', 'Gentoo']]),
];

foreach ($versions as $label => $f) {
    echo \str_pad($label, 26), json_encode($f->levels('species')), "\n";
}
```

```{.text .output}
sorted (default)          ["Adelie","Chinstrap","Gentoo"]
fctRelevel Gentoo         ["Gentoo","Adelie","Chinstrap"]
fctInfreq                 ["Adelie","Gentoo","Chinstrap"]
fctReorder by body_mass_g ["Adelie","Chinstrap","Gentoo"]
fctRev                    ["Gentoo","Chinstrap","Adelie"]
fctRecode                 ["Adelie","Chinstrap penguin","Gentoo"]
fctCollapse               ["brush-tailed","Chinstrap"]
```
