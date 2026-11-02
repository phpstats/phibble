<?php

$df = tibble(species: ['Adelie', 'Adelie'], body_mass_g: [3750, null]);
echo $df->toJson(), "\n";
echo $df->toJson('columns'), "\n";
echo DataFrame::fromJson('{"faa": ["EWR", "JFK"], "alt": [18, 13]}');
