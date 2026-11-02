```php
$high = DataFrame::fromPdo(
    $pdo,
    'SELECT * FROM airports WHERE tzone = ? AND alt > ? ORDER BY alt DESC',
    ['America/New_York', 2000],
);

echo $high;
```

```{.text .output}
# A tibble: 7 × 4
  faa   name                                           alt tzone           
  <chr> <chr>                                        <int> <chr>           
1 BLF   Mercer County Airport                         2857 America/New_York
2 BKW   Raleigh County Memorial Airport               2504 America/New_York
3 LWB   Greenbrier Valley Airport                     2302 America/New_York
4 JST   John Murtha Johnstown-Cambria County Airport  2284 America/New_York
5 2G9   Somerset County Airport                       2275 America/New_York
6 AVL   Asheville Regional Airport                    2165 America/New_York
7 BFD   Bradford Regional Airport                     2143 America/New_York
```
