<?php

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
