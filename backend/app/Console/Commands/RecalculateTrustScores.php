<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Trust\TrustService;
use Illuminate\Console\Command;

class RecalculateTrustScores extends Command
{
    /**
     * @var string
     */
    protected $signature = 'trust:recalculate';

    /**
     * @var string
     */
    protected $description = 'Recompute every student\'s trust score (account age grows with time)';

    /**
     * Execute the console command.
     */
    public function handle(TrustService $trust): int
    {
        User::query()->lazyById()->each(fn (User $user) => $trust->refresh($user, 'scheduled'));

        $this->info('Trust scores recalculated.');

        return self::SUCCESS;
    }
}
