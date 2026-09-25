<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('location_or_link')->nullable();
            $table->json('participants')->nullable();
            $table->string('status', 16)->default('planned');
            $table->text('notes')->nullable();
            $table->string('decision', 16)->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
