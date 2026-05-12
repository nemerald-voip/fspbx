<?php

namespace App\Http\Requests;

class UpdateBasicQueueRequest extends StoreBasicQueueRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_center_queue_edit');
    }

    protected function queueUuid(): ?string
    {
        $queue = $this->route('queue');

        return is_object($queue)
            ? $queue->call_center_queue_uuid
            : $queue;
    }
}
