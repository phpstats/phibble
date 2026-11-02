```php
$penguins = tbl($pdo, 'penguins');

$summary = $penguins
    ->filter(col('year')->eq(2008))
    ->groupBy(['species'])
    ->summarize([
        'n' => n(),
        'mass' => mean(col('body_mass_g'), na_rm: true),
    ]);

echo get_class($summary), "\n\n";
echo $summary->showQuery(), "\n\n";
echo json_encode($summary->bindings()), "\n\n";
echo $summary->collect();
```
