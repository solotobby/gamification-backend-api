<?php

namespace Tests\Unit;

use App\Models\Experience;
use App\Models\Certification;
use Carbon\Carbon;
use Tests\TestCase;

class CareerProfileDateTest extends TestCase
{
    public function test_experience_model_mutator_normalizes_various_date_formats()
    {
        $exp = new Experience();

        // 1. DD-MM-YYYY format (like '15-06-2025')
        $exp->start_date = '15-06-2025';
        $exp->end_date = '06-09-2026';
        $this->assertEquals('2025-06-15', $exp->start_date);
        $this->assertEquals('2026-09-06', $exp->end_date);

        // 2. Standard Y-m-d format
        $exp->start_date = '2024-01-10';
        $this->assertEquals('2024-01-10', $exp->start_date);

        // 3. Slash format DD/MM/YYYY
        $exp->start_date = '25/12/2023';
        $this->assertEquals('2023-12-25', $exp->start_date);

        // 4. Null value
        $exp->end_date = null;
        $this->assertNull($exp->end_date);
    }

    public function test_certification_model_mutator_normalizes_date_formats()
    {
        $cert = new Certification();

        $cert->issue_date = '01-05-2024';
        $cert->expiry_date = '01-05-2027';

        $this->assertEquals('2024-05-01', $cert->issue_date);
        $this->assertEquals('2027-05-01', $cert->expiry_date);
    }
}
