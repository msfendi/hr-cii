<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;

class SectionsSeeder extends Seeder
{
    public function run()
    {
        $sections = [

            ['name' => 'A',  'line_start' => 1,  'line_end' => 12],
            ['name' => 'A1', 'line_start' => 1,  'line_end' => 6],
            ['name' => 'A2', 'line_start' => 7,  'line_end' => 12],

            ['name' => 'B',  'line_start' => 13, 'line_end' => 24],
            ['name' => 'B1', 'line_start' => 13, 'line_end' => 18],
            ['name' => 'B2', 'line_start' => 19, 'line_end' => 24],

            ['name' => 'C',  'line_start' => 25, 'line_end' => 36],
            ['name' => 'C1', 'line_start' => 25, 'line_end' => 30],
            ['name' => 'C2', 'line_start' => 31, 'line_end' => 36],

            ['name' => 'D',  'line_start' => 37, 'line_end' => 48],
            ['name' => 'D1', 'line_start' => 37, 'line_end' => 42],
            ['name' => 'D2', 'line_start' => 43, 'line_end' => 48],

            ['name' => 'E',  'line_start' => 49, 'line_end' => 60],
            ['name' => 'E1', 'line_start' => 49, 'line_end' => 54],
            ['name' => 'E2', 'line_start' => 55, 'line_end' => 60],

            ['name' => 'F',  'line_start' => 61, 'line_end' => 72],
            ['name' => 'F1', 'line_start' => 61, 'line_end' => 66],
            ['name' => 'F2', 'line_start' => 67, 'line_end' => 72],

            ['name' => 'G',  'line_start' => 73, 'line_end' => 84],
            ['name' => 'G1', 'line_start' => 73, 'line_end' => 78],
            ['name' => 'G2', 'line_start' => 79, 'line_end' => 84],

            ['name' => 'H',  'line_start' => 85, 'line_end' => 96],
            ['name' => 'H1', 'line_start' => 85, 'line_end' => 90],
            ['name' => 'H2', 'line_start' => 91, 'line_end' => 96],

        ];

        foreach ($sections as $section) {
            Section::create($section);
        }
    }
}
