```php
use function Phibble\tbl;
use function Phibble\Expr\{col, desc, n, sum};

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE orders (
    region TEXT, product TEXT, units INTEGER, price REAL
)');
$pdo->exec("INSERT INTO orders VALUES
    ('east', 'lamp', 3, 24.0),
    ('east', 'desk', 1, 180.0),
    ('west', 'lamp', 5, 24.0),
    ('west', 'chair', 4, 65.0),
    ('north', 'desk', 2, 180.0),
    ('north', 'lamp', 1, 24.0),
    ('west', 'desk', 1, 180.0),
    ('east', 'chair', 0, 65.0)");

$orders = tbl($pdo, 'orders');

$revenue = $orders
    ->filter(col('units')->gt(0))
    ->mutate(['revenue' => col('units')->mul(col('price'))])
    ->groupBy(['region'])
    ->summarize([
        'orders' => n(),
        'revenue' => sum(col('revenue')),
    ])
    ->arrange([desc(col('revenue'))]);

echo $revenue->showQuery(), "\n\n";
echo $revenue->collect(), "\n";
```
