<?php

declare(strict_types=1);

it('keeps the Functions and methods reference in step with the public API', function () {
    $root = dirname(__DIR__);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg("{$root}/bin/api-reference").' --check 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
});

it('lists every docs page in site.json in the Quarto sidebar order', function () {
    $root = dirname(__DIR__);
    $site = json_decode((string) file_get_contents("{$root}/site/site.json"), true);
    preg_match_all('/^\s+- (\S+\.qmd)$/m', (string) file_get_contents("{$root}/docs/_quarto.yml"), $sidebar);

    expect(array_column($site['docs']['nav'], 'path'))->toBe($sidebar[1]);
});
