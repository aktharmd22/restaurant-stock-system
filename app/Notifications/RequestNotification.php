<?php

namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The written record of an alert. A sound can be missed - muted phone, someone
 * in the cold room, a browser that blocked audio - so every alert also lands
 * here, where it can still be found later.
 */
class RequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly StockRequest $request,
        public readonly string $sound,
        public readonly string $message,
        public readonly string $url,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->request->id,
            'number' => $this->request->request_number,
            'status' => $this->request->status->value,
            'sound' => $this->sound,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
