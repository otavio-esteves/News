<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(AgenciaBrasilSourceSeeder::class);
        $this->call(AgenciaCamaraSourceSeeder::class);
        $this->call(AgenciaCnjSourceSeeder::class);
        $this->call(AgenciaIbgeSourceSeeder::class);
        $this->call(AgenciaSenadoSourceSeeder::class);
        $this->call(RadioagenciaNacionalSourceSeeder::class);
    }
}
