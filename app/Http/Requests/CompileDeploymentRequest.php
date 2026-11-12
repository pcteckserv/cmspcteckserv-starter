<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CompileDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCmsSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'components' => ['required', 'array', 'min:1', 'max:4'],
            'components.*' => ['required', 'string', 'distinct', Rule::in(['base', 'media', 'database', 'plugins'])],
            'password' => [
                Rule::excludeIf(fn () => ! in_array('database', (array) $this->input('components', []), true)),
                Rule::requiredIf(fn () => in_array('database', (array) $this->input('components', []), true)),
                'nullable', 'string', 'max:255', 'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'components.required' => 'Selecione pelo menos uma opção para compilar.',
            'components.*.in' => 'A opção de compilação selecionada não é válida.',
            'password.required' => 'Defina uma palavra-passe para proteger a base de dados.',
            'password.confirmed' => 'A confirmação da palavra-passe não coincide.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(redirect($this->getRedirectUrl())
            ->withErrors($validator)
            ->withInput($this->only('components')));
    }
}
