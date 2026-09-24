<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 64)->nullable();
            $table->string('status', 16)->default('new');
            $table->tinyInteger('rating')->nullable();
            $table->json('answers')->default('[]');
            $table->string('cv_path');
            $table->string('cv_original_name');
            $table->string('cv_mime', 128);
            $table->unsignedInteger('cv_size');
            $table->json('files')->nullable();
            $table->timestamp('consent_at');
            $table->string('consent_version', 16);
            $table->timestamps();

            $table->unique(['offer_id', 'email']);
            $table->index(['offer_id', 'status']);
            $table->index(['offer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
