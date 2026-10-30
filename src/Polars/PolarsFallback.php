<?php

declare(strict_types=1);

namespace Phibble\Polars;

use RuntimeException;

/**
 * Thrown with PolarsTable::$strict on, where a verb would otherwise run in
 * an in-memory DataFrame.
 */
final class PolarsFallback extends RuntimeException {}
