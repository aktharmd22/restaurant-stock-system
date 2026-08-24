<?php

namespace App\Events;

use App\Models\StockRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One event shape for everything that happens to a request.
 *
 * ShouldBroadcastNow, not ShouldBroadcast: on shared hosting the queue runs
 * from cron, so a queued broadcast could arrive a minute late. A minute-late
 * "new request" alert is the problem this app exists to solve, so this one
 * goes out inside the request even though it costs a few hundred milliseconds.
 *
 * When no broadcast driver is configured this is a no-op and the frontend
 * falls back to polling.
 */
class RequestEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $sound  Which of the seven sounds to play.
     */
    public function __construct(
        public readonly StockRequest $request,
        public readonly string $sound,
        public readonly string $message,
        public readonly string $channelName,
        public readonly string $url,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channelName)];
    }

    public function broadcastAs(): string
    {
        return 'request.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->request->id,
            'number' => $this->request->request_number,
            'status' => $this->request->status->value,
            'sound' => $this->sound,
            'message' => $this->message,
            'url' => $this->url,
            'is_late' => $this->request->is_late,
        ];
    }
}
