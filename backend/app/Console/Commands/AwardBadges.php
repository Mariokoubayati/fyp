<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Badges\BadgeService;
use Illuminate\Console\Command;

class AwardBadges extends Command
{
    /**
     * @var string
     */
    protected $signature = 'badges:award';

    /**
     * @var string
     */
    protected $description = 'Award badges nobody triggered directly (an event that has now started counts for "Event goer")';

    public function handle(BadgeService $badges): int
    {
        $awarded = 0;

        User::query()->whereNotNull('email_verified_at')->each(function (User $user) use ($badges, &$awarded): void {
            $awarded += count($badges->check($user));
        });

        $this->info("Awarded {$awarded} badge(s).");

        return self::SUCCESS;
    }
}
