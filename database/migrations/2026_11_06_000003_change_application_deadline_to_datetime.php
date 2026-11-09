<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dateTime('application_deadline')->nullable()->change();
        });

        DB::table('announcements')
            ->whereNotNull('application_deadline')
            ->orderBy('id')
            ->select(['id', 'application_deadline'])
            ->chunkById(500, function ($announcements) {
                foreach ($announcements as $announcement) {
                    DB::table('announcements')
                        ->where('id', $announcement->id)
                        ->update([
                            'application_deadline' => Carbon::parse($announcement->application_deadline, 'Asia/Manila')
                                ->endOfDay()
                                ->utc()
                                ->format('Y-m-d H:i:s'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->date('application_deadline')->nullable()->change();
        });
    }
};
