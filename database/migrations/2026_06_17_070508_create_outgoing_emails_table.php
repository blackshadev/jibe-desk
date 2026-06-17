<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('outgoing_emails', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('tracking_id')->unique();
            $table->string('mailable_class');
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->foreignId('member_id')->nullable()->constrained('members')->cascadeOnDelete();
            $table->string('subject');
            $table->string('status');
            $table->string('message_id')->nullable();
            $table->string('batch_id')->nullable()->index();
            $table->string('related_model_type')->nullable();
            $table->string('related_model_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_emails');
    }
};
