<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            // Key/value rather than a column per setting, so adding a setting
            // is an App\Settings\SettingKey case rather than a migration.
            $table->string('key')->unique();
            // Nullable JSON, so a setting may legitimately hold null without
            // that being indistinguishable from "never saved".
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
