<?php

namespace Database\Factories;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'reference_code' => 'LPW-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'tracking_secret_hash' => hash('sha256', Str::random(32)),
            'category_id' => ComplaintCategory::factory(),
            'reporter_id' => User::factory(),
            'reporter_email' => fake()->safeEmail(),
            'reporter_phone' => fake()->phoneNumber(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(3),
            'location_text' => 'Kec. Ujung, Kota Parepare',
            'status' => ComplaintStatus::Submitted,
            'submitted_at' => now(),
        ];
    }
}
