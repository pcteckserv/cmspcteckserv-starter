<?php

namespace Pcteckserv\CmsCore\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Pcteckserv\CmsCore\Support\SiteOptions;
use Throwable;

class SmtpSettingsController extends Controller
{
    public function edit(SiteOptions $siteOptions): View
    {
        return view('cms-core::admin.smtp-settings.edit', [
            'options' => $siteOptions->all(),
            'encryptions' => $this->encryptions(),
        ]);
    }

    public function update(Request $request, SiteOptions $siteOptions): RedirectResponse
    {
        $validated = $this->validateSettings($request);

        $siteOptions->setMany($validated);
        $siteOptions->applyMailConfig();
        app('mail.manager')->purge('smtp');

        return back()->with('cms_smtp_success', 'Configuração SMTP guardada com sucesso.');
    }

    public function test(Request $request, SiteOptions $siteOptions): RedirectResponse
    {
        $validated = $request->validate([
            'test_recipient' => ['required', 'email', 'max:255'],
        ]);

        $options = $siteOptions->all();

        if (! filter_var($options['smtp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return back()->with('cms_smtp_error', 'Ative o SMTP antes de enviar um email de teste.');
        }

        try {
            $siteOptions->applyMailConfig();
            app('mail.manager')->purge('smtp');

            Mail::raw('Este email confirma que a configuração SMTP do CMS PCTECK está operacional.', function ($message) use ($validated): void {
                $message
                    ->to($validated['test_recipient'])
                    ->subject('Teste SMTP - CMS PCTECK');
            });
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->with('cms_smtp_error', 'Falha ao enviar email de teste: '.$exception->getMessage());
        }

        return back()->with('cms_smtp_success', 'Email de teste enviado para '.$validated['test_recipient'].'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSettings(Request $request): array
    {
        $validated = $request->validate([
            'smtp_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'required_if:smtp_enabled,1', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'required_if:smtp_enabled,1', 'integer', 'between:1,65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', Rule::in(array_keys($this->encryptions()))],
            'smtp_from_address' => ['nullable', 'required_if:smtp_enabled,1', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'required_if:smtp_enabled,1', 'string', 'max:255'],
        ]);

        $validated['smtp_enabled'] = $request->boolean('smtp_enabled') ? '1' : '0';

        if (($validated['smtp_password'] ?? '') === '') {
            unset($validated['smtp_password']);
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    private function encryptions(): array
    {
        return [
            '' => 'Nenhuma',
            'tls' => 'TLS',
            'ssl' => 'SSL',
        ];
    }
}
