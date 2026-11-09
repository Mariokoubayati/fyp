<?php

use App\Http\Controllers\Api\V1\BadgeCountController;
use App\Http\Controllers\Api\V1\BlockDestroyController;
use App\Http\Controllers\Api\V1\BlockStoreController;
use App\Http\Controllers\Api\V1\ConversationIndexController;
use App\Http\Controllers\Api\V1\ConversationStoreController;
use App\Http\Controllers\Api\V1\CourseIndexController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\EnrollmentDestroyController;
use App\Http\Controllers\Api\V1\EnrollmentIndexController;
use App\Http\Controllers\Api\V1\EnrollmentStoreController;
use App\Http\Controllers\Api\V1\ListingDestroyController;
use App\Http\Controllers\Api\V1\ListingIndexController;
use App\Http\Controllers\Api\V1\ListingMarkSoldController;
use App\Http\Controllers\Api\V1\ListingRatingStoreController;
use App\Http\Controllers\Api\V1\ListingShowController;
use App\Http\Controllers\Api\V1\ListingStoreController;
use App\Http\Controllers\Api\V1\ListingUpdateController;
use App\Http\Controllers\Api\V1\LoanAcceptController;
use App\Http\Controllers\Api\V1\LoanCancelController;
use App\Http\Controllers\Api\V1\LoanConfirmHandoverController;
use App\Http\Controllers\Api\V1\LoanConfirmRepaymentController;
use App\Http\Controllers\Api\V1\LoanIndexController;
use App\Http\Controllers\Api\V1\LoanShowController;
use App\Http\Controllers\Api\V1\LoanStoreController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MessageIndexController;
use App\Http\Controllers\Api\V1\MessageStoreController;
use App\Http\Controllers\Api\V1\MyTrustController;
use App\Http\Controllers\Api\V1\NotificationIndexController;
use App\Http\Controllers\Api\V1\NotificationReadController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RefreshTokenController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\ResendVerificationController;
use App\Http\Controllers\Api\V1\StudentProfileController;
use App\Http\Controllers\Api\V1\StudyGroupDestroyController;
use App\Http\Controllers\Api\V1\StudyGroupIndexController;
use App\Http\Controllers\Api\V1\StudyGroupJoinController;
use App\Http\Controllers\Api\V1\StudyGroupLeaveController;
use App\Http\Controllers\Api\V1\StudyGroupRecommendedController;
use App\Http\Controllers\Api\V1\StudyGroupShowController;
use App\Http\Controllers\Api\V1\StudyGroupStoreController;
use App\Http\Controllers\Api\V1\StudyGroupUpdateController;
use App\Http\Controllers\Api\V1\UpdateProfileController;
use App\Http\Controllers\Api\V1\UserReportStoreController;
use App\Http\Controllers\Api\V1\UserSearchController;
use App\Http\Controllers\Api\V1\UserTrustController;
use App\Http\Controllers\Api\V1\VerifyEmailController;
use App\Http\Controllers\Api\V1\VouchDestroyController;
use App\Http\Controllers\Api\V1\VouchStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');

        Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('auth:api')->group(function (): void {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::post('refresh', RefreshTokenController::class)->name('refresh');
            Route::post('email/resend', ResendVerificationController::class)
                ->middleware('throttle:6,1')
                ->name('verification.resend');
        });
    });

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', CurrentUserController::class)->name('me');
        Route::patch('me', UpdateProfileController::class)->name('me.update');

        Route::get('recommendations', RecommendationController::class)->name('recommendations');

        Route::get('me/courses', EnrollmentIndexController::class)->name('me.courses.index');
        Route::put('me/courses/{course}', EnrollmentStoreController::class)->name('me.courses.store');
        Route::delete('me/courses/{course}', EnrollmentDestroyController::class)->name('me.courses.destroy');

        Route::get('me/trust', MyTrustController::class)->name('me.trust');
        Route::get('users', UserSearchController::class)->name('users.search');
        Route::get('users/{user}', StudentProfileController::class)->name('users.show');
        Route::get('users/{user}/trust', UserTrustController::class)->name('users.trust');
        Route::put('users/{user}/vouch', VouchStoreController::class)->middleware('verified')->name('users.vouch.store');
        Route::delete('users/{user}/vouch', VouchDestroyController::class)->name('users.vouch.destroy');
        Route::get('courses', CourseIndexController::class)->name('courses.index');

        Route::put('users/{user}/block', BlockStoreController::class)->name('users.block.store');
        Route::delete('users/{user}/block', BlockDestroyController::class)->name('users.block.destroy');
        Route::post('users/{user}/report', UserReportStoreController::class)->middleware('throttle:10,1')->name('users.report.store');

        Route::get('me/badges', BadgeCountController::class)->name('me.badges');
        Route::get('notifications', NotificationIndexController::class)->name('notifications.index');
        Route::post('notifications/read', NotificationReadController::class)->name('notifications.read');

        Route::prefix('conversations')->name('conversations.')->group(function (): void {
            Route::get('/', ConversationIndexController::class)->name('index');
            Route::post('/', ConversationStoreController::class)->middleware('verified')->name('store');
            Route::get('{conversation}/messages', MessageIndexController::class)->name('messages.index');
            Route::post('{conversation}/messages', MessageStoreController::class)->middleware('throttle:messages')->name('messages.store');
        });

        Route::prefix('loans')->name('loans.')->group(function (): void {
            Route::get('/', LoanIndexController::class)->name('index');
            Route::get('{loan}', LoanShowController::class)->name('show');
            Route::post('{loan}/confirm-handover', LoanConfirmHandoverController::class)->name('confirm-handover');
            Route::post('{loan}/confirm-repayment', LoanConfirmRepaymentController::class)->name('confirm-repayment');
            Route::post('{loan}/cancel', LoanCancelController::class)->name('cancel');

            Route::middleware('verified')->group(function (): void {
                Route::post('/', LoanStoreController::class)->name('store');
                Route::post('{loan}/accept', LoanAcceptController::class)->name('accept');
            });
        });

        Route::prefix('listings')->name('listings.')->group(function (): void {
            Route::get('/', ListingIndexController::class)->name('index');
            Route::get('{listing}', ListingShowController::class)->name('show');
            Route::patch('{listing}', ListingUpdateController::class)->name('update');
            Route::delete('{listing}', ListingDestroyController::class)->name('destroy');
            Route::post('{listing}/sold', ListingMarkSoldController::class)->name('sold');
            Route::post('{listing}/rating', ListingRatingStoreController::class)->name('rating.store');

            Route::post('/', ListingStoreController::class)->middleware('verified')->name('store');
        });

        Route::prefix('study-groups')->name('study-groups.')->group(function (): void {
            Route::get('/', StudyGroupIndexController::class)->name('index');
            Route::get('recommended', StudyGroupRecommendedController::class)->name('recommended');
            Route::get('{study_group}', StudyGroupShowController::class)->name('show');
            Route::delete('{study_group}/membership', StudyGroupLeaveController::class)->name('leave');
            Route::patch('{study_group}', StudyGroupUpdateController::class)->name('update');
            Route::delete('{study_group}', StudyGroupDestroyController::class)->name('destroy');

            Route::middleware('verified')->group(function (): void {
                Route::post('/', StudyGroupStoreController::class)->name('store');
                Route::put('{study_group}/membership', StudyGroupJoinController::class)->name('join');
            });
        });
    });
});
