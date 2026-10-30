<?php

declare(strict_types=1);

use Phibble\DataFrame;

/*
 * Memory for a mixed-type table: ids, low-cardinality labels, names, prices,
 * ratings, flags, dates, datetimes, and free text. The bounds are multiples of
 * the CSV's size, so they hold at any row count. They sit above what phibble
 * uses now (about 1.3x held and 3.2x peak while reading, and about 0.3x
 * while writing 50,000 rows) to catch regressions.
 */

function mixedCsv(int $rows): string
{
    mt_srand(1);
    $brands = ['Nike', 'Adidas', 'Asics', 'Brooks', 'Hoka', 'Saucony', 'New Balance', 'On', 'Mizuno', 'Puma'];
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['id', 'brand', 'name', 'price', 'rating', 'in_stock', 'released', 'updated_at', 'description'], escape: '');
    for ($i = 1; $i <= $rows; $i++) {
        fputcsv($handle, [
            $i,
            $brands[$i % 10],
            'Model '.mt_rand(1, 5000).' v'.mt_rand(1, 9),
            sprintf('%.2f', mt_rand(3000, 25000) / 100),
            mt_rand(10, 50) / 10,
            mt_rand(0, 1) === 1 ? 'TRUE' : 'FALSE',
            sprintf('20%02d-%02d-%02d', mt_rand(10, 25), mt_rand(1, 12), mt_rand(1, 28)),
            sprintf('2026-%02d-%02d %02d:%02d:%02d', mt_rand(1, 10), mt_rand(1, 28), mt_rand(0, 23), mt_rand(0, 59), mt_rand(0, 59)),
            str_repeat('Cushioned daily trainer with a durable outsole. ', mt_rand(1, 3)),
        ], escape: '');
    }
    rewind($handle);
    $csv = (string) stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

it('holds a mixed-type table in under 4x its CSV size, and reads it in under 7x', function () {
    $csv = mixedCsv(20_000);
    $path = tempnam(sys_get_temp_dir(), 'phibble-memory');
    file_put_contents($path, $csv);
    $size = strlen($csv);
    unset($csv);

    try {
        gc_collect_cycles();
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $df = DataFrame::fromCsv($path);
        $peak = memory_get_peak_usage() - $before;
        $held = memory_get_usage() - $before;
    } finally {
        unlink($path);
    }

    expect($df->nrow())->toBe(20_000)
        ->and($held / $size)->toBeLessThan(4.0)
        ->and($peak / $size)->toBeLessThan(7.0);
});

it('writes CSV in chunks, without holding the whole output', function () {
    // Writing costs about one chunk of rows whatever the size, so the bound needs some rows.
    $csv = mixedCsv(50_000);
    $df = DataFrame::fromCsvString($csv);
    $path = tempnam(sys_get_temp_dir(), 'phibble-memory');

    try {
        gc_collect_cycles();
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $df->writeCsv($path);
        $peak = memory_get_peak_usage() - $before;
        $written = (string) file_get_contents($path);
    } finally {
        unlink($path);
    }

    expect($written)->toBe($df->toCsv())
        ->and($peak / strlen($written))->toBeLessThan(0.5);
});
