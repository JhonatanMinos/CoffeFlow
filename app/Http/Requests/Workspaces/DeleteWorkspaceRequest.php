<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class DeleteWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->route('workspace'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('name') !== $this->workspace()->name) {
                    $validator->errors()->add('name', __('The workspace name does not match.'));
                }
            },
        ];
    }

    /**
     * Get the workspace associated with the request.
     */
    private function workspace(): Workspace
    {
        $workspace = $this->route('workspace');

        abort_if(! $workspace instanceof Workspace, 404);

        return $workspace;
    }
}
