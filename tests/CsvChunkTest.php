<?php

declare(strict_types=1);

use Phibble\DataFrame;
use Phibble\Source\CsvSource;
use Phibble\Type;

/** Write $text to a temporary file, run $fn with its path, and remove it. */
function withCsvFile(string $text, Closure $fn): mixed
{
    $path = tempnam(sys_get_temp_dir(), 'phibble-chunks');
    file_put_contents($path, $text);
    try {
        return $fn($path);
    } finally {
        unlink($path);
    }
}

/** @return array<int, DataFrame> Chunks by the position of their first row. */
function chunksOf(string $text, int $size, mixed ...$options): array
{
    return withCsvFile($text, fn (string $path) => iterator_to_array(DataFrame::csvChunks($path, $size, ...$options)));
}

it('reads a file in chunks that add up to fromCsv()', function () {
    $csv = "id,name,score\n".implode('', array_map(fn ($i) => "{$i},n{$i},".($i / 2)."\n", range(1, 25)));

    $chunks = chunksOf($csv, 10);
    $whole = withCsvFile($csv, fn ($path) => DataFrame::fromCsv($path));

    expect(array_keys($chunks))->toBe([1, 11, 21])
        ->and(array_map(fn (DataFrame $c) => $c->nrow(), array_values($chunks)))->toBe([10, 10, 5])
        ->and(DataFrame::concat(array_values($chunks))->toRows())->toBe($whole->toRows());
});

it('guesses types from the first chunk and parses later chunks as those types', function () {
    $csv = "n\n1\n2\n3\nx\n";

    [$chunks, $warnings] = quietly(fn () => chunksOf($csv, 2));

    expect(array_map(fn (DataFrame $c) => $c->type('n'), $chunks))->toBe([1 => Type::Int, 3 => Type::Int])
        ->and($chunks[3]->column('n'))->toBe([3, null])
        ->and($warnings)->toBe(["One or more parsing issues: column 'n': 1 value could not be parsed as int."]);
});

it('reads every column as text without guessing', function () {
    $chunks = chunksOf("a,b\n1,TRUE\n2,FALSE\n", 1, types: ['.default' => 'c']);

    expect($chunks[1]->types())->toBe(['a' => Type::String, 'b' => Type::String])
        ->and($chunks[2]->toRows())->toBe([['a' => '2', 'b' => 'FALSE']]);
});

it('stops when the callback returns false', function () {
    $seen = [];
    withCsvFile("a\n1\n2\n3\n4\n5\n", function (string $path) use (&$seen) {
        DataFrame::fromCsvChunked($path, function (DataFrame $chunk, int $position) use (&$seen) {
            $seen[] = [$position, $chunk->column('a')];

            return $position < 3 ? null : false;
        }, chunkSize: 2);
    });

    expect($seen)->toBe([[1, [1, 2]], [3, [3, 4]]]);
});

it('numbers problem rows from the start of the file', function () {
    $csv = "a,b\n1,2\n3,4\n5,6,7\n8\n";

    [$chunks, $warnings] = quietly(fn () => chunksOf($csv, 2));

    expect(count($chunks))->toBe(2)
        ->and($warnings)->toBe(['One or more parsing issues: row 4: expected 2 columns, got 3; row 5: expected 2 columns, got 1.']);
});

it('applies skip, comment, nMax, NA, and trimWs across chunks as fromCsv() does', function () {
    $csv = "junk\na,b\n# note\n 1 ,NA\n2,x\n\n# more\n3,y\n4,z\n";
    $options = ['skip' => 1, 'comment' => '#', 'nMax' => 3];

    $chunks = chunksOf($csv, 2, ...$options);
    $whole = withCsvFile($csv, fn ($path) => DataFrame::fromCsv($path, ...$options));

    expect(array_keys($chunks))->toBe([1, 3])
        ->and(DataFrame::concat(array_values($chunks))->toRows())->toBe($whole->toRows())
        ->and($whole->column('a'))->toBe([1, 2, 3])
        ->and($whole->column('b'))->toBe([null, 'x', 'y']);
});

it('reads tab-separated files by the same quote rules', function () {
    $tsv = "a\tb\n\"x \"y\"\tz\"\t1\nx\"y\t2\n\"multi\nline\"\t3\n";

    $chunks = chunksOf($tsv, 1, delimiter: "\t", types: ['.default' => 'c']);

    expect(array_map(fn (DataFrame $c) => $c->toRows()[0], $chunks))->toBe([
        1 => ['a' => "x y\tz", 'b' => '1'],
        2 => ['a' => 'x"y', 'b' => '2'],
        3 => ['a' => "multi\nline", 'b' => '3'],
    ]);
});

it('keeps records whole across read blocks', function (int $blockSize) {
    // CRLF endings, one of them after a stray CR, and quoted line breaks.
    $csv = "a,b\r\n\"one\ntwo\",1\r\n\"x \"\"q\"\" y\",2\r\r\nplain,3\r\n";
    $expected = withCsvFile($csv, fn ($path) => DataFrame::fromCsv($path))->toRows();
    $previous = CsvSource::$blockSize;
    CsvSource::$blockSize = $blockSize;
    try {
        $chunks = chunksOf($csv, 2);
        $whole = withCsvFile($csv, fn ($path) => DataFrame::fromCsv($path))->toRows();
    } finally {
        CsvSource::$blockSize = $previous;
    }

    expect(array_column($expected, 'a'))->toBe(["one\ntwo", 'x "q" y', 'plain'])
        ->and(array_column($expected, 'b'))->toBe(['1', "2\r", '3'])
        ->and($whole)->toBe($expected)
        ->and(DataFrame::concat(array_values($chunks))->toRows())->toBe($expected);
})->with([1, 2, 3, 5, 8]);

it('yields no chunks for an empty or header-only file', function () {
    expect(chunksOf('', 5))->toBe([])
        ->and(chunksOf("a,b\n", 5))->toBe([]);
});

it('rejects a chunk size below one', function () {
    chunksOf("a\n1\n", 0);
})->throws(InvalidArgumentException::class, 'The chunk size must be at least 1, got 0.');

it('reads the same chunks with phit as in PHP', function () {
    if (! extension_loaded('phit')) {
        test()->markTestSkipped('Phit\CsvReader is not available (phit extension not loaded).');
    }
    $csv = "junk\nid,name,score,day,flag\n# note\n1, a ,1.5,2024-01-02,TRUE\n2,\"b \"\"q\"\"\",NA,2024-01-03,FALSE\n"
        ."3,c,x,2024-01-04,TRUE\n4,d,2.5,2024-01-05\n5,e,3.5,2024-01-06,TRUE,extra\n6,\"multi\nline\",4,2024-01-07,FALSE\n";
    $options = ['skip' => 1, 'comment' => '#', 'select' => ['score', 'id', 'name', 'day']];
    $read = fn () => quietly(fn () => array_map(fn (DataFrame $c) => [$c->types(), $c->toRows()], chunksOf($csv, 2, ...$options)));

    [$native, $nativeWarnings] = $read();
    Phibble\Io\NativeCsv::enable(false);
    try {
        [$php, $phpWarnings] = $read();
    } finally {
        Phibble\Io\NativeCsv::enable(true);
    }

    expect(array_keys($native))->toBe([1, 3, 5])
        ->and($native)->toEqual($php)
        ->and($nativeWarnings)->toBe($phpWarnings);
});
