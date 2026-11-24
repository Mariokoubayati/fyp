<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_students_with_open_reports(): void
    {
        $student = User::factory()->create(['email' => '202300050@ua.edu.lb']);
        UserReport::factory()->count(3)->create(['reported_id' => $student->id]);

        $this->artisan('reports:review')
            ->expectsOutputToContain('202300050@ua.edu.lb')
            ->assertSuccessful();
    }

    public function test_reviewing_a_student_lifts_the_chat_restriction(): void
    {
        $student = User::factory()->create(['email' => '202300050@ua.edu.lb']);
        UserReport::factory()->count(3)->create(['reported_id' => $student->id]);
        $this->assertTrue($student->isChatRestricted());

        $this->artisan('reports:review', ['student' => '202300050@ua.edu.lb'])
            ->expectsOutputToContain('3')
            ->assertSuccessful();

        $this->assertFalse($student->isChatRestricted());
        $this->assertSame(0, UserReport::whereNull('reviewed_at')->count());
    }

    public function test_an_unknown_student_fails_cleanly(): void
    {
        $this->artisan('reports:review', ['student' => 'nobody@ua.edu.lb'])->assertFailed();
    }
}
