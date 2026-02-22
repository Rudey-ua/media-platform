<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
            $table->string('access_mode')->nullable()->after('owner_id');
            $table->index(['owner_id', 'access_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_owner_id_member_video_access_mode_index');
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('access_mode');
        });
    }
};
