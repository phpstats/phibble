```php
$df = tibble(id: [1, 2], score: [2.5, null]);

echo $df->toJson(), "\n";
echo $df->toJson('columns'), "\n";

echo DataFrame::fromJson('{"id": [1, 2], "ok": [true, null]}');
```

```{.text .output}
[{"id":1,"score":2.5},{"id":2,"score":null}]
{"id":[1,2],"score":[2.5,null]}
# A tibble: 2 × 2
     id ok   
  <int> <lgl>
1     1 TRUE 
2     2 NA
```
