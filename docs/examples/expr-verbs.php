<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$out = $penguins
    ->filter(is_na(col('body_mass_g'))->not())
    ->groupBy(['species'])
    ->mutate([
        'z' => col('body_mass_g')
            ->sub(mean(col('body_mass_g')))
            ->div(sd(col('body_mass_g'))),
        'rank' => min_rank(desc(col('body_mass_g'))),
        'size' => case_when(
            col('body_mass_g')->ge(5000), 'large',
            col('body_mass_g')->ge(3500), 'medium',
            default: 'small',
        ),
    ])
    ->filter(col('rank')->le(2))
    ->select(['species', 'body_mass_g', 'z', 'rank', 'size']);

echo $out;
