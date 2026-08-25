# Gestão de utilizadores e permissões

O `cms-core` fornece uma base reutilizável para utilizadores, roles e permissões administrativas. O Core não depende de `App\Models\User`; o model é resolvido por `config('cms-core.user_model')` ou, em fallback, por `config('auth.providers.users.model')`.

## Convenção de permissões

Usar chaves no formato:

```text
<namespace>.<recurso>.<ação>
```

Exemplos:

```text
core.users.view
core.users.create
blog.posts.update
forms.submissions.view
```

## Permissões Core

```text
core.users.view
core.users.create
core.users.update
core.users.delete
core.users.manage_roles
core.roles.view
core.roles.create
core.roles.update
core.roles.delete
```

## Registo por plugins

Um plugin pode registar permissões no seu Service Provider:

```php
use Pcteckserv\CmsCore\Support\Permissions\PermissionRegistry;

public function boot(PermissionRegistry $permissions): void
{
    $permissions->register([
        'blog.posts.view' => [
            'label' => 'Ver artigos',
            'group' => 'Blog',
        ],
        'blog.posts.create' => [
            'label' => 'Criar artigos',
            'group' => 'Blog',
        ],
    ]);
}
```

Depois de instalar ou atualizar plugins, sincronizar com a base de dados:

```bash
php artisan cms:permissions-sync
```

A sincronização é idempotente: cria ou atualiza permissões registadas e não remove permissões existentes automaticamente.

## Verificações de autorização

Rotas:

```php
Route::middleware('can:blog.posts.view')->group(function () {
    // ...
});
```

Controllers:

```php
$this->authorize('update', $post);
```

Blade:

```blade
@can('blog.posts.create')
    ...
@endcan
```

## Super Admin

A role protegida `core.super_admin` é tratada centralmente por `Gate::before()`. Um Super Admin tem acesso total à área administrativa sem associação manual de todas as permissões.

## Dados adicionais de utilizador

Plugins não devem alterar a migration original de utilizadores do Core. Preferir tabelas próprias com relação ao model autenticável configurado, metadata estruturada quando fizer sentido, eventos e futuras extensões de UI.
