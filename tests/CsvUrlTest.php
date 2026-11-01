<?php

declare(strict_types=1);

use Phibble\DataFrame;

/*
 * Reading CSV from http:// URLs, served by PHP's built-in web server from a
 * temporary directory, so the tests need no internet.
 */

beforeAll(function () {
    $dir = sys_get_temp_dir().'/phibble-url-'.getmypid();
    @mkdir($dir);
    $csv = "id,name,score\n1,ann,2.5\n2,bob,NA\n3,cy,4\n";
    file_put_contents("{$dir}/scores.csv", $csv);
    file_put_contents("{$dir}/scores.csv.gz", gzencode($csv));
    $port = 0;
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $dir], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 100 && @fsockopen('127.0.0.1', $port) === false; $i++) {
        usleep(20_000);
    }
    $GLOBALS['csvUrl'] = ['dir' => $dir, 'base' => "http://127.0.0.1:{$port}", 'process' => $process];
});

afterAll(function () {
    proc_terminate($GLOBALS['csvUrl']['process']);
    proc_close($GLOBALS['csvUrl']['process']);
    array_map(unlink(...), glob($GLOBALS['csvUrl']['dir'].'/*'));
    rmdir($GLOBALS['csvUrl']['dir']);
});

function csvUrl(string $file): string
{
    return $GLOBALS['csvUrl']['base'].'/'.$file;
}

function phibbleDownloads(): array
{
    return glob(sys_get_temp_dir().'/phibble-url*.csv*') ?: [];
}

it('reads a CSV file from a URL as it reads the local file', function () {
    $before = phibbleDownloads();
    $df = DataFrame::fromCsv(csvUrl('scores.csv'));

    expect($df->toRows())->toBe(DataFrame::fromCsv($GLOBALS['csvUrl']['dir'].'/scores.csv')->toRows())
        ->and($df->types())->toBe(DataFrame::fromCsv($GLOBALS['csvUrl']['dir'].'/scores.csv')->types())
        ->and(phibbleDownloads())->toBe($before);
});

it('decompresses a URL ending in .gz', function () {
    expect(DataFrame::fromCsv(csvUrl('scores.csv.gz').'?v=1')->toRows())
        ->toBe(DataFrame::fromCsv($GLOBALS['csvUrl']['dir'].'/scores.csv')->toRows());
});

it('reads a URL in chunks and deletes the download afterwards', function () {
    $before = phibbleDownloads();
    $chunks = [];
    DataFrame::fromCsvChunked(csvUrl('scores.csv'), function (DataFrame $chunk, int $position) use (&$chunks) {
        $chunks[$position] = $chunk->id;
    }, chunkSize: 2);

    expect($chunks)->toBe([1 => [1, 2], 3 => [3]])
        ->and(phibbleDownloads())->toBe($before);
});

it('names the URL and the HTTP error when the request fails', function () {
    $before = phibbleDownloads();

    expect(fn () => DataFrame::fromCsv(csvUrl('missing.csv')))
        ->toThrow(RuntimeException::class, "Cannot read CSV URL '".csvUrl('missing.csv')."'")
        ->and(phibbleDownloads())->toBe($before);
});

it('says when allow_url_fopen is off', function () {
    $script = 'require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';'
        .'try { Phibble\DataFrame::fromCsv('.var_export(csvUrl('scores.csv'), true).'); } catch (RuntimeException $e) { echo $e->getMessage(); }';
    $out = shell_exec(escapeshellarg(PHP_BINARY).' -d allow_url_fopen=0 -r '.escapeshellarg($script));

    expect($out)->toBe("Cannot read CSV URL '".csvUrl('scores.csv')."': PHP's allow_url_fopen setting is off.");
});
