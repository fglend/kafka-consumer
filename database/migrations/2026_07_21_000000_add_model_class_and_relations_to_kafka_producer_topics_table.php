<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('kafka_producer_topics')) {
            return;
        }

        Schema::table('kafka_producer_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('kafka_producer_topics', 'model_class')) {
                $table->string('model_class')->nullable()->after('topic');
            }

            if (! Schema::hasColumn('kafka_producer_topics', 'relations')) {
                $table->json('relations')->nullable()->after('payload_schema');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kafka_producer_topics')) {
            return;
        }

        Schema::table('kafka_producer_topics', function (Blueprint $table) {
            if (Schema::hasColumn('kafka_producer_topics', 'model_class')) {
                $table->dropColumn('model_class');
            }

            if (Schema::hasColumn('kafka_producer_topics', 'relations')) {
                $table->dropColumn('relations');
            }
        });
    }
};
