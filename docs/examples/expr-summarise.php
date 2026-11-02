<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

echo $penguins
    ->groupBy(['island'])
    ->summarize([
        'n' => n(),
        'mass' => mean(col('body_mass_g')),
        'mass_rm' => mean(col('body_mass_g'), na_rm: true),
        'q90' => quantile(col('body_mass_g'), 0.9, na_rm: true),
        'species' => n_distinct(col('species')),
        'any_heavy' => any(
            col('body_mass_g')->gt(6000),
            na_rm: true,
        ),
    ]);
