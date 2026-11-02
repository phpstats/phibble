<?php

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
