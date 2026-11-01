<?php

declare(strict_types=1);

/*
 * Expression helpers. Each builds a node of the Phibble\Expr tree:
 *
 *     use function Phibble\Expr\{col, mean, n};
 *
 *     $df->groupBy(['g'])->summarise(['avg' => mean(col('x')), 'n' => n()]);
 *
 * Names follow dplyr. Some (min, max, round, floor, abs, ...) shadow PHP
 * built-ins inside this namespace only, so built-ins are called with a
 * leading backslash here. Options are named arguments: mean(col('x'), na_rm: true).
 */

namespace Phibble\Expr;

/** @internal */
function call_with(string $fn, array $args): Call
{
    $positional = [];
    $named = [];
    foreach ($args as $key => $value) {
        if (\is_string($key)) {
            $named[$key] = Expr::of($value);
        } else {
            $positional[] = Expr::of($value);
        }
    }

    return new Call($fn, $positional, $named);
}

/**
 * Reference to the column with the given name.
 *
 * A plain PHP string passed to a helper is a literal; col() is how an expression reads a column.
 *
 * @param  string  $name  The column name.
 */
function col(string $name): Col
{
    return new Col($name);
}

/**
 * Literal value as an expression.
 *
 * A date or datetime literal is typed as a date when its time of day is midnight, and as a datetime otherwise.
 *
 * @param  mixed  $value  Null, a bool, int, float, or string, a DateTimeInterface, a Phibble\Time, or a list of scalars.
 * @throws \InvalidArgumentException When the value is of another type, or a list holds a value that is not a scalar.
 */
function lit(mixed $value): Lit
{
    return new Lit($value);
}

/**
 * Call to any registered function by name, such as call('str_sub', col('s'), 1, 3).
 *
 * String keys in $args become named options; other arguments are positional. Values that are not expressions are wrapped as literals.
 *
 * @param  string  $fn  The registered function name, as listed in Functions::REGISTRY.
 * @param  mixed  ...$args  Positional arguments, then named options.
 * @throws \InvalidArgumentException When the function is not registered, the number of positional arguments is out of range, or a named option is not allowed.
 */
function call(string $fn, mixed ...$args): Call
{
    return call_with($fn, $args);
}

/**
 * Descending sort key for arrange(), the first argument of a ranking function, or order_by.
 *
 * desc() is rejected anywhere else in an expression.
 *
 * @param  Expr|string  $expr  An expression, or a column name.
 */
function desc(Expr|string $expr): Desc
{
    return new Desc(\is_string($expr) ? new Col($expr) : $expr);
}

// Scalar functions
/**
 * Whether each value is NA.
 *
 * NaN counts as NA.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 */
function is_na(mixed $x): Call { return call_with('is_na', [$x]); }
/**
 * Value of $true where the condition holds and of $false where it does not.
 *
 * @param  mixed  $condition  A logical expression.
 * @param  mixed  $true  The value where the condition is true.
 * @param  mixed  $false  The value where the condition is false.
 * @param  mixed  ...$options  Named options: missing (mixed, default NA) is the value where the condition is NA.
 */
function if_else(mixed $condition, mixed $true, mixed $false, mixed ...$options): Call { return call_with('if_else', [$condition, $true, $false, ...$options]); }
/**
 * Value paired with the first condition that holds, as case_when(cond1, value1, cond2, value2, ..., default: value).
 *
 * Conditions are tested in order per row; an NA condition does not match.
 *
 * @param  mixed  ...$args  Condition and value pairs, then named options: default (mixed, default NA) is the value where no condition holds.
 * @throws \InvalidArgumentException When the positional arguments do not form condition and value pairs.
 */
function case_when(mixed ...$args): Call { return call_with('case_when', $args); }
/**
 * First value that is not NA among the arguments, row by row.
 *
 * NaN is a value here: it is not skipped.
 *
 * @param  mixed  ...$args  One or more expressions or literals.
 */
function coalesce(mixed ...$args): Call { return call_with('coalesce', $args); }
/**
 * Whether each value lies between $low and $high, inclusive.
 *
 * The result is NA where $x, $low, or $high is NA.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  $low  The lower bound.
 * @param  mixed  $high  The upper bound.
 */
function between(mixed $x, mixed $low, mixed $high): Call { return call_with('between', [$x, $low, $high]); }
/**
 * Values of $x, with NA where $x equals $y.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  $y  The value to replace with NA.
 */
function na_if(mixed $x, mixed $y): Call { return call_with('na_if', [$x, $y]); }
/**
 * Whether $x and $y are equal within a tolerance.
 *
 * The result is NA where either value is NA.
 *
 * @param  mixed  $x  A numeric expression or literal.
 * @param  mixed  $y  A numeric expression or literal.
 * @param  mixed  ...$options  Named options: tol (float, default sqrt(PHP_FLOAT_EPSILON), about 1.5e-8) is the tolerance; an absolute difference below it counts as equal.
 */
