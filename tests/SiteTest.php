<?php

declare(strict_types=1);

it('points the data URLs at the latest release', function () {
    $root = dirname(__DIR__);
    preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', file_get_contents("{$root}/CHANGELOG.md"), $c);
    preg_match('/^release: (\S+)$/m', file_get_contents("{$root}/docs/_variables.yml"), $v);
    expect($v[1])->toBe('v'.$c[1]);
    expect(file_get_contents("{$root}/README.md"))->toContain("phibble/{$v[1]}/docs/data/penguins.csv");
    foreach (['index', 'getting-started'] as $page) {
        expect(file_get_contents("{$root}/docs/{$page}.qmd"))->toContain('phibble/{{< var release >}}/docs/data/');
    }
});

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
