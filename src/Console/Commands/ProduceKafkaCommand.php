<?php

namespace Gurento\KafkaConsumer\Console\Commands;

use Gurento\KafkaConsumer\Models\KafkaProducerTopic;
use Gurento\KafkaConsumer\Services\KafkaProducerService;
use Illuminate\Console\Command;

class ProduceKafkaCommand extends Command
{
    protected $signature = 'gurento:kafka-produce
        {--topic= : Producer topic name to send to}
        {--payload= : Message payload as a JSON string}
        {--key= : Optional message key (overrides the topic key field)}
        {--retry-failed : Retry failed produce logs instead of sending a new message}
        {--retry-limit=50 : Max failed logs per topic to retry}';

    protected $description = 'Produce a Kafka message or retry failed produce logs';

    public function handle(KafkaProducerService $service): int
    {
        if ((bool) $this->option('retry-failed')) {
            return $this->retryFailed($service);
        }

        $topicName = (string) $this->option('topic');

        if ($topicName === '') {
            $this->warn('Provide --topic (and --payload) to send, or use --retry-failed.');
            return self::FAILURE;
        }

        $payload = json_decode((string) $this->option('payload'), true);

        if (! is_array($payload)) {
            $this->error('The --payload option must be a valid JSON object.');
            return self::FAILURE;
        }

        $key = $this->option('key') !== null ? (string) $this->option('key') : null;

        $log = $service->send($topicName, $payload, $key);

        if (! $log) {
            $this->error("No active producer config for topic [{$topicName}].");
            return self::FAILURE;
        }

        if ($log->status === 'sent') {
            $this->info("Message sent to [{$topicName}] (log #{$log->id}).");
            return self::SUCCESS;
        }

        $this->error("Send failed for [{$topicName}] (log #{$log->id}): {$log->error}");
        return self::FAILURE;
    }

    private function retryFailed(KafkaProducerService $service): int
    {
        $retryLimit = max(1, (int) $this->option('retry-limit'));
        $topicName = (string) $this->option('topic');

        $query = KafkaProducerTopic::query()->where('is_active', true);

        if ($topicName !== '') {
            $query->where('topic', $topicName);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->warn('No active producer topics configured.');
            return self::FAILURE;
        }

        foreach ($configs as $topic) {
            $stats = $service->retryFailedMessages($topic, $retryLimit);
            $this->line("[{$topic->topic}] attempted={$stats['attempted']} success={$stats['success']} failed={$stats['failed']}");
        }

        return self::SUCCESS;
    }
}
