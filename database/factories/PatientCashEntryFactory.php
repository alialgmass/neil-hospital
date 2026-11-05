<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Models\PatientCashEntry;

/** @extends Factory<PatientCashEntry> */
class PatientCashEntryFactory extends Factory
{
    protected $model = PatientCashEntry::class;

    public function definition(): array
    {
        return [
            'booking_id' => null,
            'insurance_company_id' => null,
            'description' => 'خدمة غير مغطاة',
            'department' => 'clinic',
            'amount' => fake()->randomFloat(2, 10, 5000),
            'entry_date' => today(),
            'status' => 'due',
        ];
    }
}
