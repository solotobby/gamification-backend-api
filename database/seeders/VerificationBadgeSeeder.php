<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\VerificationBadge;

class VerificationBadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        foreach ([
            ['key' => 'identity', 'label' => 'Identity Verified'],
            ['key' => 'education', 'label' => 'Education Verified'],
            ['key' => 'experience', 'label' => 'Experience Verified'],
            ['key' => 'portfolio', 'label' => 'Portfolio Verified'],
            ['key' => 'top_talent', 'label' => 'Top Talent'],
        ] as $badge) {
            VerificationBadge::firstOrCreate(['key' => $badge['key']], $badge);
        }
    }
}
