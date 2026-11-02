<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)
    ->asFactor('species')
    ->filter(col('species')->ne('Chinstrap'));

echo $penguins
    ->groupBy(['species'], drop: false)
    ->summarize(['n' => n()]);
