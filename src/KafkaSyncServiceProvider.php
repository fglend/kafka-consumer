<?php

namespace Gurento\KafkaConsumer;

use Gurento\KafkaConsumer\Console\Commands\ConsumeKafkaCommand;
use Gurento\KafkaConsumer\Contracts\ConsumerEngine;
use Gurento\KafkaConsumer\Engines\LaravelKafkaConsumerEngine;
use Illuminate\Support\ServiceProvider;

class KafkaSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/kafka-consumer.php', 'kafka-consumer');

        // Plug-and-play default engine based on mateusjunges/laravel-kafka.
        $this->app->singleton(ConsumerEngine::class, LaravelKafkaConsumerEngine::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/kafka-consumer.php' => config_path('kafka-consumer.php'),
        ], 'kafka-consumer-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'kafka-consumer-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsumeKafkaCommand::class,
            ]);
        }
    }
}
