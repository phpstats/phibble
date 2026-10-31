<?php

$penguins = DataFrame::fromCsv('penguins.csv');

$counts = $penguins
    ->groupBy(['island'])
    ->summarize(['n' => n()])
    ->arrange([desc(col('n'))]);

echo $counts;
