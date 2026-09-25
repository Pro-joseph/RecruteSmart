<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forwards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('to_emails');
            $table->string('subject');
            $table->text('message')->nullable();
            $table->string('delivery', 16)->default('attachments');
            $table->boolean('include_analysis')->default(false);
            $table->string('status', 16)->default('queued');
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('forward_application', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('candidate_name_snapshot');

            $table->unique(['forward_id', 'application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forward_application');
        Schema::dropIfExists('forwards');
    }
};
