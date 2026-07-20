<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kafka_produce_logs')) {
            return;
        }

        Schema::create('kafka_produce_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kafka_producer_topic_id')->constrained('kafka_producer_topics')->cascadeOnDelete();
            $table->string('status');
            $table->unsignedInteger('attempt_count')->default(1);
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->string('message_key')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('produced_at')->useCurrent();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('is_resent')->default(false);
            $table->boolean('retryable')->default(true);

            $table->index(['status', 'retryable', 'next_retry_at'], 'kafka_produce_logs_retry_lookup_idx');
            $table->index(['kafka_producer_topic_id', 'produced_at'], 'kafka_produce_logs_topic_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kafka_produce_logs');
    }
};
