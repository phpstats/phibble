<?php

// Hidden setup for the Laravel examples: Eloquent on an in-memory SQLite
// database holding palmerpenguins' penguins from phpstats/datasets. In a Laravel app the framework
// configures the connection, and package discovery registers the macros.

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Phibble\DataFrame;
use Phibble\Laravel\PhibbleServiceProvider;

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

Capsule::schema()->create('penguins', function ($table) {
    $table->increments('id');
    $table->string('species');
    $table->string('island');
    $table->float('bill_length_mm')->nullable();
    $table->float('bill_depth_mm')->nullable();
    $table->integer('flipper_length_mm')->nullable();
    $table->integer('body_mass_g')->nullable();
    $table->string('sex')->nullable();
    $table->integer('year');
});
$rows = array_map(
    fn (array $r) => ['id' => $r['rownames']] + array_diff_key($r, ['rownames' => true]),
    DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)->toRows(),
);
foreach (array_chunk($rows, 100) as $chunk) {
    Capsule::table('penguins')->insert($chunk);
}

PhibbleServiceProvider::registerMacros();

final class Penguin extends Model
{
    protected $table = 'penguins';

    public $timestamps = false;

    public function scopeMeasured($query)
    {
        return $query->whereNotNull('body_mass_g');
    }
}
