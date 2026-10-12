<?php

namespace App\Listeners;

use App\Services\Trust\TrustService;
use Illuminate\Auth\Events\Verified;

class RefreshTrustScoreOnVerified
{
    public function __construct(private readonly TrustService $trust) {}

    /**
     * Verifying the university email is the first trust signal, so re-score right away.
     */
    public function handle(Verified $event): void
    {
        $this->trust->refresh($event->user, 'email_verified');
    }
}
