<?php

namespace Database\Factories;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicYear> */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2030, 2090);

        return [
            'name' => "{$year}/".($year + 1),
            'start_date' => "{$year}-09-01",
            'end_date' => ($year + 1).'-07-31',
            'status' => AcademicYearStatus::Draft,
        ];
    }

    public function status(AcademicYearStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
