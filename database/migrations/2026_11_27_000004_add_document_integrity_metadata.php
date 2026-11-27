<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['templates', 'project_attachments'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                if (! Schema::hasColumn($name, 'file_size')) {
                    $table->unsignedBigInteger('file_size')->nullable();
                }
                if (! Schema::hasColumn($name, 'file_sha256')) {
                    $table->string('file_sha256', 64)->nullable();
                }
                if (! Schema::hasColumn($name, 'file_disk')) {
                    $table->string('file_disk', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Integrity metadata is intentionally retained during a rollback.
        // No original document bytes or existing columns are removed.
    }
};
