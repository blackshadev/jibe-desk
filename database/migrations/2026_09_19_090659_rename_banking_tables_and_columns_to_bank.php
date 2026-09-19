<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign keys that reference the old column/table names.
        Schema::table('banking_transaction_references', static function (Blueprint $table): void {
            $table->dropForeign(['banking_transaction_id']);
        });

        Schema::table('banking_transaction_links', static function (Blueprint $table): void {
            $table->dropForeign(['banking_transaction_id']);
            $table->dropForeign(['linked_transaction_id']);
        });

        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->dropForeign(['reversed_by_transaction_id']);
        });

        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->dropForeign(['banking_transaction_id']);
            $table->dropIndex(['banking_transaction_id']);
        });

        // Rename columns first (while tables still have old names).
        Schema::table('banking_transaction_references', static function (Blueprint $table): void {
            $table->renameColumn('banking_transaction_id', 'bank_transaction_id');
        });

        Schema::table('banking_transaction_links', static function (Blueprint $table): void {
            $table->renameColumn('banking_transaction_id', 'bank_transaction_id');
        });

        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->renameColumn('banking_transaction_id', 'bank_transaction_id');
        });

        // Rename tables.
        Schema::rename('banking_transactions', 'bank_transactions');
        Schema::rename('banking_transaction_references', 'bank_transaction_references');
        Schema::rename('banking_transaction_links', 'bank_transaction_links');

        // Recreate foreign keys with the new names.
        Schema::table('bank_transaction_references', static function (Blueprint $table): void {
            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions')
                ->cascadeOnDelete();
        });

        Schema::table('bank_transaction_links', static function (Blueprint $table): void {
            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions')
                ->cascadeOnDelete();

            $table->foreign('linked_transaction_id')
                ->references('id')
                ->on('bank_transactions')
                ->cascadeOnDelete();
        });

        Schema::table('bank_transactions', static function (Blueprint $table): void {
            $table->foreign('reversed_by_transaction_id')
                ->references('id')
                ->on('bank_transactions');
        });

        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions');

            $table->index('bank_transaction_id');
        });
    }

    public function down(): void
    {
        // Drop foreign keys.
        Schema::table('bank_transaction_references', static function (Blueprint $table): void {
            $table->dropForeign(['bank_transaction_id']);
        });

        Schema::table('bank_transaction_links', static function (Blueprint $table): void {
            $table->dropForeign(['bank_transaction_id']);
            $table->dropForeign(['linked_transaction_id']);
        });

        Schema::table('bank_transactions', static function (Blueprint $table): void {
            $table->dropForeign(['reversed_by_transaction_id']);
        });

        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->dropForeign(['bank_transaction_id']);
            $table->dropIndex(['bank_transaction_id']);
        });

        // Rename columns back.
        Schema::table('bank_transaction_references', static function (Blueprint $table): void {
            $table->renameColumn('bank_transaction_id', 'banking_transaction_id');
        });

        Schema::table('bank_transaction_links', static function (Blueprint $table): void {
            $table->renameColumn('bank_transaction_id', 'banking_transaction_id');
        });

        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->renameColumn('bank_transaction_id', 'banking_transaction_id');
        });

        // Rename tables back.
        Schema::rename('bank_transactions', 'banking_transactions');
        Schema::rename('bank_transaction_references', 'banking_transaction_references');
        Schema::rename('bank_transaction_links', 'banking_transaction_links');

        // Recreate foreign keys with the old names.
        Schema::table('banking_transaction_references', static function (Blueprint $table): void {
            $table->foreign('banking_transaction_id')
                ->references('id')
                ->on('banking_transactions')
                ->cascadeOnDelete();
        });

        Schema::table('banking_transaction_links', static function (Blueprint $table): void {
            $table->foreign('banking_transaction_id')
                ->references('id')
                ->on('banking_transactions')
                ->cascadeOnDelete();

            $table->foreign('linked_transaction_id')
                ->references('id')
                ->on('banking_transactions')
                ->cascadeOnDelete();
        });

        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->foreign('reversed_by_transaction_id')
                ->references('id')
                ->on('banking_transactions');
        });

        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->foreign('banking_transaction_id')
                ->references('id')
                ->on('banking_transactions');

            $table->index('banking_transaction_id');
        });
    }
};
