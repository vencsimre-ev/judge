<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_sheet_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_sheet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('start_time')->nullable();
            $table->string('bib')->nullable();
            $table->string('name')->nullable();
            $table->string('country')->nullable();
            $table->string('attempts_raw')->nullable();
            $table->unsignedInteger('attempts_count')->nullable();
            $table->unsignedInteger('zone_attempt')->nullable();
            $table->unsignedInteger('top_attempt')->nullable();
            $table->unsignedInteger('zone_column_value')->nullable();
            $table->unsignedInteger('top_column_value')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->unique(['score_sheet_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_sheet_rows');
    }
};
