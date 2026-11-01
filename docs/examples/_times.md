```php
$trains = DataFrame::fromCsvString("train,departs\nA,7:05 AM\nB,12:40 PM\nC,6:15 PM\nD,NA\n");

echo $trains, "\n";

echo $trains
    ->filter(col('departs')->gt('12:00'))
    ->mutate(['hour' => hour(col('departs')), 'later' => col('departs')->add(90 * 60)]), "\n";

echo Time::of(1, 30), ', ', Time::fromSeconds(-90.5), ', ', Time::parse('25:00')?->seconds(), "\n";
```

```{.text .output}
# A tibble: 4 × 2
  train departs
  <chr> <time> 
1 A     07:05  
2 B     12:40  
3 C     18:15  
4 D        NA  
# A tibble: 2 × 4
  train departs  hour later 
  <chr> <time>  <int> <time>
1 B     12:40      12 14:10 
2 C     18:15      18 19:45 
01:30:00, -00:01:30.5, 90000
```
