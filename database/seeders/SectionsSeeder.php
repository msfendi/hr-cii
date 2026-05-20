<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;

class SectionsSeeder extends Seeder
{
    public function run()
    {

        Section::updateOrCreate(
            ['name' => 'FA'],
            [
                'line_start' => 1,
                'line_end'   => 48,
            ]
        );

        Section::updateOrCreate(
            ['name' => 'FB'],
            [
                'line_start' => 49,
                'line_end'   => 96,
            ]
        );
        $letters = range('A', 'Z');

        $line = 1;
        $index = 0;

        while ($line <= 96) {

            $parentStart = $line;
            $parentEnd   = $line + 11;

            $letter = $letters[$index];

            /*
            |--------------------------------------------------------------------------
            | KELIPATAN 12
            |--------------------------------------------------------------------------
            */
            Section::updateOrCreate(
                ['name' => $letter],
                [
                    'line_start' => $parentStart,
                    'line_end'   => $parentEnd,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | KELIPATAN 6 → A6a, A6b
            |--------------------------------------------------------------------------
            */
            $sixLetters = ['a', 'b'];

            for ($i = 0; $i < 2; $i++) {

                $start = $parentStart + ($i * 6);
                $end   = $start + 5;

                Section::updateOrCreate(
                    ['name' => $letter . '6' . $sixLetters[$i]],
                    [
                        'line_start' => $start,
                        'line_end'   => $end,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | KELIPATAN 4 → A4a,A4b,A4c
            |--------------------------------------------------------------------------
            */
            $fourLetters = ['a', 'b', 'c'];

            for ($i = 0; $i < 3; $i++) {

                $start = $parentStart + ($i * 4);
                $end   = $start + 3;

                Section::updateOrCreate(
                    ['name' => $letter . '4' . $fourLetters[$i]],
                    [
                        'line_start' => $start,
                        'line_end'   => $end,
                    ]
                );
            }

            $line += 12;
            $index++;
        }
    }
}
