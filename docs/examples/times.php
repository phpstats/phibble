<?php

$trains = DataFrame::fromCsvString("train,departs\nA,7:05 AM\nB,12:40 PM\nC,6:15 PM\nD,NA\n");

echo $trains, "\n";

echo $trains
    ->filter(col('departs')->gt('12:00'))
    ->mutate(['hour' => hour(col('departs')), 'later' => col('departs')->add(90 * 60)]), "\n";

echo Time::of(1, 30), ', ', Time::fromSeconds(-90.5), ', ', Time::parse('25:00')?->seconds(), "\n";
