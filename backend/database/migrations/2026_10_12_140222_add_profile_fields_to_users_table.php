<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('major', 100)->nullable()->after('email');
            $table->unsignedTinyInteger('study_year')->nullable()->after('major');
            $table->string('bio', 500)->nullable()->after('study_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['major', 'study_year', 'bio']);
        });
    }
};
