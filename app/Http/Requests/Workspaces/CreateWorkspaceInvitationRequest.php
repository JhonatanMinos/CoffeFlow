<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Rules\UniqueWorkspaceInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWorkspaceInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');

        abort_if(! $workspace instanceof Workspace, 404);

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueWorkspaceInvitation($workspace)],
            'role' => ['required', 'string', Rule::enum(WorkspaceRole::class)],
        ];
    }
}
