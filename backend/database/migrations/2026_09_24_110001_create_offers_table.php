<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 32);
            $table->string('type_label')->nullable();
            $table->text('description');
            $table->text('missions')->nullable();
            $table->text('profile_wanted')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('work_mode', 16)->nullable();
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->smallInteger('positions_count')->default(1);
            $table->json('required_skills')->default('[]');
            $table->json('preferred_skills')->default('[]');
            $table->decimal('min_experience_years', 3, 1)->nullable();
            $table->string('education_level')->nullable();
            $table->json('languages')->default('[]');
            $table->json('knockout_criteria')->default('[]');
            $table->json('scoring_weights')->nullable();
            $table->integer('criteria_version')->default(1);
            $table->string('status', 16)->default('draft');
            $table->string('public_token', 32)->nullable()->unique();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
