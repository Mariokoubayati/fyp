<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Console\Command;

class ReviewReports extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reports:review
                            {student? : Email or id of a reported student; marks their open reports as reviewed}';

    /**
     * @var string
     */
    protected $description = 'List students with open reports, or mark one student\'s reports reviewed (lifts the chat restriction)';

    public function handle(): int
    {
        $student = $this->argument('student');

        return $student === null ? $this->listOpenReports() : $this->review((string) $student);
    }

    private function listOpenReports(): int
    {
        $rows = UserReport::query()
            ->with('reported')
            ->whereNull('reviewed_at')
            ->get()
            ->groupBy('reported_id')
            ->map(fn ($reports) => [
                $reports->first()->reported->email,
                $reports->first()->reported->name,
                $reports->count(),
                $reports->pluck('reason')->map->value->unique()->implode(', '),
                $reports->first()->reported->isChatRestricted() ? 'yes' : 'no',
            ])
            ->values();

        if ($rows->isEmpty()) {
            $this->info('No open reports.');

            return self::SUCCESS;
        }

        $this->table(['Email', 'Name', 'Open reports', 'Reasons', 'Restricted'], $rows->all());

        return self::SUCCESS;
    }

    private function review(string $student): int
    {
        $user = User::query()
            ->when(ctype_digit($student), fn ($query) => $query->whereKey((int) $student), fn ($query) => $query->where('email', $student))
            ->first();

        if (! $user) {
            $this->error("No student found for \"{$student}\".");

            return self::FAILURE;
        }

        $count = UserReport::query()
            ->where('reported_id', $user->id)
            ->whereNull('reviewed_at')
            ->update(['reviewed_at' => now()]);

        $this->info("Marked {$count} report(s) about {$user->email} as reviewed.");

        return self::SUCCESS;
    }
}
