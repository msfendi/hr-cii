<?php

namespace Database\Seeders;

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ApprovalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $depts = [
            [
                'name' => 'Sewing A',
                'dept' => [2031, 2032, 2033, 2034]
            ],
            [
                'name' => 'Sewing B',
                'dept' => [2036, 2035, 2038, 2039]
            ],
            [
                'name' => 'Sewing C',
                'dept' => [2040, 2041, 2042, 2043]
            ],
            [
                'name' => 'Staff',
                'dept' => [2097, 2055, 83, 86]
            ],

        ];

        foreach ($depts as $dept) {
            ApprovalDept::create($dept);
        }

        $rules = [
            [
                'name' => '',
                'rules_id' => 1,
                'approval_id' => 1,
                'level' => 1,
            ],

        ];

        foreach ($rules as $rule) {
            ApprovalRule::create($rule);
        }
    }
}
