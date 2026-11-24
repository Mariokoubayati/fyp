<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(fn (object $notifiable): string => URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        ));

        // "Contains" search where % and _ typed by a student are plain characters, not wildcards.
        // '!' is the escape character because SQLite has no default one and MySQL and SQLite both accept it.
        QueryBuilder::macro('whereContains', function (string $column, string $value, string $boolean = 'and'): QueryBuilder {
            /** @var QueryBuilder $this */
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';

            return $this->whereRaw($this->getGrammar()->wrap($column)." like ? escape '!'", [$pattern], $boolean);
        });

        QueryBuilder::macro('orWhereContains', function (string $column, string $value): QueryBuilder {
            /** @var QueryBuilder $this */
            return $this->whereContains($column, $value, 'or');
        });

        RateLimiter::for('messages', function (Request $request): Limit {
            return Limit::perMinute(config('messaging.messages_per_minute'))->by((string) $request->user()?->id);
        });

        // Sign-ups per network address. Generous enough for a lab full of students
        // behind one campus address, low enough to stop scripted account creation.
        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower($request->string('email')->toString()).'|'.$request->ip())
            );
        });
    }
}
