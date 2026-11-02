```php
$collection = new class
{
    public function all(): array
    {
        return [
            ['species' => 'Adelie', 'body_mass_g' => 3750],
            (object) ['species' => 'Gentoo', 'body_mass_g' => 4500],
        ];
    }
};

echo DataFrame::fromCollection($collection);
```

```{.text .output}
# A tibble: 2 × 2
  species body_mass_g
  <chr>         <int>
1 Adelie         3750
2 Gentoo         4500
```
