<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('error_code', 32)->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('criteria_version')->default(1);
            $table->string('prompt_version', 16)->default('v1');
            $table->char('input_hash', 64)->nullable();
            $table->string('llm_provider', 32)->nullable();
            $table->string('llm_model', 64)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedTinyInteger('ats_score')->nullable();
            $table->string('ats_verdict', 24)->nullable();
            $table->json('ats_checks')->nullable();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->json('match_breakdown')->nullable();
            $table->json('knockout_flags')->nullable();
            $table->text('summary')->nullable();
            $table->json('strengths')->nullable();
            $table->json('gaps')->nullable();
            $table->json('anomalies')->nullable();
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('city_normalized')->nullable();
            $table->json('languages')->nullable();
            $table->json('education')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index('match_score');
            $table->index('ats_score');
            $table->index('years_experience');
            $table->index('city_normalized');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_analyses');
    }
};
