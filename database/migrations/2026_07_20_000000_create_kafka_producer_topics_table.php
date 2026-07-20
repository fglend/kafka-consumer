<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kafka_producer_topics')) {
            return;
        }

        Schema::create('kafka_producer_topics', function (Blueprint $table) {
            $table->id();
            $table->string('topic')->unique();
            $table->string('key_field')->nullable();
            $table->json('default_headers')->nullable();
            $table->json('payload_schema')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('messages_produced')->default(0);
            $table->unsignedBigInteger('messages_send_failed')->default(0);
            $table->unsignedBigInteger('messages_resent')->default(0);
            $table->timestamp('last_produced_at')->nullable();
            $table->text('producer_last_error')->nullable();
            $table->unsignedInteger('max_send_attempts')->default(3);
            $table->unsignedInteger('send_backoff_seconds')->default(60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kafka_producer_topics');
    }
};
