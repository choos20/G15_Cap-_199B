<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_attachments', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_attachments', 'file_data')) {
                $table->binary('file_data')->nullable();
            }
            if (! Schema::hasColumn('project_attachments', 'file_mime')) {
                $table->string('file_mime', 150)->nullable();
            }
        });

        // Preserve existing files that still exist on the public disk before
        // future deployments rely on the database copy.
        DB::table('project_attachments')
            ->whereNull('file_data')
            ->orderBy('id')
            ->get()
            ->each(function (object $attachment): void {
                if (! $attachment->file_path || ! Storage::disk('public')->exists($attachment->file_path)) {
                    return;
                }

                DB::table('project_attachments')
                    ->where('id', $attachment->id)
                    ->update([
                        'file_data' => Storage::disk('public')->get($attachment->file_path),
                        'file_mime' => $attachment->mime_type,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('project_attachments', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('project_attachments', 'file_data') ? 'file_data' : null,
                Schema::hasColumn('project_attachments', 'file_mime') ? 'file_mime' : null,
            ]));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
