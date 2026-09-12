<?php

use App\Auth\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The column is a string rather than a native enum: MySQL and MariaDB
     * both require a table rewrite (ALTER TABLE) to add a value to an enum
     * column, so adding a role would mean a migration and a lock on the users
     * table, while App\Auth\Role is free to grow. The default keeps the
     * least-privileged role authoritative in one place.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')
                ->default(Role::DEFAULT->value)
                ->after('email_verified_at')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
