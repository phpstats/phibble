<?php

$totals = [];
$count = function (DataFrame $chunk) use (&$totals) {
    $counts = $chunk
        ->groupBy(['species'])
        ->summarize(['n' => n()]);
    foreach ($counts as $row) {
        $totals[$row->species] ??= 0;
        $totals[$row->species] += $row->n;
    }
};

DataFrame::fromCsvChunked('penguins.csv', $count, chunkSize: 100);
ksort($totals);

echo tibble(
    species: array_keys($totals),
    n: array_values($totals),
);
