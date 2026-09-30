<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\PaginatesApiRequests;
use App\Http\Requests\FilterProjectTasksRequest;

class IndexApiTaskRequest extends FilterProjectTasksRequest
{
    use PaginatesApiRequests;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), $this->perPageRules());
    }
}
