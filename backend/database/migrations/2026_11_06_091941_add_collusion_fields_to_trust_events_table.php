<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The other student involved in the event, and why an event was logged but not counted.
     */
    public function up(): void
    {
        Schema::table('trust_events', function (Blueprint $table) {
            $table->foreignId('counterparty_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('discount_reason', 40)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('trust_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counterparty_id');
            $table->dropColumn('discount_reason');
        });
    }
};
