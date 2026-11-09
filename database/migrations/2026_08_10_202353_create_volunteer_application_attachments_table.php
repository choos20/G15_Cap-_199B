<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_application_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_application_id')
                ->constrained('volunteer_applications', 'id', 'va_attachments_va_id_fk')
                ->onDelete('cascade');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_application_attachments');
    }
};
