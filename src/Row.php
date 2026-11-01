<?php

declare(strict_types=1);

namespace Phibble;

use ArrayAccess;
use LogicException;
use Phibble\Exception\ColumnNotFound;

/**
 * One row of a table, read-only. Read values as $row->price or $row['price'].
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class Row implements ArrayAccess
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    /**
     * One value by column name. $row->name and $row['name'] read the same value.
     *
     * @throws \Phibble\Exception\ColumnNotFound When the column does not exist.
     */
    public function get(string $name): mixed
    {
        if (! array_key_exists($name, $this->values)) {
            throw ColumnNotFound::named($name, array_keys($this->values));
        }

        return $this->values[$name];
    }

    /** Whether the row has the column. */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    /**
     * The row as an associative array of column name => value.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Rows are read-only. Use mutate() to change values.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Rows are read-only. Use select() to drop columns.');
    }
}