function near(mixed $x, mixed $y, mixed ...$options): Call { return call_with('near', [$x, $y, ...$options]); }
/**
 * Absolute value.
 *
 * @param  mixed  $x  A numeric expression or literal.
 */
function abs(mixed $x): Call { return call_with('abs', [$x]); }
/**
 * Square root.
 *
 * The square root of a negative number is NaN.
 *
 * @param  mixed  $x  A numeric expression or literal.
 */
function sqrt(mixed $x): Call { return call_with('sqrt', [$x]); }
/**
 * Exponential function, e raised to the power $x.
 *
 * @param  mixed  $x  A numeric expression or literal.
 */
function exp(mixed $x): Call { return call_with('exp', [$x]); }
/**
 * Logarithm, natural by default.
 *
 * The logarithm of 0 is -Inf, and of a negative number NaN.
 *
 * @param  mixed  $x  A numeric expression or literal.
 * @param  mixed  ...$options  Named options: base (float, default e) is the base of the logarithm.
 */
function log(mixed $x, mixed ...$options): Call { return call_with('log', [$x, ...$options]); }
/**
 * Base-10 logarithm.
 *
 * The logarithm of 0 is -Inf, and of a negative number NaN.
 *
 * @param  mixed  $x  A numeric expression or literal.
 */
function log10(mixed $x): Call { return call_with('log10', [$x]); }
/**
 * Value rounded to a number of decimal digits.
 *
 * Rounding follows R 4.0 and later: the candidate nearer the stored double wins, and exact ties go to the even digit, so round(2.675, digits: 2) is 2.67. Integers are returned unchanged when digits is 0 or more. SQL backends evaluate it in memory.
 *
 * @param  mixed  $x  A numeric expression or literal.
 * @param  mixed  ...$options  Named options: digits (int, default 0) is the number of decimal places; a negative value rounds to tens, hundreds, and so on.
 */
function round(mixed $x, mixed ...$options): Call { return call_with('round', [$x, ...$options]); }
/**
 * Largest whole number not greater than the value, as a float.
 *
 * @param  mixed  $x  A numeric expression or literal.
 */
function floor(mixed $x): Call { return call_with('floor', [$x]); }
/**
 * Smallest whole number not less than the value, as a float.
 *
 * @param  mixed  $x  A numeric expression or literal.
 */
function ceiling(mixed $x): Call { return call_with('ceiling', [$x]); }
/**
 * Values converted to integers.
 *
 * R's as.integer().
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 */
function as_int(mixed $x): Call { return call_with('as_int', [$x]); }
/**
 * Values converted to floats.
 *
 * R's as.double().
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 */
function as_float(mixed $x): Call { return call_with('as_float', [$x]); }
/**
 * Values converted to strings.
 *
 * R's as.character(). SQL backends evaluate it in memory.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 */
function as_string(mixed $x): Call { return call_with('as_string', [$x]); }
/**
 * Values converted to logicals.
 *
 * R's as.logical(). SQL backends evaluate it in memory.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 */
function as_bool(mixed $x): Call { return call_with('as_bool', [$x]); }
/**
 * String in lower case.
 *
 * Alias of str_to_lower().
 *
 * @param  mixed  $x  A string expression or literal.
 */
function str_lower(mixed $x): Call { return call_with('str_lower', [$x]); }
/**
 * String in upper case.
 *
 * Alias of str_to_upper().
 *
 * @param  mixed  $x  A string expression or literal.
 */
function str_upper(mixed $x): Call { return call_with('str_upper', [$x]); }
/**
 * Number of characters in the string.
 *
 * Characters are Unicode code points. NA gives NA.
 *
 * @param  mixed  $x  A string expression or literal.
 */
function str_length(mixed $x): Call { return call_with('str_length', [$x]); }
/**
 * String with leading and trailing white space removed.
 *
 * All Unicode white space is removed, as in stringr. SQL backends evaluate it in memory.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  ...$options  Named options: side (string, default 'both') is 'left', 'right', or 'both'.
 */
function str_trim(mixed $x, mixed ...$options): Call { return call_with('str_trim', [$x, ...$options]); }
/**
 * Substring from position $start to position $end, inclusive.
 *
 * Positions are 1-based characters; negative positions count from the end, so -1 is the last character. The result is NA where any argument is NA.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $start  The first position.
 * @param  mixed  $end  The last position; the default, -1, is the end of the string.
 */
