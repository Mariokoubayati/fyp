<?php

namespace Database\Factories;

use App\Enums\CampusTag;
use App\Enums\ProjectMemberStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'course_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'skills_needed' => fake()->randomElements(['Laravel', 'React Native', 'UI design', 'Python', 'Marketing', 'Video editing'], 2),
            'tags' => [fake()->randomElement(CampusTag::cases())],
            'team_size' => fake()->numberBetween(3, 6),
            'is_open' => true,
        ];
    }

    /**
     * Put the owner on the team, like the API does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project): void {
            $project->people()->syncWithoutDetaching([$project->owner_id => ['status' => ProjectMemberStatus::Accepted->value]]);
        });
    }

    /**
     * A project that no longer looks for members.
     */
    public function closed(): static
    {
        return $this->state(fn (): array => ['is_open' => false]);
    }
}
