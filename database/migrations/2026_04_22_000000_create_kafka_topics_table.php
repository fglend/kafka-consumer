<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kafka_topics', function (Blueprint $table) {
            $table->id();
            $table->string('topic')->unique();
            $table->string('model_class');
            $table->string('upsert_key')->default('id');
            $table->json('field_map')->nullable();
            $table->json('exclude_keys')->nullable();
            $table->json('relations')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('messages_consumed')->default(0);
            $table->unsignedBigInteger('messages_failed')->default(0);
            $table->unsignedBigInteger('messages_reconsumed')->default(0);
            $table->timestamp('last_consumed_at')->nullable();
            $table->timestamp('consumer_last_heartbeat_at')->nullable();
            $table->text('consumer_last_error')->nullable();
            $table->unsignedBigInteger('consumer_lag_seconds')->nullable();
            $table->unsignedInteger('max_reconsume_attempts')->default(3);
            $table->unsignedInteger('retry_backoff_seconds')->default(60);
            $table->unsignedInteger('health_stale_after_seconds')->default(300);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kafka_topics');
    }
};
