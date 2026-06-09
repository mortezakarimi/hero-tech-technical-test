<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'event_id']);
            $table->string('active_key')->nullable()->after('status');
            $table->unique('active_key');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['active_key']);
            $table->dropColumn('active_key');
            $table->unique(['user_id', 'event_id']);
        });
    }
};