function str_sub(mixed $x, mixed $start, mixed $end = -1): Call { return call_with('str_sub', [$x, $start, $end]); }
/**
 * Whether the string contains a match for the pattern.
 *
 * A plain string pattern is a regular expression; wrap it in fixed() to match literal text. NA gives NA, as does an empty fixed() pattern.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 * @param  mixed  ...$options  Named options: negate (bool, default false) inverts the result.
 */
function str_detect(mixed $x, mixed $pattern, mixed ...$options): Call { return call_with('str_detect', [$x, $pattern, ...$options]); }
/**
 * Whether the string starts with a match for the pattern.
 *
 * A plain string pattern is a regular expression; wrap it in fixed() to match literal text. NA gives NA, as does an empty fixed() pattern.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 * @param  mixed  ...$options  Named options: negate (bool, default false) inverts the result.
 */
function str_starts(mixed $x, mixed $pattern, mixed ...$options): Call { return call_with('str_starts', [$x, $pattern, ...$options]); }
/**
 * Whether the string ends with a match for the pattern.
 *
 * A plain string pattern is a regular expression; wrap it in fixed() to match literal text. NA gives NA, as does an empty fixed() pattern.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 * @param  mixed  ...$options  Named options: negate (bool, default false) inverts the result.
 */
function str_ends(mixed $x, mixed $pattern, mixed ...$options): Call { return call_with('str_ends', [$x, $pattern, ...$options]); }
/**
 * String with the first match of the pattern replaced.
 *
 * In a regular-expression replacement, \1 to \9 refer to capture groups, and $ is literal. An NA replacement gives NA where the pattern matches.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 * @param  mixed  $replacement  The replacement string.
 */
function str_replace(mixed $x, mixed $pattern, mixed $replacement): Call { return call_with('str_replace', [$x, $pattern, $replacement]); }
/**
 * String with every match of the pattern replaced.
 *
 * In a regular-expression replacement, \1 to \9 refer to capture groups, and $ is literal. An NA replacement gives NA where the pattern matches.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 * @param  mixed  $replacement  The replacement string.
 */
function str_replace_all(mixed $x, mixed $pattern, mixed $replacement): Call { return call_with('str_replace_all', [$x, $pattern, $replacement]); }
/**
 * Arguments joined into one string with no separator.
 *
 * NA is written as "NA".
 *
 * @param  mixed  ...$args  One or more expressions or literals.
 */
function paste0(mixed ...$args): Call { return call_with('paste0', $args); }
/**
 * Arguments joined into one string with a separator.
 *
 * NA is written as "NA".
 *
 * @param  mixed  ...$args  One or more expressions or literals, then named options: sep (string, default ' ') is the separator.
 */
function paste(mixed ...$args): Call { return call_with('paste', $args); }

// Aggregates
/**
 * Number of rows in the group.
 */
function n(): Call { return call_with('n', []); }
/**
 * Sum of the values.
 *
 * The result is NA when a value is NA, unless na_rm is true. The sum is an integer when every value is an integer or logical, and 0 for no values. The sum of times is a time.
 *
 * @param  mixed  $x  A numeric expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 * @throws \InvalidArgumentException At evaluation, when the values are dates or datetimes.
 */
function sum(mixed $x, mixed ...$options): Call { return call_with('sum', [$x, ...$options]); }
/**
 * Mean of the values.
 *
 * The result is NA when a value is NA, unless na_rm is true, and NaN for no values. The mean of dates is a date, of datetimes a datetime, and of times a time.
 *
 * @param  mixed  $x  A numeric or temporal expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function mean(mixed $x, mixed ...$options): Call { return call_with('mean', [$x, ...$options]); }
/**
 * Median of the values.
 *
 * The result is NA when a value is NA, unless na_rm is true, and NA for no values. The median of dates is a date, of datetimes a datetime, and of times a time.
 *
 * @param  mixed  $x  A numeric or temporal expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function median(mixed $x, mixed ...$options): Call { return call_with('median', [$x, ...$options]); }
/**
 * Smallest of the values.
 *
 * The result is NA when a value is NA, unless na_rm is true, NaN when a value is NaN, and Inf for no values. Strings compare byte by byte.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function min(mixed $x, mixed ...$options): Call { return call_with('min', [$x, ...$options]); }
/**
 * Largest of the values.
 *
 * The result is NA when a value is NA, unless na_rm is true, NaN when a value is NaN, and -Inf for no values. Strings compare byte by byte.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function max(mixed $x, mixed ...$options): Call { return call_with('max', [$x, ...$options]); }
/**
 * Sample standard deviation of the values.
 *
 * The divisor is n - 1, and the result is NA for fewer than two values. The result is NA when a value is NA, unless na_rm is true. Dates are measured in days, and datetimes and times in seconds.
 *
 * @param  mixed  $x  A numeric or temporal expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function sd(mixed $x, mixed ...$options): Call { return call_with('sd', [$x, ...$options]); }
/**
 * Sample variance of the values.
 *
 * R's var(); the trailing underscore avoids PHP's reserved word. The divisor is n - 1, and the result is NA for fewer than two values. The result is NA when a value is NA, unless na_rm is true. Dates are measured in days, and datetimes and times in seconds.
 *
 * @param  mixed  $x  A numeric or temporal expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function var_(mixed $x, mixed ...$options): Call { return call_with('var', [$x, ...$options]); }
/**
 * Sample quantile of the values at probability $p.
 *
 * Uses R's default method (type 7), interpolating between order statistics. The quantile of datetimes is a datetime, and of times a time.
 *
 * @param  mixed  $x  A numeric or temporal expression.
 * @param  mixed  $p  The probability, from 0 to 1.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 * @throws \InvalidArgumentException At evaluation, when a value is NA and na_rm is false, or when the values are dates.
 */
