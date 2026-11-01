```php
$orders = tibble(total: [20.0, null, 35.0]);

echo $orders->mutate([
    'with_tax' => col('total')->mul(1.1),
]), "\n";

echo $orders->summarize([
    'mean' => mean(col('total')),
    'mean_known' => mean(col('total'), na_rm: true),
]);
```

```{.text .output}
# A tibble: 3 × 2
  total with_tax
  <dbl>    <dbl>
1    20     22  
2    NA     NA  
3    35     38.5
# A tibble: 1 × 2
   mean mean_known
  <dbl>      <dbl>
1    NA       27.5
```
