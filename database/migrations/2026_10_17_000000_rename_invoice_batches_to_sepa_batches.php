<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        // Drop the FK first so the column can be renamed cleanly.
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropForeign(['invoice_batch_id']);
        });

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->renameColumn('invoice_batch_id', 'sepa_batch_id');
        });

        Schema::rename('invoice_batches', 'sepa_batches');

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->foreign('sepa_batch_id')->references('id')->on('sepa_batches')->nullOnDelete();
        });

        $permissions = [
            'view_any_invoice_batches' => 'view_any_sepa_batches',
            'view_invoice_batches' => 'view_sepa_batches',
            'create_invoice_batches' => 'create_sepa_batches',
            'update_invoice_batches' => 'update_sepa_batches',
            'delete_invoice_batches' => 'delete_sepa_batches',
            'delete_any_invoice_batches' => 'delete_any_sepa_batches',
        ];

        foreach ($permissions as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        $permissions = [
            'view_any_sepa_batches' => 'view_any_invoice_batches',
            'view_sepa_batches' => 'view_invoice_batches',
            'create_sepa_batches' => 'create_invoice_batches',
            'update_sepa_batches' => 'update_invoice_batches',
            'delete_sepa_batches' => 'delete_invoice_batches',
            'delete_any_sepa_batches' => 'delete_any_invoice_batches',
        ];

        foreach ($permissions as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropForeign(['sepa_batch_id']);
        });

        Schema::rename('sepa_batches', 'invoice_batches');

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->renameColumn('sepa_batch_id', 'invoice_batch_id');
        });

        Schema::table('invoices', static function (Blueprint $table): void {
            $table->foreign('invoice_batch_id')->references('id')->on('invoice_batches')->nullOnDelete();
        });
    }
};
