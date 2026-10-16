<?php

namespace Database\Factories;

use App\Models\ComplaintCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ComplaintCategory>
 */
class ComplaintCategoryFactory extends Factory
{
    protected $model = ComplaintCategory::class;

    public function definition(): array
    {
        // The DB enforces UNIQUE(complaint_categories.slug). Deriving the slug
        // from a purely random 2-word phrase could collide between records and
        // intermittently fail fixtures (e.g. the 25-category pagination test).
        // The faker `unique()` proxy (the same convention used by UserFactory
        // and DinasUnitFactory) guarantees a distinct phrase per generated
        // record within the process, so the derived slug is unique by
        // construction while staying human-readable. No schema or production
        // behaviour is affected — this is a test/dev fixture only.
        $name = fake()->unique()->words(2, true);
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'dinas_name' => 'Dinas ' . ucfirst(fake()->word()),
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
