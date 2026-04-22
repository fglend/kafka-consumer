<?php

return [
    'default_group' => env('KAFKA_CONSUMER_GROUP_ID', 'app-consumer'),
    'max_reconsume_attempts' => 3,
    'retry_backoff_seconds' => 60,
    'health_stale_after_seconds' => 300,
];
