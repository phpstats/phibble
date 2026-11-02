```php
$weather = tbl($pdo, 'weather');
$airports = tbl($pdo, 'airports');

$joined = $weather
    ->groupBy(['origin'])
    ->summarize(['temp' => mean(col('temp'), na_rm: true)])
    ->join($airports, [['origin', 'faa']], 'left');

echo $joined->showQuery(), "\n\n";
echo $joined->arrange(['origin'])->collect();
```
