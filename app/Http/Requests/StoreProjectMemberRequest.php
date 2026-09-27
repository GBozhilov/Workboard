<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('manageMembers', $project);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $project = $this->route('project');

            if (! $project instanceof Project) {
                return;
            }

            $user = User::query()->where('email', $this->input('email'))->first();

            if ($user === null) {
                return;
            }

            if ($user->id === $project->user_id) {
                $validator->errors()->add('email', 'The project owner is already on this project.');
            }

            $existingRole = $project->members()->whereKey($user->id)->value('role');

            if ($existingRole !== null) {
                $validator->errors()->add('email', 'This user is already a member of the project.');
            }
        });
    }

    public function memberRole(): ProjectRole
    {
        return ProjectRole::Member;
    }
}
