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
            if (! Schema::hasColumn('kafka_producer_topics', 'payload_template')) {
                $table->text('payload_template')->nullable()->after('payload_schema');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kafka_producer_topics')) {
            return;
        }

        Schema::table('kafka_producer_topics', function (Blueprint $table) {
            if (Schema::hasColumn('kafka_producer_topics', 'payload_template')) {
                $table->dropColumn('payload_template');
            }
        });
    }
};
