<?php

$totals = [];
DataFrame::fromCsvChunked('penguins.csv', function (DataFrame $chunk) use (&$totals) {
    $counts = $chunk->groupBy(['species'])->summarize(['n' => n()]);
    foreach ($counts as $row) {
        $totals[$row->species] = ($totals[$row->species] ?? 0) + $row->n;
    }
}, chunkSize: 100);
ksort($totals);

echo tibble(species: array_keys($totals), n: array_values($totals));