function quantile(mixed $x, mixed $p, mixed ...$options): Call { return call_with('quantile', [$x, $p, ...$options]); }
/**
 * Interquartile range of the values: the 0.75 quantile minus the 0.25 quantile.
 *
 * R's IQR(). Quantiles use R's default method (type 7). The result is NA for no values. Dates are measured in days, and datetimes and times in seconds.
 *
 * @param  mixed  $x  A numeric or temporal expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 * @throws \InvalidArgumentException At evaluation, when a value is NA and na_rm is false.
 */
function iqr(mixed $x, mixed ...$options): Call { return call_with('iqr', [$x, ...$options]); }
/**
 * Number of distinct values, or of distinct combinations across several arguments.
 *
 * NA counts as a distinct value unless na_rm is true.
 *
 * @param  mixed  ...$args  One or more expressions, then named options: na_rm (bool, default false) skips rows where any argument is NA.
 */
function n_distinct(mixed ...$args): Call { return call_with('n_distinct', $args); }
/**
 * First value in the group.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  ...$options  Named options: default (mixed, default NA) is returned when the group has no values; order_by (expression, default row order) sorts the values first, NA last, and accepts desc(); na_rm (bool, default false) drops NA first.
 */
function first(mixed $x, mixed ...$options): Call { return call_with('first', [$x, ...$options]); }
/**
 * Last value in the group.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  ...$options  Named options: default (mixed, default NA) is returned when the group has no values; order_by (expression, default row order) sorts the values first, NA last, and accepts desc(); na_rm (bool, default false) drops NA first.
 */
function last(mixed $x, mixed ...$options): Call { return call_with('last', [$x, ...$options]); }
/**
 * Value at position $n in the group.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  $n  The 1-based position; a negative position counts from the end.
 * @param  mixed  ...$options  Named options: default (mixed, default NA) is returned when the position is 0 or out of range; order_by (expression, default row order) sorts the values first, NA last, and accepts desc(); na_rm (bool, default false) drops NA first.
 */
function nth(mixed $x, mixed $n, mixed ...$options): Call { return call_with('nth', [$x, $n, ...$options]); }
/**
 * Whether any value is true.
 *
 * With NA present and na_rm false, the result is true when some value is true, and NA otherwise. The result is false for no values.
 *
 * @param  mixed  $x  A logical expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function any(mixed $x, mixed ...$options): Call { return call_with('any', [$x, ...$options]); }
/**
 * Whether every value is true.
 *
 * With NA present and na_rm false, the result is false when some value is false, and NA otherwise. The result is true for no values.
 *
 * @param  mixed  $x  A logical expression.
 * @param  mixed  ...$options  Named options: na_rm (bool, default false) drops NA first.
 */
function all(mixed $x, mixed ...$options): Call { return call_with('all', [$x, ...$options]); }

// Window functions
/**
 * Value from n rows earlier in the group.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  ...$options  Named options: n (int, default 1) is the offset; default (mixed, default NA) fills rows with no earlier row; order_by (expression, default row order) sets the order, NA last, and accepts desc().
 */
function lag(mixed $x, mixed ...$options): Call { return call_with('lag', [$x, ...$options]); }
/**
 * Value from n rows later in the group.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 * @param  mixed  ...$options  Named options: n (int, default 1) is the offset; default (mixed, default NA) fills rows with no later row; order_by (expression, default row order) sets the order, NA last, and accepts desc().
 */
function lead(mixed $x, mixed ...$options): Call { return call_with('lead', [$x, ...$options]); }
/**
 * Row number within the group, or the rank of each value with ties broken by row order.
 *
 * With no argument, rows are numbered 1 to n in their order. With an argument, NA values get NA, and a factor column ranks by level order.
 *
 * @param  mixed  ...$x  Optionally one expression to rank by, or desc() of one.
 */
