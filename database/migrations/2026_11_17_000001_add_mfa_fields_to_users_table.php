<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'mfa_secret')) {
                $table->text('mfa_secret')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'mfa_enabled_at')) {
                $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('users', 'mfa_secret')) {
                $columns[] = 'mfa_secret';
            }

            if (Schema::hasColumn('users', 'mfa_enabled_at')) {
                $columns[] = 'mfa_enabled_at';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
