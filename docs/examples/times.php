<?php

$trains = DataFrame::fromCsvString(<<<'CSV'
    train,departs
    A,7:05 AM
    B,12:40 PM
    C,6:15 PM
    D,NA
    CSV);

echo $trains, "\n";

echo $trains
    ->filter(col('departs')->gt('12:00'))
    ->mutate([
        'hour' => hour(col('departs')),
        'later' => col('departs')->add(90 * 60),
    ]), "\n";

echo Time::of(1, 30), "\n";
echo Time::fromSeconds(-90.5), "\n";
echo Time::parse('25:00')?->seconds(), "\n";
