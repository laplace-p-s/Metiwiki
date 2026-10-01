<?php

namespace Database\Factories;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'uploads/'.Str::random(40).'.png',
            'original_name' => fake()->word().'.png',
            'mime' => 'image/png',
            'size' => fake()->numberBetween(1_000, 500_000),
            'user_id' => User::factory(),
        ];
    }
}
