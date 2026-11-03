<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who may manage the questions (see the manage-questions gate).
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
        });

        // When a question's content was last changed in the admin. Imports
        // leave such questions' content alone, so edits aren't overwritten.
        Schema::table('questions', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('times_correct');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
