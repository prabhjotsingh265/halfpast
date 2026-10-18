<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A queued job that gave up after all its tries, as Laravel records it.
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property CarbonImmutable $failed_at
 */
class FailedJob extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['failed_at' => 'immutable_datetime'];
    }

    /** The job's class, without its namespace: "SendRefund". */
    public function name(): string
    {
        $payload = json_decode($this->payload, true);
        $class = is_array($payload) ? (string) ($payload['displayName'] ?? '') : '';

        return $class === '' ? 'Unknown job' : class_basename($class);
    }

    /** The first line of what went wrong. */
    public function reason(): string
    {
        return strtok($this->exception, "\n") ?: '';
    }
}
