<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table->string('approval_status')->index()->default('pending')->after('status');
        });

        DB::table('purchase_orders')
            ->whereIn('status', ['pending', 'paid', 'declined'])
            ->update(['approval_status' => 'accepted']);

        DB::table('purchase_orders')
            ->where('status', 'open')
            ->update(['approval_status' => 'pending']);

        DB::table('purchase_orders')
            ->where('status', 'pending')
            ->whereNull('sepa_batch_id')
            ->update(['status' => 'open']);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table->dropIndex(['approval_status']);
            $table->dropColumn('approval_status');
        });
    }
};
