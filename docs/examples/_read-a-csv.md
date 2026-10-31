```php
<?php

require __DIR__.'/vendor/autoload.php';

use Phibble\DataFrame;

$readings = DataFrame::fromCsv('readings.csv', types: ['station' => 'factor']);

echo $readings, "\n";
```

```{.text .output}
# A tibble: 6 × 6
  date       station temp_c rain_mm wind_kmh windy
  <date>     <fct>    <dbl>   <dbl>    <int> <lgl>
1 2024-03-01 north      7.5     0         12 FALSE
2 2024-03-01 south      9.1     1.2       31 TRUE 
3 2024-03-02 north      6.8    NA         18 FALSE
4 2024-03-02 south      8.4     3.5       35 TRUE 
5 2024-03-03 north     10.2     0          9 FALSE
6 2024-03-03 south     11       0.4       22 FALSE
```