function row_number(mixed ...$x): Call { return call_with('row_number', $x); }
/**
 * Rank of each value, ties sharing the lowest rank, with gaps after ties.
 *
 * NA values get NA. A factor column ranks by level order.
 *
 * @param  mixed  $x  An expression to rank by, or desc() of one.
 */
function min_rank(mixed $x): Call { return call_with('min_rank', [$x]); }
/**
 * Rank of each value, ties sharing a rank, with no gaps after ties.
 *
 * NA values get NA. A factor column ranks by level order.
 *
 * @param  mixed  $x  An expression to rank by, or desc() of one.
 */
function dense_rank(mixed $x): Call { return call_with('dense_rank', [$x]); }
/**
 * Minimum rank rescaled to the range 0 to 1: (min_rank - 1) / (m - 1), where m is the number of non-NA values.
 *
 * NA values get NA. With one non-NA value, the result is NaN.
 *
 * @param  mixed  $x  An expression to rank by, or desc() of one.
 */
function percent_rank(mixed $x): Call { return call_with('percent_rank', [$x]); }
/**
 * Cumulative distribution: the share of non-NA values less than or equal to each value.
 *
 * NA values get NA.
 *
 * @param  mixed  $x  An expression to rank by, or desc() of one.
 */
function cume_dist(mixed $x): Call { return call_with('cume_dist', [$x]); }
/**
 * Bucket number from 1 to n, splitting the ordered values into n groups as evenly as possible.
 *
 * Earlier buckets take the extra rows when the sizes differ. NA values get NA. With no expression, rows are bucketed in row order.
 *
 * @param  mixed  ...$args  Optionally one expression to order by, or desc() of one, then named options: n (int, required) is the number of buckets.
 * @throws \InvalidArgumentException When n is not given.
 */
function ntile(mixed ...$args): Call { return call_with('ntile', $args); }
/**
 * Cumulative sum in row order.
 *
 * Once a value is NA, every later result is NA.
 *
 * @param  mixed  $x  A numeric expression.
 */
function cumsum(mixed $x): Call { return call_with('cumsum', [$x]); }
/**
 * Cumulative product in row order.
 *
 * Once a value is NA, every later result is NA. SQL backends evaluate it in memory.
 *
 * @param  mixed  $x  A numeric expression.
 */
function cumprod(mixed $x): Call { return call_with('cumprod', [$x]); }
/**
 * Cumulative mean in row order.
 *
 * Once a value is NA, every later result is NA.
 *
 * @param  mixed  $x  A numeric expression.
 */
function cummean(mixed $x): Call { return call_with('cummean', [$x]); }
/**
 * Cumulative minimum in row order.
 *
 * Once a value is NA, every later result is NA.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 */
function cummin(mixed $x): Call { return call_with('cummin', [$x]); }
/**
 * Cumulative maximum in row order.
 *
 * Once a value is NA, every later result is NA.
 *
 * @param  mixed  $x  A column expression, such as col('x'), or a literal.
 */
function cummax(mixed $x): Call { return call_with('cummax', [$x]); }
/**
 * Whether every value so far, in row order, is true.
 *
 * Once a value is false, every later result is false. After an NA, results are NA until a false value appears.
 *
 * @param  mixed  $x  A logical expression.
 */
function cumall(mixed $x): Call { return call_with('cumall', [$x]); }
/**
 * Whether any value so far, in row order, is true.
 *
 * Once a value is true, every later result is true. After an NA, results are NA until a true value appears.
 *
 * @param  mixed  $x  A logical expression.
 */
function cumany(mixed $x): Call { return call_with('cumany', [$x]); }
/**
 * Run identifier that starts at 1 and increases each time the value, or combination of values, changes from the previous row.
 *
 * SQL backends evaluate it in memory.
 *
 * @param  mixed  ...$args  One or more expressions.
 */
function consecutive_id(mixed ...$args): Call { return call_with('consecutive_id', $args); }

// Patterns (stringr): a plain string is a regular expression
/**
 * Pattern that matches its text literally.
 *
 * An empty fixed() pattern gives NA in the functions that take it.
 *
 * @param  mixed  $pattern  The literal text to match.
 */
function fixed(mixed $pattern): Call { return call_with('fixed', [$pattern]); }
/**
 * Regular-expression pattern with options.
 *
 * Patterns are PCRE and match by Unicode code point.
 *
 * @param  mixed  $pattern  The regular expression, without delimiters.
 * @param  mixed  ...$options  Named options: ignore_case (bool, default false) matches without regard to case.
 */
function regex(mixed $pattern, mixed ...$options): Call { return call_with('regex', [$pattern, ...$options]); }

