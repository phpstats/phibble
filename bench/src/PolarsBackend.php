<?php

declare(strict_types=1);

namespace Phibble\Bench;

use Phibble\Contracts\Table;
use Phibble\DataFrame;
use Phibble\Polars\PolarsTable;

/**
 * PolarsTable for phibble's shared benchmark runner (bin/bench). Data is
 * loaded through phibble's CSV reader, so every backend starts from the
 * same types, then copied into Polars.
 */
final class PolarsBackend implements Backend
{
    public function name(): string
    {
        return 'polars';
    }

    public function load(string $path): Table
    {
        return PolarsTable::fromTable(DataFrame::fromCsv($path));
    }
}
