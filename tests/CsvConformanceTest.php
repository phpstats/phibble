<?php

declare(strict_types=1);

/*
 * phibble's reader against the corpus it published.
 *
 * tests/fixtures/csv-conformance.json records what phibble's PHP reader
 * answers for every case in it, through the Phit\read_csv() contract. phit
 * commits a byte-identical copy and checks itself against the same file, so
 * this test is what keeps the file honest: without it nothing notices when
 * phibble's reader changes, and the first sign of trouble is a failure in
 * phit's repository, where the natural first guess is that phit broke.
 *
 * Regenerate with bin/generate-csv-conformance, which also holds the case list
 * and this side's copy of the fixture encoding. Requiring it defines those and
 * writes nothing.
 */

require_once __DIR__.'/../bin/generate-csv-conformance';

/** @return array<string, array{0: array<string, mixed>}> */
function csvConformanceFixtureCases(): array
{
    $document = json_decode((string) file_get_contents(CSV_CONFORMANCE_FIXTURE), true, 512, JSON_THROW_ON_ERROR);

    return array_combine(
        array_column($document['cases'], 'name'),
        array_map(fn (array $case) => [$case], $document['cases']),
    );
}

it('is generated from the cases the generator declares', function () {
    $document = json_decode((string) file_get_contents(CSV_CONFORMANCE_FIXTURE), true, 512, JSON_THROW_ON_ERROR);

    expect(array_column($document['cases'], 'name'))
        ->toBe(array_column(csvConformanceCases(), 'name'));
});

it('reads the corpus as the committed expectations say', function (array $case) {
    $input = array_key_exists('input_base64', $case)
        ? base64_decode($case['input_base64'], true)
        : $case['input'];
    expect($input)->toBeString();

    $directory = sys_get_temp_dir().'/phibble-csv-conformance-'.getmypid();
    @mkdir($directory);
    try {
        $actual = csvConformanceRead(['input' => $input, 'options' => $case['options']], $directory);
    } finally {
        array_map(unlink(...), glob($directory.'/*') ?: []);
        @rmdir($directory);
    }

    expect($actual['names'])->toBe($case['expected']['names'], 'names')
        ->and($actual['problems'])->toBe($case['expected']['problems'], 'problems')
        ->and(count($actual['columns']))->toBe(count($case['expected']['columns']), 'column count');
    foreach ($case['expected']['columns'] as $i => $want) {
        expect($actual['columns'][$i])->toBe($want, "column {$i}");
    }
})->with(csvConformanceFixtureCases());
