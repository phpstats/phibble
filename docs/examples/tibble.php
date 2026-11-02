<?php

$penguins = tibble(
    species: 'Adelie',
    island: ['Torgersen', 'Torgersen', 'Torgersen', 'Torgersen'],
    body_mass_g: [3750, 3800, 3250, null],
    heavy: fn (DataFrame $t) => array_map(
        fn ($m) => $m === null ? null : $m >= 3500,
        $t['body_mass_g'],
    ),
);

echo $penguins;
