<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (! Schema::hasColumn('templates', 'file_data')) {
                $table->binary('file_data')->nullable();
            }
            if (! Schema::hasColumn('templates', 'file_mime')) {
                $table->string('file_mime', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('templates', 'file_data') ? 'file_data' : null,
                Schema::hasColumn('templates', 'file_mime') ? 'file_mime' : null,
            ]);

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