// stringr
/**
 * Number of matches of the pattern in the string.
 *
 * NA gives NA, as does an empty fixed() pattern.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 */
function str_count(mixed $x, mixed $pattern): Call { return call_with('str_count', [$x, $pattern]); }
/**
 * First match of the pattern in the string.
 *
 * The result is NA when there is no match. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 */
function str_extract(mixed $x, mixed $pattern): Call { return call_with('str_extract', [$x, $pattern]); }
/**
 * Every match of the pattern in the string, as a list.
 *
 * NA gives a list holding one NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 */
function str_extract_all(mixed $x, mixed $pattern): Call { return call_with('str_extract_all', [$x, $pattern]); }
/**
 * Pieces of the string split at each match of the pattern, as a list.
 *
 * An empty fixed() pattern splits into characters. NA gives a list holding one NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 */
function str_split(mixed $x, mixed $pattern): Call { return call_with('str_split', [$x, $pattern]); }
/**
 * Piece $i of the string split at each match of the pattern.
 *
 * The result is NA when $i is 0 or out of range. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $pattern  A regular expression, fixed(), or regex().
 * @param  mixed  $i  The 1-based piece number; a negative number counts from the end.
 */
function str_split_i(mixed $x, mixed $pattern, mixed $i): Call { return call_with('str_split_i', [$x, $pattern, $i]); }
/**
 * String padded to at least $width characters.
 *
 * Width counts Unicode code points; a string already at least that wide is unchanged. With side 'both', the extra padding goes on the right.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $width  The minimum width.
 * @param  mixed  ...$options  Named options: side (string, default 'left') is 'left', 'right', or 'both'; pad (string, default ' ') is the padding, repeated once per missing character.
 * @throws \InvalidArgumentException At evaluation, when side is not 'left', 'right', or 'both'.
 */
function str_pad(mixed $x, mixed $width, mixed ...$options): Call { return call_with('str_pad', [$x, $width, ...$options]); }
/**
 * String truncated to at most $width characters, ellipsis included.
 *
 * Width counts Unicode code points; a string that fits is unchanged.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $width  The maximum width.
 * @param  mixed  ...$options  Named options: side (string, default 'right') is the side to cut, 'right', 'left', or 'center'; ellipsis (string, default '...') marks the cut.
 * @throws \InvalidArgumentException At evaluation, when side is not 'right', 'left', or 'center'.
 */
function str_trunc(mixed $x, mixed $width, mixed ...$options): Call { return call_with('str_trunc', [$x, $width, ...$options]); }
/**
 * String in title case.
 *
 * The string is lowercased, then each letter that follows a character other than a letter, digit, or apostrophe is uppercased. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 */
function str_to_title(mixed $x): Call { return call_with('str_to_title', [$x]); }
/**
 * String in upper case, by Unicode rules.
 *
 * @param  mixed  $x  A string expression or literal.
 */
function str_to_upper(mixed $x): Call { return call_with('str_to_upper', [$x]); }
/**
 * String in lower case, by Unicode rules.
 *
 * @param  mixed  $x  A string expression or literal.
 */
function str_to_lower(mixed $x): Call { return call_with('str_to_lower', [$x]); }
/**
 * String with white space trimmed from both ends and each inner run collapsed to one space.
 *
 * @param  mixed  $x  A string expression or literal.
 */
function str_squish(mixed $x): Call { return call_with('str_squish', [$x]); }

