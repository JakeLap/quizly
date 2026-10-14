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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('difficulty', 10)->default('medium');
            $table->unsignedTinyInteger('correct_index');
            $table->string('status', 20)->default('draft');
            $table->string('source', 20)->default('manual');
            $table->string('source_ref')->nullable();
            $table->string('license')->nullable();
            $table->unsignedInteger('times_asked')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->unique(['source', 'source_ref']);
        });

        Schema::create('question_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('text');
            $table->jsonb('options');
            $table->text('explanation')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_translations');
        Schema::dropIfExists('questions');
    }
};
