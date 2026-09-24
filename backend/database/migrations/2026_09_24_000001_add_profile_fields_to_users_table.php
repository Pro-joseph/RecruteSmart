<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('email');
            $table->string('logo_path')->nullable()->after('company_name');
            $table->text('email_signature')->nullable()->after('logo_path');
            $table->string('timezone', 64)->default('UTC')->after('email_signature');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['company_name', 'logo_path', 'email_signature', 'timezone']);
        });
    }
};
