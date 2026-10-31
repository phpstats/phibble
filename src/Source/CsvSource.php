<?php

declare(strict_types=1);

namespace Phibble\Source;

use Generator;
use InvalidArgumentException;
use Phibble\DataFrame;
use Phibble\Io\ColTypes;
use Phibble\Io\CsvOptions;
use Phibble\Io\NativeCsv;
use Phibble\Io\TextParser;
use Phibble\NameRepair;
use Phibble\Type;
use RuntimeException;

/**
 * Reads delimited text the way readr::read_delim() does: guesses column
 * types from a sample of rows, turns na strings into NA, repairs names, and
 * reports problems (unparseable values, ragged rows) as one warning while
 * still returning the data.
 */
final class CsvSource implements Source
{
    /** @param resource $handle */
    private function __construct(
        private readonly mixed $handle,
        private readonly CsvOptions $options,
        private readonly bool $close,
        private readonly ?string $path = null,
    ) {}

    /** Read a file; ".gz" files are decompressed. */
    public static function file(string $path, CsvOptions $options = new CsvOptions()): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Cannot read CSV file '{$path}'.");
        }
        $target = str_ends_with(strtolower($path), '.gz') ? 'compress.zlib://'.$path : $path;
        $handle = fopen($target, 'r') ?: throw new RuntimeException("Cannot open CSV file '{$path}'.");

        return new self($handle, $options, true, $path);
    }

    public static function string(string $csv, CsvOptions $options = new CsvOptions()): self
    {
        $handle = fopen('php://memory', 'r+') ?: throw new RuntimeException('Cannot open memory stream.');
        fwrite($handle, $csv);
        rewind($handle);

        return new self($handle, $options, true);
    }

    /** @param resource $stream An open, readable stream. It is not closed. */
    public static function stream(mixed $stream, CsvOptions $options = new CsvOptions()): self
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('Expected an open stream resource.');
        }

        return new self($stream, $options, false);
    }

    public function read(): DataFrame
    {
        $o = $this->options;
        if ($this->path !== null && NativeCsv::available() && NativeCsv::supports($this->path, $o)) {
            $this->finish();
            [$df, $problems] = NativeCsv::read($this->path, $o);
            self::warn($problems);

            return $df;
        }

        foreach ($this->batches(null) as $df) {
            return $df;
        }

        return DataFrame::fromColumns([]);
    }

    /**
     * The rows in DataFrames of at most $size rows, keyed by the 1-based
     * index of each one's first row, as readr's read_delim_chunked() passes
     * it. Columns are typed once: types not given are guessed from the first
     * chunk's rows (guessMax of them, sampled as readr samples) and every
     * later chunk is parsed as those types. Factors without levels take
     * their levels from each chunk. Each chunk's problems raise their own
     * warning, with rows numbered from the start of the file.
     *
     * @return Generator<int, DataFrame>
     */
    public function chunks(int $size): Generator
    {
        if ($size < 1) {
            throw new InvalidArgumentException("The chunk size must be at least 1, got {$size}.");
        }
        if ($this->path !== null && NativeCsv::chunksAvailable() && NativeCsv::supports($this->path, $this->options)) {
            $this->finish();

            return NativeCsv::chunks($this->path, $this->options, $size);
        }

        return $this->batches($size);
    }

    /**
     * Batches of rows as DataFrames; one batch of every row when $size is
     * null. A single batch is yielded even for an empty or header-only
     * input, which read() returns; chunks() yields only batches with rows.
     *
     * @return Generator<int, DataFrame>
     */
    private function batches(?int $size): Generator
    {
        $o = $this->options;
        try {
            $records = $this->records();

            // Header (or the first record, which sets the column count).
            $first = null;
            for (; $records->valid(); $records->next()) {
                if ($records->current() !== null) {
                    $first = $records->current();
                    $records->next();

                    break;
                }
            }
            if ($first === null) {
                if ($size === null) {
                    yield 1 => DataFrame::fromColumns([]);
                }

                return;
            }

            $record = $first;
            $record[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $record[0]);
            $width = count($record);
            if ($o->colNames === true) {
                $names = array_map(fn (?string $h) => $o->trimWs ? trim((string) $h) : (string) $h, $record);
                $pendingRows = [];
            } else {
                $names = $o->colNames === false ? [] : array_values(array_map(strval(...), $o->colNames));
                $pendingRows = [$record];
            }
            for ($i = count($names); $i < $width; $i++) {
                $names[] = 'X'.($i + 1);
            }
            $width = count($names);
            $names = NameRepair::repair($names, $o->nameRepair);
            $specs = ColTypes::resolve($o->types, $names, $o->select);
            $kept = array_keys(array_filter($specs, fn ($spec) => $spec !== 'skip'));
            $parser = new TextParser($o->decimalMark, $o->groupingMark);

            // Problems name rows as readr::problems() does: the data row's index plus one.
            $count = 0;
            $columns = [];
            $problems = [];
            // Repeated values in a column share one string, until a column's
            // values turn out to be mostly distinct. Besides memory, this saves
            // time: a repeated value's new string is freed at once and reused.
            $pools = [];
            $inBatch = 0;
            $naSet = array_fill_keys($o->na, true);

            $done = false;
            $batch = 0;
            while (! $done) {
                $columns = array_fill_keys($kept, []);
                $pools = array_fill_keys($kept, []);
                $problems = [];
                $inBatch = 0;
                $limit = min($size ?? PHP_INT_MAX, $o->nMax === null ? PHP_INT_MAX : $o->nMax - $count);
                // The hot loop: one pass per record. The tokenizers' key says
                // whether the record's fields may need trimming.
                while ($inBatch < $limit) {
                    if ($pendingRows !== []) {
                        $record = array_shift($pendingRows);
                        $trim = $o->trimWs;
                    } elseif ($records->valid()) {
                        $record = $records->current();
                        $trim = $records->key();
                        $records->next();
                        if ($record === null) {
                            continue;
                        }
                    } else {
                        break;
                    }
                    if (count($record) !== $width) {
                        $problems[] = 'row '.($count + 2).": expected {$width} columns, got ".count($record);
                        if (count($record) < $width) {
                            $record = array_pad($record, $width, null);
                        } else {
                            $extra = array_splice($record, $width - 1);
                            $record[] = implode($o->delimiter, array_map(strval(...), $extra));
                        }
                    }
                    foreach ($kept as $i) {
                        $value = $record[$i];
                        if ($trim && $value !== null) {
                            $value = trim($value, " \t");
                        }
                        $columns[$i][] = match (true) {
                            $value === null, isset($naSet[$value]) => null,
                            isset($pools[$i]) => $pools[$i][$value] ??= $value,
                            default => $value,
                        };
                    }
                    if (($inBatch + 1) % 4096 === 0) {
                        foreach ($pools as $i => $pool) {
                            if (count($pool) > ($inBatch + 1) / 2) {
                                unset($pools[$i]);
                            }
                        }
                    }
                    $count++;
                    $inBatch++;
                }
                $capped = $o->nMax !== null && $count >= $o->nMax;
                // Past any blank lines, to know whether records remain.
                while (! $capped && $records->valid() && $records->current() === null) {
                    $records->next();
                }
                $exhausted = ! $records->valid();
                $done = $exhausted || $capped;
                if ($exhausted && ! $capped && $records->getReturn() === true) {
                    $problems[] = 'row '.($count + 1).': unterminated quoted field at end of file';
                }
                $pools = [];
                if ($inBatch === 0 && ($size !== null || $batch > 0)) {
                    self::warn($problems);

                    break;
                }

                // Types: guessed from the first batch, then fixed for the rest.
                $sample = self::sampleRows($inBatch, $o->guessMax);
                $data = [];
                foreach ($names as $i => $name) {
                    $spec = $specs[$i];
                    if ($spec === 'skip') {
                        continue;
                    }
                    $values = $columns[$i];
                    $columns[$i] = null;
                    if ($spec === null) {
                        // readr reads the columns of a header-only file as text.
                        $type = $inBatch === 0 ? Type::String : $parser->guess(array_map(fn (int $row) => $values[$row], $sample));
                        $spec = $specs[$i] = ['type' => $type, 'number' => false, 'levels' => null, 'ordered' => false, 'guessed' => true];
                    }
                    [$column, $failed] = $parser->parse($values, $spec['type'], [
                        'grouping' => $spec['guessed'] ?? false,
                        'number' => $spec['number'],
                        'levels' => $spec['levels'],
                        'ordered' => $spec['ordered'],
                    ]);
                    if ($failed > 0) {
                        $problems[] = "column '{$name}': {$failed} value".($failed === 1 ? '' : 's')." could not be parsed as {$spec['type']->value}";
                    }
                    $data[$name] = $column;
                }

                if ($o->select !== null) {
                    $selected = [];
                    foreach ($o->select as $name) {
                        $selected[$name] = $data[$name]
                            ?? throw new InvalidArgumentException("col_select names unknown column '{$name}'.");
                    }
                    $data = $selected;
                }

                self::warn($problems);
                $batch++;
                yield $count - $inBatch + 1 => DataFrame::fromColumns($data);
            }
        } finally {
            $this->finish();
        }
    }

    /**
     * Raise one warning listing a read's problems, if it has any.
     *
     * @internal
     *
     * @param list<string> $problems
     */
    public static function warn(array $problems): void
    {
        if ($problems !== []) {
            $shown = array_slice($problems, 0, 5);
            $more = count($problems) > 5 ? ' (and '.(count($problems) - 5).' more)' : '';
            trigger_error('One or more parsing issues: '.implode('; ', $shown).$more.'.', E_USER_WARNING);
        }
    }

    /**
     * The rows readr (vroom) looks at to guess types: all of them when
     * there are at most $guessMax, otherwise $guessMax - 1 evenly spaced
     * rows plus the last.
     *
     * @return list<int>
     */
    private static function sampleRows(int $count, int $guessMax): array
    {
        if ($count <= $guessMax) {
            return $count === 0 ? [] : range(0, $count - 1);
        }
        $step = intdiv($count, $guessMax);
        $rows = [];
        for ($i = 0; $i < $guessMax - 1; $i++) {
            $rows[] = $i * $step;
        }
        $rows[] = $count - 1;

        return $rows;
    }

    /**
     * Bytes read from the input at a time. Tests shrink it to put block
     * boundaries everywhere.
     *
     * @internal
     */
    public static int $blockSize = 1 << 22;

    /** The first bytes, at most, that decide whether lines end in CR alone. */
    private const int WINDOW = 1 << 22;

    /**
     * Records, keyed by whether their fields may need trimming (with
     * trimWs, unquoted text without a space or tab at any field's edge
     * needs none); null for an empty line. Returns
     * whether the text ended inside an unclosed quote.
     *
     * The input is read a block at a time. Each tokenizer gets the text up to
     * the block's last newline and stops before a record that does not end
     * inside it (a quoted field running on into the next block), so a record
     * is never split and the whole input is never held.
     *
     * @return Generator<bool, list<?string>|null, mixed, bool>
     */
    private function records(): Generator
    {
        $o = $this->options;
        $line = 0;
        for ($i = 0; $i < $o->skip; $i++) {
            if (fgets($this->handle) === false) {
                return false;
            }
            $line++;
        }
        $fast = $o->comment === '' && $o->escapeDouble && ! $o->escapeBackslash && $o->quote !== '';
        // $pending holds bytes read but not yet tokenized: the first window,
        // which decides the line endings, or a CR held back in case an LF follows.
        $pending = '';
        $crOnly = null;
        $carry = '';
        $emitted = false;
        while (true) {
            $block = $pending.(string) fread($this->handle, self::$blockSize);
            $pending = '';
            $final = feof($this->handle);
            if ($crOnly === null) {
                if (strlen($block) < self::WINDOW && ! $final) {
                    $pending = $block;

                    continue;
                }
                // A UTF-8 byte-order mark (Excel writes one, even before a quoted header).
                if ($line === 0 && str_starts_with($block, "\xEF\xBB\xBF")) {
                    $block = substr($block, 3);
                }
                // Old Mac files end lines in CR alone: the first window has CRs and no LF.
                $crOnly = ! str_contains($block, "\n") && str_contains($block, "\r");
            }
            if (! $final && str_ends_with($block, "\r")) {
                $pending = "\r";
                $block = substr($block, 0, -1);
            }
            $text = $carry.($crOnly ? str_replace("\r", "\n", $block) : str_replace("\r\n", "\n", $block));
            unset($block);
            $rest = '';
            if (! $final) {
                $cut = strrpos($text, "\n");
                if ($cut === false) {
                    $carry = $text;

                    continue;
                }
                $rest = substr($text, $cut + 1);
                $text = substr($text, 0, $cut + 1);
            }
            [$unterminated, $consumed, $line, $emitted] = $fast
                ? yield from $this->fastRecords($text, $line, $final)
                : yield from $this->slowRecords($text, $line, $final, $emitted);
            if ($final) {
                return $unterminated;
            }
            $carry = substr($text, $consumed).$rest;
        }
    }

    /**
     * Without quotes, every line is a record and every delimiter separates
     * fields, so plain splitting gives the tokenizer's result.
     *
     * @return Generator<bool, list<?string>|null, mixed, array{bool, int, int, bool}>
     */
    private function fastRecords(string $text, int $line, bool $final): Generator
    {
        $o = $this->options;
        if (str_contains($text, $o->quote)) {
            return yield from $this->quotedRecords($text, $line, $final);
        }
        $ended = str_ends_with($text, "\n");
        $length = strlen($text);
        $trim = $o->trimWs && self::edgeSpace($text, $o->delimiter);
        $lines = explode("\n", $text);
        unset($text);
        if (end($lines) === '') {
            array_pop($lines);
        }
        $last = array_key_last($lines);
        foreach ($lines as $i => $content) {
            $line++;
            yield $trim => $this->record($content, explode($o->delimiter, $content), $ended || $i !== $last);
        }

        return [false, $length, $line, true];
    }

    /**
     * Records from text that has quotes, by vroom's (readr 2's) rules. A
     * field is quoted when it starts with the quote character. Within it,
     * every lone quote toggles quoting and is dropped, and a doubled quote is
     * a literal quote, so `"x "y" z"` reads as `x y z` and `"x "y",z"` as
     * `x y,z`. Quotes in an unquoted field are literal.
     *
     * Returns whether the text ended inside quotes, how many bytes were
     * read, the line count, and that a record was seen. Unless $final, a
     * record that does not end in this text is left for the next.
     *
     * @return Generator<bool, list<?string>|null, mixed, array{bool, int, int, bool}>
     */
    private function quotedRecords(string $text, int $line, bool $final): Generator
    {
        $o = $this->options;
        $delim = $o->delimiter;
        $quote = $o->quote;
        $length = strlen($text);
        $pos = 0;
        while ($pos < $length) {
            $line++;
            $start = $pos;
            $fields = [];
            $end = null;
            while (true) {
                if ($text[$pos] === $quote) {
                    $field = '';
                    $pos++;
                    $inQuotes = true;
                    while (true) {
                        if ($inQuotes) {
                            $next = strpos($text, $quote, $pos);
                            if ($next === false) {
                                if (! $final) {
                                    return [false, $start, $line - 1, true];
                                }
                                $fields[] = $field.substr($text, $pos);
                                yield $o->trimWs => $this->record(substr($text, $start), $fields, false);

                                return [true, $length, $line, true];
                            }
                            $field .= substr($text, $pos, $next - $pos);
                            $pos = $next + 1;
                            if ($pos < $length && $text[$pos] === $quote) {
                                $field .= $quote;
                                $pos++;
                            } else {
                                $inQuotes = false;
                            }

                            continue;
                        }
                        $run = strcspn($text, $delim.$quote."\n", $pos);
                        $field .= substr($text, $pos, $run);
                        $pos += $run;
                        if ($pos < $length && $text[$pos] === $quote) {
                            if ($pos + 1 < $length && $text[$pos + 1] === $quote) {
                                $field .= $quote;
                                $pos += 2;
                            } else {
                                $inQuotes = true;
                                $pos++;
                            }

                            continue;
                        }

                        break;
                    }
                } else {
                    $run = strcspn($text, $delim."\n", $pos);
                    $field = $this->unquoteTrimmed(substr($text, $pos, $run));
                    $pos += $run;
                }
                $fields[] = $field;
                if ($pos >= $length) {
                    $end = null;

                    break;
                }
                $end = $text[$pos++];
                if ($end === "\n") {
                    break;
                }
                if ($pos >= $length) {
                    // A delimiter at the very end leaves one empty field.
                    $fields[] = '';

                    break;
                }
            }
            if ($end !== "\n" && ! $final) {
                return [false, $start, $line - 1, true];
            }
            $raw = substr($text, $start, $pos - $start);
            yield $o->trimWs => $this->record($raw, $fields, $end === "\n");
        }

        return [false, $length, $line, true];
    }

    /**
     * Whether a space or tab touches the edge of some field in unquoted
     * text, so that trimming could change it. A tab delimiter is never part
     * of a field.
     */
    private static function edgeSpace(string $text, string $delimiter): bool
    {
        foreach (str_split(str_replace($delimiter, '', " \t")) as $w) {
            if (str_starts_with($text, $w) || str_ends_with($text, $w)
                || str_contains($text, $w.$delimiter) || str_contains($text, $delimiter.$w)
                || str_contains($text, $w."\n") || str_contains($text, "\n".$w)) {
                return true;
            }
        }

        return false;
    }

    /**
     * With trimWs, an unquoted field that starts with a quote once trimmed
     * is unquoted as vroom does: the first quote dropped, doubled quotes
     * made single, and other quotes dropped.
     */
    private function unquoteTrimmed(string $field): string
    {
        $quote = $this->options->quote;
        if (! $this->options->trimWs || $quote === '' || ! str_contains($field, $quote)) {
            return $field;
        }
        $trimmed = trim($field, " \t");
        if (! str_starts_with($trimmed, $quote)) {
            return $field;
        }

        return $this->options->escapeDouble
            ? strtr(substr($trimmed, 1), [$quote.$quote => $quote, $quote => ''])
            : self::dropClosingQuote(substr($trimmed, 1), $quote);
    }

    /**
     * A field from slowRecords(). Without escapeDouble, vroom keeps the
     * quotes inside a quoted field and drops only the opening and closing
     * ones, so slowRecords() keeps every inner quote and the closing one is
     * dropped here.
     */
    private function finishField(string $field, bool $quoted): string
    {
        if (! $quoted) {
            return $this->unquoteTrimmed($field);
        }
        if ($this->options->escapeDouble) {
            return $field;
        }

        return self::dropClosingQuote($this->options->trimWs ? trim($field, " \t") : $field, $this->options->quote);
    }

    private static function dropClosingQuote(string $field, string $quote): string
    {
        return str_ends_with($field, $quote) ? substr($field, 0, -1) : $field;
    }

    /**
     * A character-level reader for comments, backslash escapes, and files
     * without quoting. Quotes follow the rules in quotedRecords().
     *
     * Returns what quotedRecords() returns; $emitted carries whether a
     * record has been seen in an earlier block.
     *
     * @return Generator<bool, list<?string>|null, mixed, array{bool, int, int, bool}>
     */
    private function slowRecords(string $text, int $line, bool $final, bool $emitted): Generator
    {
        $o = $this->options;
        $length = strlen($text);
        $pos = 0;
        $delim = $o->delimiter;
        $quote = $o->quote;
        $comment = $o->comment;

        while ($pos < $length) {
            $line++;
            $start = $pos;
            $fields = [];
            $field = '';
            $quoted = false;
            $inQuotes = false;

            // A line that starts with the comment string is dropped entirely.
            // Before the first record readr looks past leading whitespace for
            // it; after one, such a line is a record holding that whitespace.
            $at = $pos;
            if (! $emitted) {
                while ($at < $length && ($text[$at] === ' ' || $text[$at] === "\t")) {
                    $at++;
                }
            }
            if ($comment !== '' && $at < $length && substr_compare($text, $comment, $at, strlen($comment)) === 0) {
                $end = strpos($text, "\n", $at);
                $pos = $end === false ? $length : $end + 1;
                $line--;

                continue;
            }

            $terminated = false;
            while ($pos < $length) {
                $c = $text[$pos];
                if ($inQuotes) {
                    if ($o->escapeBackslash && $c === '\\' && $pos + 1 < $length) {
                        $field .= $text[$pos + 1];
                        $pos += 2;

                        continue;
                    }
                    if ($c === $quote) {
                        if ($o->escapeDouble && $pos + 1 < $length && $text[$pos + 1] === $quote) {
                            $field .= $quote;
                            $pos += 2;

                            continue;
                        }
                        $inQuotes = false;
                        if (! $o->escapeDouble) {
                            $field .= $quote;
                        }
                        $pos++;

                        continue;
                    }
                    $field .= $c;
                    $pos++;

                    continue;
                }

                if ($c === "\n") {
                    $pos++;
                    $terminated = true;

                    break;
                }
                if ($quote !== '' && $c === $quote && ($quoted || $field === '')) {
                    // Opens a quoted field, or toggles quoting inside one.
                    if ($quoted && $o->escapeDouble && $pos + 1 < $length && $text[$pos + 1] === $quote) {
                        $field .= $quote;
                        $pos += 2;

                        continue;
                    }
                    if ($quoted && ! $o->escapeDouble) {
                        $field .= $quote;
                    }
                    $quoted = $inQuotes = true;
                    $pos++;

                    continue;
                }
                if ($comment !== '' && substr_compare($text, $comment, $pos, strlen($comment)) === 0) {
                    $end = strpos($text, "\n", $pos);
                    $pos = $end === false ? $length : $end + 1;
                    $terminated = $end !== false;

                    break;
                }
                if ($c === $delim) {
                    $fields[] = $this->finishField($field, $quoted);
                    $field = '';
                    $quoted = false;
                    $pos++;

                    continue;
                }
                if ($o->escapeBackslash && $c === '\\' && $pos + 1 < $length) {
                    $field .= $text[$pos + 1];
                    $pos += 2;

                    continue;
                }
                $field .= $c;
                $pos++;
            }

            if (! $terminated && ! $final) {
                return [false, $start, $line - 1, $emitted];
            }
            $fields[] = $this->finishField($field, $quoted);
            $raw = substr($text, $start, $pos - $start);
            $record = $this->record($raw, $fields, str_ends_with($raw, "\n"));
            $emitted = $emitted || $record !== null;
            yield $o->trimWs => $record;
            if ($inQuotes) {
                return [true, $length, $line, $emitted];
            }
        }

        return [false, $length, $line, $emitted];
    }

    /**
     * One line's record, or null for a blank line that skipEmptyRows drops.
     *
     * readr decides blankness from the raw line rather than from the parsed
     * fields, so a quoted empty string is a field holding data while a line of
     * spaces or tabs is blank whatever trimWs says. It also never drops a last
     * line the file left unterminated.
     *
     * @param  list<?string>  $fields
     * @return list<?string>|null
     */
    private function record(string $raw, array $fields, bool $terminated): ?array
    {
        return $terminated && $this->options->skipEmptyRows && self::blank($raw) ? null : $fields;
    }

    /** A line holding only spaces, tabs or carriage returns is empty, as in readr. */
    private static function blank(string $line): bool
    {
        return trim($line, " \t\r\n") === '';
    }

    private function finish(): void
    {
        if ($this->close && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
