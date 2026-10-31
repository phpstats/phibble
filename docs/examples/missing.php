<?php

$orders = tibble(total: [20.0, null, 35.0]);

echo $orders->mutate(['with_tax' => col('total')->mul(1.1)]), "\n";

echo $orders->summarize([
    'mean' => mean(col('total')),
    'mean_known' => mean(col('total'), na_rm: true),
]);
