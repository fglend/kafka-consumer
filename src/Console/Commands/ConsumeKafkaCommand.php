<?php

namespace Gurento\KafkaConsumer\Console\Commands;

use Gurento\KafkaConsumer\Contracts\ConsumerEngine;
use Gurento\KafkaConsumer\Models\KafkaTopic;
use Gurento\KafkaConsumer\Services\KafkaConsumerService;
use Illuminate\Console\Command;

class ConsumeKafkaCommand extends Command
{
    protected $signature = 'gurento:kafka-consume
        {--topics=* : Topic names to consume}
        {--limit=0 : Max messages for this run}
        {--from-beginning : Start from earliest offsets}
        {--reconsume-failed : Retry failed logs instead of polling brokers}
        {--reconsume-limit=50 : Max failed logs per topic to retry}';

    protected $description = 'Consume Kafka topics and upsert mapped records';

    public function handle(KafkaConsumerService $service): int
    {
        $reconsume = (bool) $this->option('reconsume-failed');
        $fromBeginning = (bool) $this->option('from-beginning');
        $limit = max(0, (int) $this->option('limit'));
        $topics = array_filter((array) $this->option('topics'));

        $query = KafkaTopic::query()->where('is_active', true);
        if ($topics !== []) {
            $query->whereIn('topic', $topics);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->warn('No active topics configured.');
            return self::FAILURE;
        }

        if ($reconsume) {
            $retryLimit = max(1, (int) $this->option('reconsume-limit'));
            foreach ($configs as $topic) {
                $stats = $service->reconsumeFailedMessages($topic, $retryLimit);
                $this->line("[{$topic->topic}] attempted={$stats['attempted']} success={$stats['success']} failed={$stats['failed']}");
            }

            return self::SUCCESS;
        }

        $topicNames = $configs->pluck('topic')->values()->all();

        if (! $this->laravel->bound(ConsumerEngine::class)) {
            $this->warn('No ConsumerEngine binding found.');
            $this->line('Bind ' . ConsumerEngine::class . ' in your host app and call gurento:kafka-consume again.');
            $this->line('Requested offset reset: ' . ($fromBeginning ? 'earliest' : 'latest'));
            return self::FAILURE;
        }

        /** @var ConsumerEngine $engine */
        $engine = $this->laravel->make(ConsumerEngine::class);

        $this->info('Starting Kafka consumer...');
        $this->line('Topics: ' . implode(', ', $topicNames));
        $this->line('Offset reset: ' . ($fromBeginning ? 'earliest' : 'latest'));

        $engine->consume(
            $topicNames,
            function (string $topicName, array $payload, array $metadata = []) use ($service): void {
                $service->handle($topicName, $payload, $metadata);
            },
            [
                'from_beginning' => $fromBeginning,
                'offset_reset' => $fromBeginning ? 'earliest' : 'latest',
                'limit' => $limit,
            ]
        );

        return self::SUCCESS;
    }
}
