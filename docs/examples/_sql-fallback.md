```php
$pdo = new PDO('sqlite::memory:');
$t = tbl($pdo, <<<'SQL'
    SELECT 1 AS id, 2.5 AS x
    UNION ALL SELECT 2, 3.5
    SQL);

$doubled = $t->mutate(['y' => col('x')->mul(2)]);
$rounded = $t->mutate(['y' => Phibble\Expr\round(col('x'))]);
$closure = $t->filter(fn ($r) => $r->x > 3);

echo get_class($doubled), "\n";
echo get_class($rounded), "\n";
echo get_class($closure), "\n";

echo $rounded;
```

```{.text .output}
Phibble\Sql\SqlTable
Phibble\DataFrame
Phibble\DataFrame
# A tibble: 2 × 3
     id     x     y
  <int> <dbl> <dbl>
1     1   2.5     2
2     2   3.5     4
```