// lubridate
/**
 * Date parsed from text with year, month, and day in that order.
 *
 * Digits may run together (20240131) or be separated by any non-digits. Invalid input gives NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  ...$options  Named options: tz (string, default none) parses to a datetime at midnight in that time zone; without it, the result is a date.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function ymd(mixed $x, mixed ...$options): Call { return call_with('ymd', [$x, ...$options]); }
/**
 * Date parsed from text with day, month, and year in that order.
 *
 * Digits may run together (31012024) or be separated by any non-digits. Invalid input gives NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  ...$options  Named options: tz (string, default none) parses to a datetime at midnight in that time zone; without it, the result is a date.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function dmy(mixed $x, mixed ...$options): Call { return call_with('dmy', [$x, ...$options]); }
/**
 * Date parsed from text with month, day, and year in that order.
 *
 * Digits may run together (01312024) or be separated by any non-digits. Invalid input gives NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  ...$options  Named options: tz (string, default none) parses to a datetime at midnight in that time zone; without it, the result is a date.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function mdy(mixed $x, mixed ...$options): Call { return call_with('mdy', [$x, ...$options]); }
/**
 * Datetime parsed from text with year, month, day, hour, minute, and second in that order.
 *
 * Seconds may have a fraction. Invalid input, and a local time the zone skips, give NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  ...$options  Named options: tz (string, default 'UTC') is the time zone of the clock time.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function ymd_hms(mixed $x, mixed ...$options): Call { return call_with('ymd_hms', [$x, ...$options]); }
/**
 * Datetime parsed from text with day, month, year, hour, minute, and second in that order.
 *
 * Seconds may have a fraction. Invalid input, and a local time the zone skips, give NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  ...$options  Named options: tz (string, default 'UTC') is the time zone of the clock time.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function dmy_hms(mixed $x, mixed ...$options): Call { return call_with('dmy_hms', [$x, ...$options]); }
/**
 * Datetime parsed from text with month, day, year, hour, minute, and second in that order.
 *
 * Seconds may have a fraction. Invalid input, and a local time the zone skips, give NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  ...$options  Named options: tz (string, default 'UTC') is the time zone of the clock time.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function mdy_hms(mixed $x, mixed ...$options): Call { return call_with('mdy_hms', [$x, ...$options]); }
/**
 * Datetime parsed from text by the first of the given orders that fits.
 *
 * Order letters are Y (four-digit year), y (two-digit year: 00 to 68 are 2000s, 69 to 99 1900s), m, d, H, M, and S; spaces and the separators - / : _ in an order are ignored. A missing month or day is 1, and a missing hour, minute, or second is 0; an order without a year matches nothing. Text no order fits gives NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A string expression or literal.
 * @param  mixed  $orders  One order, such as 'Ymd HMS', or a list of orders.
 * @param  mixed  ...$options  Named options: tz (string, default 'UTC') is the time zone of the clock time.
 * @throws \InvalidArgumentException At evaluation, when an order has another letter, or tz is not a known time zone.
 */
function parse_date_time(mixed $x, mixed $orders, mixed ...$options): Call { return call_with('parse_date_time', [$x, \is_array($orders) ? new Lit($orders) : $orders, ...$options]); }
/**
 * Year of the date or datetime.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @throws \InvalidArgumentException At evaluation, when a value is a time.
 */
function year(mixed $x): Call { return call_with('year', [$x]); }
/**
 * Month of the date or datetime, from 1 to 12.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @throws \InvalidArgumentException At evaluation, when a value is a time.
 */
function month(mixed $x): Call { return call_with('month', [$x]); }
/**
 * Day of the month, from 1 to 31.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @throws \InvalidArgumentException At evaluation, when a value is a time.
 */
function day(mixed $x): Call { return call_with('day', [$x]); }
/**
 * Day of the week, from 1 to 7.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @param  mixed  ...$options  Named options: week_start (int, default 7) is the day numbered 1, from 1 for Monday to 7 for Sunday.
 * @throws \InvalidArgumentException At evaluation, when a value is a time.
 */
function wday(mixed $x, mixed ...$options): Call { return call_with('wday', [$x, ...$options]); }
/**
 * Day of the year, from 1 to 366.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @throws \InvalidArgumentException At evaluation, when a value is a time.
 */
function yday(mixed $x): Call { return call_with('yday', [$x]); }
/**
 * Quarter of the year, from 1 to 4.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @throws \InvalidArgumentException At evaluation, when a value is a time.
 */
function quarter(mixed $x): Call { return call_with('quarter', [$x]); }
/**
 * Hour of the datetime or time, from 0 to 23.
 *
 * @param  mixed  $x  A datetime or time expression.
 */
function hour(mixed $x): Call { return call_with('hour', [$x]); }
/**
 * Minute of the datetime or time, from 0 to 59.
 *
 * @param  mixed  $x  A datetime or time expression.
 */
function minute(mixed $x): Call { return call_with('minute', [$x]); }
/**
 * Second of the datetime or time, with any fraction.
 *
 * @param  mixed  $x  A datetime or time expression.
 */
function second(mixed $x): Call { return call_with('second', [$x]); }
/**
 * Date or datetime rounded down to the start of its unit.
 *
 * SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @param  mixed  $unit  The unit, optionally with a count, such as 'month' or '15 minutes': second, minute, hour, day, week, month, bimonth, quarter, or year.
 * @param  mixed  ...$options  Named options: week_start (int, default 7) is the first day of a week, from 1 for Monday to 7 for Sunday.
 * @throws \InvalidArgumentException At evaluation, when the unit is unknown.
 */
function floor_date(mixed $x, mixed $unit, mixed ...$options): Call { return call_with('floor_date', [$x, $unit, ...$options]); }
/**
 * Date or datetime rounded up to the start of the next unit.
 *
 * A datetime already on a boundary is unchanged; a date moves to the next boundary, as in lubridate. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @param  mixed  $unit  The unit, optionally with a count, such as 'month' or '15 minutes': second, minute, hour, day, week, month, bimonth, quarter, or year.
 * @param  mixed  ...$options  Named options: week_start (int, default 7) is the first day of a week, from 1 for Monday to 7 for Sunday.
 * @throws \InvalidArgumentException At evaluation, when the unit is unknown.
 */
