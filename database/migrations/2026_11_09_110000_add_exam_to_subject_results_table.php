<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student sits three exams in the academic year: quarterly, half yearly and annual. The marks already
 * entered were the marks of the year, so they become the annual exam.
 *
 * The new unique index is added before the old one is dropped: the foreign key on the admission needs an index.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('subject_results', 'exam')) {
            Schema::table('subject_results', function (Blueprint $table) {
                $table->string('exam')->default('annual')->after('subject_id');
            });
        }

        if (! Schema::hasIndex('subject_results', ['admission_id', 'subject_id', 'exam'], 'unique')) {
            Schema::table('subject_results', function (Blueprint $table) {
                $table->unique(['admission_id', 'subject_id', 'exam']);
            });
        }

        if (Schema::hasIndex('subject_results', ['admission_id', 'subject_id'], 'unique')) {
            Schema::table('subject_results', function (Blueprint $table) {
                $table->dropUnique(['admission_id', 'subject_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_results', function (Blueprint $table) {
            $table->unique(['admission_id', 'subject_id']);
        });

        Schema::table('subject_results', function (Blueprint $table) {
            $table->dropUnique(['admission_id', 'subject_id', 'exam']);
        });

        Schema::table('subject_results', function (Blueprint $table) {
            $table->dropColumn('exam');
        });
    }
};
