```php
use Phibble\Sql\Compiler;
use Phibble\Sql\MysqlDialect;
use Phibble\Sql\PostgresDialect;
use Phibble\Sql\SqliteDialect;

$expressions = [
    'mean, NA aware' => mean(col('body_mass_g')),
    'ratio' => col('bill_length_mm')->div(col('bill_depth_mm')),
    'contains' => str_detect(col('island'), 'Dream'),
    'in, with NA' => col('sex')->isIn(['female', null]),
];

$dialects = [
    new SqliteDialect(),
    new PostgresDialect(),
    new MysqlDialect(),
];

foreach ($dialects as $dialect) {
    echo "-- {$dialect->name()}\n";
    $compiler = new Compiler($dialect, mode: 'summary');
    foreach ($expressions as $label => $expr) {
        $sql = $compiler->compile($expr)->sql;
        echo \str_pad($label, 16), $sql, "\n";
    }
    echo "\n";
}

$window = new Compiler(
    new PostgresDialect(),
    groups: ['species'],
    order: [[col('year'), false]],
);
echo $window->compile(cumsum(col('body_mass_g')))->sql, "\n";
```

```{.text .output}
-- SQLite
mean, NA aware  (CASE WHEN COUNT(*) > COUNT("body_mass_g") THEN NULL ELSE (CASE WHEN COUNT("body_mass_g") = 0 THEN NULL ELSE AVG(CAST("body_mass_g" AS REAL)) END) END)
ratio           (CASE WHEN "bill_depth_mm" = 0 THEN (CASE WHEN "bill_length_mm" > 0 THEN 9e999 WHEN "bill_length_mm" < 0 THEN -9e999 WHEN "bill_length_mm" = 0 THEN NULL END) ELSE CAST("bill_length_mm" AS REAL) / "bill_depth_mm" END)
contains        (INSTR("island", ?) > 0)
in, with NA     (COALESCE("sex" IN (?), FALSE) OR "sex" IS NULL)

-- PostgreSQL
mean, NA aware  (CASE WHEN COUNT(*) > COUNT("body_mass_g") THEN NULL ELSE (CASE WHEN COUNT("body_mass_g") = 0 THEN 'NaN'::float8 ELSE AVG(CAST("body_mass_g" AS DOUBLE PRECISION)) END) END)
ratio           (CASE WHEN "bill_depth_mm" = 0 THEN (CASE WHEN "bill_length_mm" > 0 THEN 'Infinity'::float8 WHEN "bill_length_mm" < 0 THEN '-Infinity'::float8 WHEN "bill_length_mm" = 0 THEN 'NaN'::float8 END) ELSE CAST("bill_length_mm" AS DOUBLE PRECISION) / "bill_depth_mm" END)
contains        (POSITION(? IN "island") > 0)
in, with NA     (COALESCE("sex" IN (?), FALSE) OR "sex" IS NULL)

-- MySQL
mean, NA aware  (CASE WHEN COUNT(*) > COUNT(`body_mass_g`) THEN NULL ELSE (CASE WHEN COUNT(`body_mass_g`) = 0 THEN NULL ELSE AVG(CAST(`body_mass_g` AS DOUBLE)) END) END)
ratio           (CASE WHEN `bill_depth_mm` = 0 THEN (CASE WHEN `bill_length_mm` > 0 THEN NULL WHEN `bill_length_mm` < 0 THEN NULL WHEN `bill_length_mm` = 0 THEN NULL END) ELSE CAST(`bill_length_mm` AS DOUBLE) / `bill_depth_mm` END)
contains        (LOCATE(?, `island`) > 0)
in, with NA     (COALESCE(`sex` IN (?), FALSE) OR `sex` IS NULL)

(CASE WHEN COUNT(*) OVER (PARTITION BY "species" ORDER BY ("year" IS NULL), "year" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) > COUNT("body_mass_g") OVER (PARTITION BY "species" ORDER BY ("year" IS NULL), "year" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) THEN NULL ELSE SUM("body_mass_g") OVER (PARTITION BY "species" ORDER BY ("year" IS NULL), "year" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) END)
```
