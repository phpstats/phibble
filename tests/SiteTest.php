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
