<?php

namespace App\Events;

use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttachmentUploaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public TaskAttachment $attachment,
        public User $actor,
    ) {}
}
