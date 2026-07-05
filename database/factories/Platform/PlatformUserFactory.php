<?php

namespace Database\Factories\Platform;

use App\Models\Platform\PlatformUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformUser>
 */
class PlatformUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'password', // casts() hashed
            'phone' => $this->faker->phoneNumber(),
            'department' => $this->faker->randomElement(['运营', '技术', '财务', '风控']),
            'role_id' => null,
        ];
    }
}
