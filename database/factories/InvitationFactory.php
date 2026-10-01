<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * 平文のトークンが必要なテストでは withToken() を使う。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => Invitation::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(7),
            'created_by' => User::factory()->admin(),
            'used_at' => null,
            'used_by' => null,
        ];
    }

    /**
     * Use the given plain token.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => Invitation::hashToken($token),
        ]);
    }

    /**
     * Indicate that the invitation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation has been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now(),
            'used_by' => User::factory(),
        ]);
    }
}
