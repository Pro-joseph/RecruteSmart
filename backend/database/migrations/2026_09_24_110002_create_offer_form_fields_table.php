<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label');
            $table->string('type', 16);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->json('options')->nullable();
            $table->json('rules')->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['offer_id', 'key']);
            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_form_fields');
    }
};