function ceiling_date(mixed $x, mixed $unit, mixed ...$options): Call { return call_with('ceiling_date', [$x, $unit, ...$options]); }
/**
 * Date or datetime rounded to the nearer of floor_date() and ceiling_date().
 *
 * A value halfway between rounds up. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @param  mixed  $unit  The unit, optionally with a count, such as 'month' or '15 minutes': second, minute, hour, day, week, month, bimonth, quarter, or year.
 * @param  mixed  ...$options  Named options: week_start (int, default 7) is the first day of a week, from 1 for Monday to 7 for Sunday.
 * @throws \InvalidArgumentException At evaluation, when the unit is unknown.
 */
function round_date(mixed $x, mixed $unit, mixed ...$options): Call { return call_with('round_date', [$x, $unit, ...$options]); }
/**
 * Period of $n seconds, to add to or subtract from a datetime.
 *
 * @param  mixed  $n  The number of seconds.
 */
function seconds(mixed $n): Call { return call_with('seconds', [$n]); }
/**
 * Period of $n minutes, to add to or subtract from a datetime.
 *
 * @param  mixed  $n  The number of minutes.
 */
function minutes(mixed $n): Call { return call_with('minutes', [$n]); }
/**
 * Period of $n hours, to add to or subtract from a datetime.
 *
 * @param  mixed  $n  The number of hours.
 */
function hours(mixed $n): Call { return call_with('hours', [$n]); }
/**
 * Period of $n days, to add to or subtract from a date or datetime.
 *
 * Days are calendar days, so the clock time is kept across a daylight saving change.
 *
 * @param  mixed  $n  The number of days; a fraction is truncated.
 */
function days(mixed $n): Call { return call_with('days', [$n]); }
/**
 * Period of $n weeks, to add to or subtract from a date or datetime.
 *
 * @param  mixed  $n  The number of weeks; a fraction is truncated.
 */
function weeks(mixed $n): Call { return call_with('weeks', [$n]); }
/**
 * Period of $n months, to add to or subtract from a date or datetime.
 *
 * A result that lands on a day the month lacks, such as January 31 plus one month, is NA, as in lubridate.
 *
 * @param  mixed  $n  The number of months; a fraction is truncated.
 */
function months(mixed $n): Call { return call_with('months', [$n]); }
/**
 * Period of $n years, to add to or subtract from a date or datetime.
 *
 * A result that lands on a day the year lacks, such as February 29 plus one year, is NA, as in lubridate.
 *
 * @param  mixed  $n  The number of years; a fraction is truncated.
 */
function years(mixed $n): Call { return call_with('years', [$n]); }
/**
 * Difference $x - $y between two dates or datetimes, as a float in the given units.
 *
 * The result is NA where either value is NA.
 *
 * @param  mixed  $x  A date or datetime expression.
 * @param  mixed  $y  A date or datetime expression.
 * @param  mixed  ...$options  Named options: units (string, default 'days') is 'secs', 'mins', 'hours', 'days', or 'weeks'.
 * @throws \InvalidArgumentException At evaluation, when units is another value.
 */
function difftime(mixed $x, mixed $y, mixed ...$options): Call { return call_with('difftime', [$x, $y, ...$options]); }
/**
 * Same instant shown in another time zone.
 *
 * SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A datetime expression.
 * @param  mixed  $tz  The time zone name, such as 'America/New_York'.
 * @throws \InvalidArgumentException At evaluation, when the time zone is unknown.
 */
function with_tz(mixed $x, mixed $tz): Call { return call_with('with_tz', [$x, $tz]); }
/**
 * Same clock time in another time zone, which changes the instant.
 *
 * A clock time the zone skips, in a daylight saving gap, gives NA. SQL backends evaluate it in memory when it reads a column.
 *
 * @param  mixed  $x  A datetime expression.
 * @param  mixed  $tz  The time zone name, such as 'America/New_York'.
 * @throws \InvalidArgumentException At evaluation, when the time zone is unknown.
 */
function force_tz(mixed $x, mixed $tz): Call { return call_with('force_tz', [$x, $tz]); }
/**
 * Current date.
 *
 * @param  mixed  ...$options  Named options: tz (string, default 'UTC') is the time zone whose calendar gives the date.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function today(mixed ...$options): Call { return call_with('today', $options); }
/**
 * Current date and time.
 *
 * @param  mixed  ...$options  Named options: tz (string, default 'UTC') is the time zone of the result.
 * @throws \InvalidArgumentException At evaluation, when tz is not a known time zone.
 */
function now(mixed ...$options): Call { return call_with('now', $options); }
