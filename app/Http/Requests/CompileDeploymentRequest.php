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
            'components.*' => ['required', 'string', 'distinct', Rule::in(['base', 'media', 'database', 'plugins', 'repair'])],
            'password' => [
                Rule::excludeIf(fn () => ! $this->needsPassword()),
                Rule::requiredIf(fn () => $this->needsPassword()),
                'nullable', 'string', 'max:255', 'confirmed',
            ],
        ];
    }

    private function needsPassword(): bool
    {
        $components = (array) $this->input('components', []);

        return in_array('database', $components, true) || in_array('repair', $components, true);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $components = (array) $this->input('components', []);
            if (in_array('repair', $components, true) && $components !== ['repair']) {
                $validator->errors()->add('components', 'A reparação deve ser gerada separadamente dos restantes componentes.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'components.required' => 'Selecione pelo menos uma opção para compilar.',
            'components.*.in' => 'A opção de compilação selecionada não é válida.',
            'password.required' => 'Defina uma palavra-passe para proteger o pacote.',
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
