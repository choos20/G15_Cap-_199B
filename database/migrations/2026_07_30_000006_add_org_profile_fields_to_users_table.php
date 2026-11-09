<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization_name')->nullable()->after('role');
            $table->string('org_logo')->nullable()->after('organization_name');
            $table->text('org_description')->nullable()->after('org_logo');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['organization_name', 'org_logo', 'org_description']);
        });
    }
};
