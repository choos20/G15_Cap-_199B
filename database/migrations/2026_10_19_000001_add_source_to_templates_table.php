<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->index();
        });

        DB::table('templates')
            ->leftJoin('users', 'templates.uploaded_by', '=', 'users.id')
            ->select('templates.id', 'users.role')
            ->orderBy('templates.id')
            ->chunk(200, function ($templates): void {
                foreach ($templates as $template) {
                    $source = match ($template->role) {
                        'csa_director' => 'csa',
                        'celp_head', 'coordinator', 'celp_secretary' => 'celp',
                        default => null,
                    };

                    if ($source !== null) {
                        DB::table('templates')
                            ->where('id', $template->id)
                            ->update(['source' => $source]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
