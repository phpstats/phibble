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
    // Wait until the server answers a request, not just a connection.
    for ($i = 0; $i < 250 && @file_get_contents("http://127.0.0.1:{$port}/scores.csv") === false; $i++) {
        usleep(20_000);
    }
    $GLOBALS['csvUrl'] = ['dir' => $dir, 'base' => "http://127.0.0.1:{$port}", 'process' => $process];
});

afterAll(function () {
    proc_terminate($GLOBALS['csvUrl']['process']);
    proc_close($GLOBALS['csvUrl']['process']);
    array_map(unlink(...), glob($GLOBALS['csvUrl']['dir'].'/tmp/*') ?: []);
    @rmdir($GLOBALS['csvUrl']['dir'].'/tmp');
    array_map(unlink(...), glob($GLOBALS['csvUrl']['dir'].'/*.*') ?: []);
    rmdir($GLOBALS['csvUrl']['dir']);
});

function csvUrl(string $file): string
{
    return $GLOBALS['csvUrl']['base'].'/'.$file;
}

/**
 * Run $code in a PHP process whose temporary directory is this test's own,
 * so downloads by other processes cannot show up. Returns the code's result
 * and the phibble downloads left in that directory afterwards.
 *
 * @return array{result: mixed, downloads: list<string>}
 */
function inOwnTempDir(string $code): array
{
    $temp = $GLOBALS['csvUrl']['dir'].'/tmp';
    is_dir($temp) || mkdir($temp);
    $script = 'require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';'
        .'try { $result = (function () { '.$code.' })(); } catch (Throwable $e) { $result = ["exception" => get_class($e), "message" => $e->getMessage()]; }'
        .'echo serialize(["result" => $result, "downloads" => array_map(basename(...), glob(sys_get_temp_dir()."/phibble-url*") ?: [])]);';
    $out = shell_exec(escapeshellarg(PHP_BINARY).' -d sys_temp_dir='.escapeshellarg($temp).' -r '.escapeshellarg($script));

    return unserialize((string) $out);
}

it('reads a CSV file from a URL as it reads the local file', function () {
    $local = DataFrame::fromCsv($GLOBALS['csvUrl']['dir'].'/scores.csv');
    $run = inOwnTempDir('$df = Phibble\DataFrame::fromCsv('.var_export(csvUrl('scores.csv'), true).');'
        .'return ["rows" => $df->toRows(), "types" => array_map(fn ($t) => $t->value, $df->types())];');

    expect($run['result']['rows'])->toBe($local->toRows())
        ->and($run['result']['types'])->toBe(array_map(fn ($t) => $t->value, $local->types()))
        ->and($run['downloads'])->toBe([]);
});

it('decompresses a URL ending in .gz', function () {
    expect(DataFrame::fromCsv(csvUrl('scores.csv.gz').'?v=1')->toRows())
        ->toBe(DataFrame::fromCsv($GLOBALS['csvUrl']['dir'].'/scores.csv')->toRows());
});

it('reads a URL in chunks and deletes the download afterwards', function () {
    $run = inOwnTempDir('$chunks = [];'
        .'Phibble\DataFrame::fromCsvChunked('.var_export(csvUrl('scores.csv'), true).', function ($chunk, int $position) use (&$chunks) { $chunks[$position] = $chunk->id; }, chunkSize: 2);'
        .'return $chunks;');

    expect($run['result'])->toBe([1 => [1, 2], 3 => [3]])
        ->and($run['downloads'])->toBe([]);
});

it('names the URL and the HTTP error when the request fails', function () {
    $run = inOwnTempDir('Phibble\DataFrame::fromCsv('.var_export(csvUrl('missing.csv'), true).');');

    expect($run['result']['exception'])->toBe(RuntimeException::class)
        ->and($run['result']['message'])->toStartWith("Cannot read CSV URL '".csvUrl('missing.csv')."'")
        ->and($run['downloads'])->toBe([]);
});

it('says when allow_url_fopen is off', function () {
    $script = 'require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';'
        .'try { Phibble\DataFrame::fromCsv('.var_export(csvUrl('scores.csv'), true).'); } catch (RuntimeException $e) { echo $e->getMessage(); }';
    $out = shell_exec(escapeshellarg(PHP_BINARY).' -d allow_url_fopen=0 -r '.escapeshellarg($script));

    expect($out)->toBe("Cannot read CSV URL '".csvUrl('scores.csv')."': PHP's allow_url_fopen setting is off.");
});
