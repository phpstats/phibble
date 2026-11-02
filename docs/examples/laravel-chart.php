<?php

require __DIR__.'/vendor/autoload.php';
require '../examples/support/laravel.php';

// ---

use Phiz\Plot;

$penguins = Penguin::query()
    ->measured()
    ->phibble()
    ->select(['species', 'flipper_length_mm', 'body_mass_g'])
    ->collect();

$plot = Plot::of($penguins)
    ->aes(x: 'flipper_length_mm', y: 'body_mass_g', color: 'species')
    ->point(alpha: 0.5)
    ->smooth(method: 'lm')
    ->labs(
        title: 'Body mass by flipper length',
        x: 'Flipper length (mm)',
        y: 'Body mass (g)',
    );

// ===
echo "<!--phiz-->\n", Phiz\Html::embed($plot);
