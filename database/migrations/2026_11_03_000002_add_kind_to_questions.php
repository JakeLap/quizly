<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What kind of question it is, e.g. the Wikidata template that made it
        // ("country-calling-code"). Games spread their rounds over kinds, so
        // one kind doesn't take over. Null: a one-off, hand-written question.
        Schema::table('questions', function (Blueprint $table) {
            $table->string('kind', 64)->nullable()->after('category_id');
            $table->index(['category_id', 'kind']);
        });

        // Generated questions' refs start with their template: "country-calling-code:Q142".
        DB::table('questions')
            ->where('source', 'wikidata')
            ->where('source_ref', 'like', '%:%')
            ->update(['kind' => DB::raw("split_part(source_ref, ':', 1)")]);
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
