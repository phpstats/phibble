<?php

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
