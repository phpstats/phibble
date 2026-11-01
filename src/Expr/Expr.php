<?php

declare(strict_types=1);

namespace Phibble\Expr;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * An inspectable, serialisable column expression, evaluated per group with
 * dplyr's vector semantics. Backends (in-memory, SQL, Polars) all read the
 * same tree: Col, Lit, Call, plus Desc for sort keys.
 *
 *     col('price')->mul(1.1)->gt(100)->and(col('brand')->isIn(['a', 'b']))
 */
abstract readonly class Expr
{
    /**
     * JSON-friendly description of the expression tree.
     *
     * Expr::fromArray() rebuilds the expression from it.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Names of the columns the expression reads, in order of first use.
     *
     * @return list<string>
     */
    abstract public function columns(): array;

    /**
     * Expression rebuilt from the array form that toArray() returns.
     *
     * @param  array<string, mixed>  $node  A node as toArray() gives it.
     * @throws \InvalidArgumentException When a node has an unknown type, a literal is invalid, or a call does not fit the function registry.
     */
    public static function fromArray(array $node): self
    {
        return match ($node['type'] ?? null) {
            'col' => new Col($node['name']),
            'lit' => Lit::fromArray($node),
            'call' => new Call(
                $node['fn'],
                array_map(self::fromArray(...), $node['args'] ?? []),
                array_map(self::fromArray(...), $node['named'] ?? []),
            ),
            'desc' => new Desc(self::fromArray($node['expr'])),
            default => throw new InvalidArgumentException('Unknown expression node: '.json_encode($node)),
        };
    }

    /**
     * PHP value wrapped as a literal expression; an expression is returned unchanged.
     *
     * @param  mixed  $value  An expression, or a value lit() accepts.
     * @throws \InvalidArgumentException When the value is not one lit() accepts.
     */
    public static function of(mixed $value): self
    {
        return $value instanceof self ? $value : new Lit($value);
    }

    /**
     * Sum of this expression and $other.
     *
     * NA gives NA. Adding a period such as days(1) to a date or datetime uses clock arithmetic, and adding seconds or a time to a time gives a time.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function add(mixed $other): Call
    {
        return new Call('+', [$this, self::of($other)]);
    }

    /**
     * Difference of this expression and $other.
     *
     * NA gives NA. Subtracting a date from a date gives days, a datetime from a datetime gives seconds, and a period from a date or datetime uses clock arithmetic.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function sub(mixed $other): Call
    {
        return new Call('-', [$this, self::of($other)]);
    }

    /**
     * Product of this expression and $other.
     *
     * NA gives NA. A period or time multiplied by a number is scaled.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function mul(mixed $other): Call
    {
        return new Call('*', [$this, self::of($other)]);
    }

    /**
     * Quotient of this expression and $other, always a float.
     *
     * NA gives NA. Division by zero gives Inf or -Inf, and 0 / 0 gives NaN, as in R.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function div(mixed $other): Call
    {
        return new Call('/', [$this, self::of($other)]);
    }

    /**
     * Remainder of this expression divided by $other, with the sign of $other.
     *
     * R's %% operator: the remainder after floored division. NA gives NA. An integer remainder by zero is NA, and a float remainder by zero NaN.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function mod(mixed $other): Call
    {
        return new Call('%%', [$this, self::of($other)]);
    }

    /**
     * This expression raised to the power $other, as a float.
     *
     * R's ^ operator. NA gives NA.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function pow(mixed $other): Call
    {
        return new Call('^', [$this, self::of($other)]);
    }

    /**
     * Negation of this expression.
     *
     * NA gives NA.
     */
    public function neg(): Call
    {
        return new Call('neg', [$this]);
    }

    /**
     * Whether this expression equals $other.
     *
     * R's == operator. NA on either side gives NA. Numbers and logicals compare numerically, dates and datetimes by instant, and other values as strings.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function eq(mixed $other): Call
    {
        return new Call('==', [$this, self::of($other)]);
    }

    /**
     * Whether this expression differs from $other.
     *
     * R's != operator. NA on either side gives NA. Numbers and logicals compare numerically, dates and datetimes by instant, and other values as strings.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function ne(mixed $other): Call
    {
        return new Call('!=', [$this, self::of($other)]);
    }

    /**
     * Whether this expression is less than $other.
     *
     * R's < operator. NA on either side gives NA. Numbers and logicals compare numerically, dates and datetimes by instant, and other values as strings, byte by byte.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function lt(mixed $other): Call
    {
        return new Call('<', [$this, self::of($other)]);
    }

    /**
     * Whether this expression is less than or equal to $other.
     *
     * R's <= operator. NA on either side gives NA. Numbers and logicals compare numerically, dates and datetimes by instant, and other values as strings, byte by byte.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function le(mixed $other): Call
    {
        return new Call('<=', [$this, self::of($other)]);
    }

    /**
     * Whether this expression is greater than $other.
     *
     * R's > operator. NA on either side gives NA. Numbers and logicals compare numerically, dates and datetimes by instant, and other values as strings, byte by byte.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function gt(mixed $other): Call
    {
        return new Call('>', [$this, self::of($other)]);
    }

    /**
     * Whether this expression is greater than or equal to $other.
     *
     * R's >= operator. NA on either side gives NA. Numbers and logicals compare numerically, dates and datetimes by instant, and other values as strings, byte by byte.
     *
     * @param  mixed  $other  An expression or literal.
     */
    public function ge(mixed $other): Call
    {
        return new Call('>=', [$this, self::of($other)]);
    }

    /**
     * Logical AND of this expression and $other.
     *
     * R's & operator, with three-valued logic: false wins over NA, and NA with true gives NA.
     *
     * @param  mixed  $other  A logical expression or literal.
     */
    public function and(mixed $other): Call
    {
        return new Call('&', [$this, self::of($other)]);
    }

    /**
     * Logical OR of this expression and $other.
     *
     * R's | operator, with three-valued logic: true wins over NA, and NA with false gives NA.
     *
     * @param  mixed  $other  A logical expression or literal.
     */
    public function or(mixed $other): Call
    {
        return new Call('|', [$this, self::of($other)]);
    }

    /**
     * Logical negation of this expression.
     *
     * R's ! operator. NA gives NA.
     */
    public function not(): Call
    {
        return new Call('!', [$this]);
    }

    /**
     * Whether each value is NA.
     *
     * NaN counts as NA. Equivalent to is_na().
     */
    public function isNa(): Call
    {
        return new Call('is_na', [$this]);
    }

    /**
     * Whether each value is one of the given values.
     *
     * R's %in% operator. NA matches only when the list holds null. The result is never NA.
     *
     * @param  list<mixed>  $values  The values to match, each null or a scalar.
     * @throws \InvalidArgumentException When a value is not null or a scalar.
     */
    public function isIn(array $values): Call
    {
        return new Call('in', [$this, new Lit(array_values($values))]);
    }

    /**
     * Whether each value lies between $low and $high, inclusive.
     *
     * NA in the value or either bound gives NA. Equivalent to between().
     *
     * @param  mixed  $low  The lower bound.
     * @param  mixed  $high  The upper bound.
     */
    public function between(mixed $low, mixed $high): Call
    {
        return new Call('between', [$this, self::of($low), self::of($high)]);
    }

    /**
     * Descending sort key on this expression, for arrange(), the first argument of a ranking function, or order_by.
     *
     * Equivalent to desc().
     */
    public function desc(): Desc
    {
        return new Desc($this);
    }
}
