<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kafka_consume_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kafka_topic_id')->constrained('kafka_topics')->cascadeOnDelete();
            $table->string('status');
            $table->unsignedInteger('attempt_count')->default(1);
            $table->string('upsert_key_value')->nullable();
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('consumed_at')->useCurrent();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('is_reconsumed')->default(false);
            $table->boolean('retryable')->default(true);
            $table->integer('kafka_partition')->nullable();
            $table->unsignedBigInteger('kafka_offset')->nullable();
            $table->string('kafka_key')->nullable();

            $table->index(['status', 'retryable', 'next_retry_at'], 'kafka_logs_retry_lookup_idx');
            $table->index(['kafka_topic_id', 'consumed_at'], 'kafka_logs_topic_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kafka_consume_logs');
    }
};
