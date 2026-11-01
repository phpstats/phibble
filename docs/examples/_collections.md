```php
$collection = new class
{
    public function all(): array
    {
        return [
            ['id' => 1, 'name' => 'Ana'],
            (object) ['id' => 2, 'name' => 'Ben'],
        ];
    }
};

echo DataFrame::fromCollection($collection);
```

```{.text .output}
# A tibble: 2 × 2
     id name 
  <int> <chr>
1     1 Ana  
2     2 Ben
```
