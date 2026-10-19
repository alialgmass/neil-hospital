<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Insurance\Enums\DepositDecision;
use Modules\Insurance\Models\PendingDeposit;

/**
 * @extends Factory<PendingDeposit>
 */
class PendingDepositFactory extends Factory
{
    protected $model = PendingDeposit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->numberBetween(300, 20000),
            'paid_on' => now()->toDateString(),
            'decision' => DepositDecision::Waiting,
            'refunded' => false,
            'converted' => false,
        ];
    }
}
