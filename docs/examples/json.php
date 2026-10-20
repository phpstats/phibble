<?php

$df = tibble(id: [1, 2], score: [2.5, null]);

echo $df->toJson(), "\n";
echo $df->toJson('columns'), "\n";

echo DataFrame::fromJson('{"id": [1, 2], "ok": [true, null]}');
