# Validação Core 3.5.0 / MaxiHome 1.42.0 / CG Limpezas 0.1.0 / Starter 2.1.0

Código Core validado: `fed64b8c854c48001f30ce1986021d7ceb8f74a7`. Relatório de 18/11/2026; as alterações posteriores deste relatório são documentação.

- Core: 336 testes aprovados em cada host novo PHP 8.3, 8.4 e 8.5, com SQLite e MariaDB em loopback. Composer real, instalação preparada, snapshots para Git, atualização seguinte, integridade, ausência de .git, instalação sem dev e rollback. JavaScript: 8 testes aprovados.
- MaxiHome: suite completa, 553/553 testes, 5859 asserções. Os assets foram recompilados; um primeiro ensaio tinha assets desatualizados e um timeout de arranque sob carga. A repetição completa passou. A revisão visual detetou uma cópia CG com ficheiros da primeira candidata sob a mesma versão; fixou-se a origem privada para a candidata final, reinstalou-se pelo Composer e repetiram-se a suite CG e os fluxos browser. O ensaio final com IA teve também uma falha de preparação: o caminho do Composer não estava configurado na cópia; após definir DEPLOY_COMPOSER_PHAR, repetiu-se a suite completa.
- CG: 143 testes, 131 aprovados e 12 ignorados previstos, tanto na base 3.0.1 preservada (738 asserções) como na cópia com Core candidato (740). Nenhuma alteração ao Composer, lock ou BackupManager do vendor original; hashes conferidos.
- Starter inicial: 142 testes, 131 aprovados, 11 ignorados previstos e 859 asserções. A falta inicial dos assets na cópia isolada foi resolvida com a compilação; a suite completa passou.
- Browser: quatro cenários, desktop de 1440 px e mobile de 390 px; login, proteção de visitantes, CSRF, Core/Starter/plugins, filtros e paginação dos logs, destinos FTP/Google, callbacks e PKCE, assets e ausência de erros JavaScript. O redirecionamento OAuth foi observado sem autorizar uma conta Google real.
- FTP: dois projetos, PHP real e autoload autoritativo antigo, modo sem dev; chave errada/expirada, outro projeto/linha, payload incompleto/alterado, GET sem mutações, falha após troca e reposição, atualização completa, versões conservadas, linha SQLite/documento/ambiente preservados e finalizador/payload eliminados. A recuperação de Core incoerente foi validada sem publicar os seus ficheiros antes de preparar as dependências.
- Integridade: manifesto regenerado, arquivos Git canónico LF e normal Windows, snapshots e lint/diff verificados. Os scripts, chaves e URLs de recuperação privados não são versionados.

## Limites e passagem

Os ensaios não instalaram código online nem configuraram o cron ou a aplicação OAuth do alojamento. O operador usa o pacote FTP do respetivo site quando o atualizador antigo bloqueia o Core e depois atualiza o Starter pelo painel. A conta Google só fica ligada após a autorização real. As URI autorizadas são `/admin/backups/google/callback` em cada domínio. A execução de schedule:run no alojamento continua necessária para os backups e para a limpeza de auditoria. Migrations executadas não são revertidas automaticamente numa reposição de ficheiros.

Os testes browser referem-se aos fluxos descritos, não a uma aprovação global de todas as suites Playwright históricas. As falhas de referência de fluxos antigos da release 1.41.0 permanecem registadas nessa validação anterior; não se apresentaram como novos resultados aprovados.

O Composer identificou o aviso preexistente de severidade baixa [GHSA-63hm-g96r-2m2q](https://github.com/advisories/GHSA-63hm-g96r-2m2q) no Laravel 13.17 das bases preservadas. A condição descrita envolve a utilização insegura de Concurrency::run; não foram encontradas chamadas diretas nos módulos revistos. Esta entrega conserva a versão do Laravel, conforme o contrato do atualizador; a atualização dessa dependência exige manutenção separada. Não se declarou um audit sem avisos.

O resultado remoto do workflow compatibility deve ser consultado no GitHub; os resultados locais acima são distintos desse resultado.

## IA 0.1.3

16 testes específicos aprovados, 211 asserções: autorização, segredo cifrado, campos de ajuda, Tracking ID/chaves mascaradas, autenticação, permissões, modelo, quota, limites, indisponibilidade, respostas incompletas ou malformadas. As mensagens originais do fornecedor nunca são mostradas. O modelo configurado é conservado. Não foi usada uma chave real nem executado um pedido real à OpenAI; o diagnóstico da conta online só fica disponível depois de instalar a correção e repetir o teste.

## Comandos executados

Todos os hosts são privados em `output`. A matriz usa `CMS_CORE_TEST_APP`, `CMS_COMPOSER_BINARY` e uma MariaDB descartável na porta 33347; ver `output/run-core350c-matrix.cjs`. Os caminhos abaixo são relativos ao workspace salvo indicação de cwd.

```powershell
D:/WEBSITES_NOVOS/output/php83-backups/php.exe output/core350c-host-83sqlite/vendor/phpunit/phpunit/phpunit -c output/core350c-candidate/phpunit.xml --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation --log-junit output/core350c-83sqlite.xml
D:/WEBSITES_NOVOS/.tools/php84/php.exe output/core350c-host-84sqlite/vendor/phpunit/phpunit/phpunit -c output/core350c-candidate/phpunit.xml --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation --log-junit output/core350c-84sqlite.xml
D:/WEBSITES_NOVOS/output/php85-backups/php.exe output/core350c-host-85sqlite/vendor/phpunit/phpunit/phpunit -c output/core350c-candidate/phpunit.xml --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation --log-junit output/core350c-85sqlite.xml
D:/WEBSITES_NOVOS/output/php83-backups/php.exe output/core350c-host-83mysql/vendor/phpunit/phpunit/phpunit -c output/core350c-candidate/phpunit.xml --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation --log-junit output/core350c-83mysql.xml
D:/WEBSITES_NOVOS/.tools/php84/php.exe output/core350c-host-84mysql/vendor/phpunit/phpunit/phpunit -c output/core350c-candidate/phpunit.xml --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation --log-junit output/core350c-84mysql.xml
D:/WEBSITES_NOVOS/output/php85-backups/php.exe output/core350c-host-85mysql/vendor/phpunit/phpunit/phpunit -c output/core350c-candidate/phpunit.xml --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation --log-junit output/core350c-85mysql.xml
node --test cmspcteckserv-core/tests/Js/*.test.mjs
# cwd: output/processes-host-20261117
$env:DEPLOY_COMPOSER_PHAR="D:/WEBSITES_NOVOS/.tools/composer.phar"
D:/WEBSITES_NOVOS/.tools/php84/php.exe vendor/phpunit/phpunit/phpunit --filter "AiModuleTest|IntegrationSetupGuidesTest" --no-coverage
D:/WEBSITES_NOVOS/.tools/php84/php.exe vendor/phpunit/phpunit/phpunit --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation
# cwd: output/cg350-preserved-base; output/core-backups-site-cglimpezas; output/generic350-host
D:/WEBSITES_NOVOS/.tools/php84/php.exe vendor/phpunit/phpunit/phpunit --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation
node output/admin350-browser.cjs
node output/test-core350-bootstrap.cjs maxihome
node output/test-core350-bootstrap.cjs cglimpezas D:/WEBSITES_NOVOS/output/bootstrap350-cglimpezas-test-1795006294817
node output/verify-completed-core350-bootstrap.cjs
node output/validate-admin350-sources.cjs
node output/verify-max-snapshots.cjs
D:/WEBSITES_NOVOS/.tools/php84/php.exe cmspcteckserv-core/tools/verify-release.php cmspcteckserv-core 3.5.0
```

## Ficheiros alterados

Lista relativa a cada repositório. Não inclui dependências instaladas, dados ou ficheiros privados.


**cmspcteckserv-core**

- `config/cms-core.php`
- `core-release.json`
- `docs/atualizacoes-snapshots-3.5.0.md`
- `docs/validacao-3.5.0.md`
- `resources/views/admin/activity-logs/index.blade.php`
- `resources/views/admin/backups/destination.blade.php`
- `resources/views/admin/backups/index.blade.php`
- `resources/views/admin/partials/pagination.blade.php`
- `resources/views/admin/updates/index.blade.php`
- `resources/views/admin/updates/partials/packages-table.blade.php`
- `resources/views/admin/updates/screens/index.blade.php`
- `routes/web.php`
- `src/CmsCoreServiceProvider.php`
- `src/Console/PruneActivityLogsCommand.php`
- `src/Http/Controllers/Admin/ActivityLogController.php`
- `src/Http/Controllers/Admin/BackupGoogleController.php`
- `src/Services/Backups/GoogleDriveBackupService.php`
- `src/Updates/ComposerDeploymentTransaction.php`
- `src/Updates/PackageUpdater.php`
- `src/Updates/StarterDependencyPolicy.php`
- `src/Updates/StarterUpdater.php`
- `tests/Feature/ActivityLogMaintenanceTest.php`
- `tests/Feature/BackupGoogleTest.php`
- `tests/Feature/CompatibleCoreUpdaterTest.php`
- `tests/Feature/StarterDependencyPolicyTest.php`
- `tests/Feature/UpdatesSafetyTest.php`
- `tools/build-snapshot-core-bootstrap.php`
- `tools/templates/snapshot-core-bootstrap.php`

**cmspcteckserv-plugins**

- `cmspcteckserv-ai/README.md`
- `cmspcteckserv-ai/cms-plugin.json`
- `cmspcteckserv-ai/resources/views/settings.blade.php`
- `cmspcteckserv-ai/resources/views/setup-guide.blade.php`
- `cmspcteckserv-ai/src/AiGateway.php`
- `cmspcteckserv-ai/src/AiSettings.php`

**maxihome**

- `CHANGELOG.md`
- `README.md`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/CompatibleStarterUpdater.php`
- `composer.json`
- `composer.lock`
- `docs/VALIDACAO-1.42.0.md`
- `packages/SOURCES.json`
- `packages/runtime/core/config/cms-core.php`
- `packages/runtime/core/core-release.json`
- `packages/runtime/core/docs/atualizacoes-snapshots-3.5.0.md`
- `packages/runtime/core/docs/validacao-3.5.0.md`
- `packages/runtime/core/resources/views/admin/activity-logs/index.blade.php`
- `packages/runtime/core/resources/views/admin/backups/destination.blade.php`
- `packages/runtime/core/resources/views/admin/backups/index.blade.php`
- `packages/runtime/core/resources/views/admin/partials/pagination.blade.php`
- `packages/runtime/core/resources/views/admin/updates/index.blade.php`
- `packages/runtime/core/resources/views/admin/updates/partials/packages-table.blade.php`
- `packages/runtime/core/resources/views/admin/updates/screens/index.blade.php`
- `packages/runtime/core/routes/web.php`
- `packages/runtime/core/src/CmsCoreServiceProvider.php`
- `packages/runtime/core/src/Console/PruneActivityLogsCommand.php`
- `packages/runtime/core/src/Http/Controllers/Admin/ActivityLogController.php`
- `packages/runtime/core/src/Http/Controllers/Admin/BackupGoogleController.php`
- `packages/runtime/core/src/Services/Backups/GoogleDriveBackupService.php`
- `packages/runtime/core/src/Updates/ComposerDeploymentTransaction.php`
- `packages/runtime/core/src/Updates/PackageUpdater.php`
- `packages/runtime/core/src/Updates/StarterDependencyPolicy.php`
- `packages/runtime/core/src/Updates/StarterUpdater.php`
- `packages/runtime/core/tests/Feature/ActivityLogMaintenanceTest.php`
- `packages/runtime/core/tests/Feature/BackupGoogleTest.php`
- `packages/runtime/core/tests/Feature/CompatibleCoreUpdaterTest.php`
- `packages/runtime/core/tests/Feature/StarterDependencyPolicyTest.php`
- `packages/runtime/core/tests/Feature/UpdatesSafetyTest.php`
- `packages/runtime/core/tools/build-snapshot-core-bootstrap.php`
- `packages/runtime/core/tools/templates/snapshot-core-bootstrap.php`
- `packages/runtime/plugins/cmspcteckserv-ai/README.md`
- `packages/runtime/plugins/cmspcteckserv-ai/cms-plugin.json`
- `packages/runtime/plugins/cmspcteckserv-ai/resources/views/settings.blade.php`
- `packages/runtime/plugins/cmspcteckserv-ai/resources/views/setup-guide.blade.php`
- `packages/runtime/plugins/cmspcteckserv-ai/src/AiGateway.php`
- `packages/runtime/plugins/cmspcteckserv-ai/src/AiSettings.php`
- `scripts/sync-package.cjs`
- `tests/Feature/Admin/AiModuleTest.php`
- `tests/Feature/Admin/RealstateTest.php`

**cglimpezas**

- `AGENTS.md`
- `app/Updates/SiteStarterUpdater.php`
- `bootstrap/updates/ComposerDeploymentTransaction.php`
- `bootstrap/updates/CoreReleasePolicy.php`
- `bootstrap/updates/InstalledComposerSnapshot.php`
- `bootstrap/updates/ReleaseIntegrity.php`
- `bootstrap/updates/StarterDependencyPolicy.php`
- `bootstrap/updates/semver-loader.php`
- `bootstrap/updates/semver/LICENSE`
- `bootstrap/updates/semver/ORIGIN.json`
- `bootstrap/updates/semver/src/Comparator.php`
- `bootstrap/updates/semver/src/CompilingMatcher.php`
- `bootstrap/updates/semver/src/Constraint/Bound.php`
- `bootstrap/updates/semver/src/Constraint/Constraint.php`
- `bootstrap/updates/semver/src/Constraint/ConstraintInterface.php`
- `bootstrap/updates/semver/src/Constraint/MatchAllConstraint.php`
- `bootstrap/updates/semver/src/Constraint/MatchNoneConstraint.php`
- `bootstrap/updates/semver/src/Constraint/MultiConstraint.php`
- `bootstrap/updates/semver/src/Interval.php`
- `bootstrap/updates/semver/src/Intervals.php`
- `bootstrap/updates/semver/src/Semver.php`
- `bootstrap/updates/semver/src/VersionParser.php`
- `config/app.php`
- `docs/ATUALIZACOES-0.1.0.md`
- `docs/VALIDACAO-0.1.0.md`
- `resources/views/site-updates/compatibility.blade.php`
- `resources/views/site-updates/packages-table.blade.php`
- `resources/views/vendor/cms-core/admin/updates/index.blade.php`
- `tests/Feature/Admin/SiteUpdatesTest.php`

**cmspcteckserv-starter**

- `README.md`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/CompatibleStarterUpdater.php`
- `composer.json`
- `composer.lock`
- `docs/BASE-CORE3.md`
- `docs/VALIDACAO-2.1.0.md`


## Verificação final da base para novos sites

A instalação remota do Core v3.5.0 foi confirmada no commit 8602767687cbfbb5ea98cd3bb5a57e7a33de6f34. A verificação posterior do lock inicial identificou nove avisos em cinco dependências preexistentes, incluindo [uma falha de severidade alta no Symfony](https://symfony.com/cve-2026-108082). Esta base foi então preparada com Laravel 13.43.0 e Symfony 7.4 corrigido, resolvida para PHP 8.3.36. Não se alteraram dependências dos sites MaxiHome/CG Limpezas nem as suas bases de dados.

O Composer audit --locked --format=json não encontrou avisos nem pacotes abandonados em 18/11/2026. Isto descreve o resultado observado, sem garantir ausência de avisos futuros.

- PHP 83: 142 testes, 131 aprovados, 11 ignorados previstos, 858 asserções.
- PHP 84: 142 testes, 131 aprovados, 11 ignorados previstos, 858 asserções.
- PHP 85: 142 testes, 131 aprovados, 11 ignorados previstos, 858 asserções.
- Browser: página pública, login, autenticação, CSRF, atualizações, plugins, logs, FTP/Google, callback e PKCE; desktop e mobile sem erros JavaScript. A conta Google não foi autorizada.

O teste de exportação cifrada passou a usar uma ligação SQLite real em memória, compatível com a limpeza de ligações do Laravel 13.43, conservando as verificações de não exposição de dados e chaves.

Esta entrega é uma base para instalações novas. Atualizar uma instalação anterior para dependências novas exige publicação preparada, separadamente das atualizações de conteúdo do Starter.

Comandos adicionais executados em cópias privadas:

```powershell
node output/prepare-generic350-remote.cjs
node output/audit-generic350.cjs
node output/secure-generic350.cjs
node output/test-secure-starter350.cjs
# Cada binário PHP executou, em output/generic350-host:
php vendor/phpunit/phpunit/phpunit --no-coverage --fail-on-warning --fail-on-notice --fail-on-deprecation
# Composer, na cópia de preparação:
composer update laravel/framework "symfony/*" --with-dependencies --minimal-changes --no-install --no-scripts --no-interaction
composer audit --locked --format=json
composer install --no-scripts --no-interaction
```

Dependências alteradas nesta base (Core instalado por tag; restantes alterações de segurança isoladas):

| Pacote | Antes | Depois |
|---|---|---|
| `laravel/framework` | v13.36.0 | v13.43.0 |
| `pcteckserv/cms-core` | v3.1.12 | v3.5.0 |
| `symfony/clock` | v8.0.8 | v7.4.21 |
| `symfony/console` | v8.0.15 | v7.4.23 |
| `symfony/css-selector` | v8.0.9 | v7.4.22 |
| `symfony/error-handler` | v8.0.15 | v7.4.22 |
| `symfony/event-dispatcher` | v8.0.15 | v7.4.17 |
| `symfony/finder` | v8.0.14 | v7.4.23 |
| `symfony/http-foundation` | v8.0.15 | v7.4.23 |
| `symfony/http-kernel` | v8.0.15 | v7.4.23 |
| `symfony/mailer` | v8.0.15 | v7.4.22 |
| `symfony/mime` | v8.0.15 | v7.4.22 |
| `symfony/polyfill-php83` | ausente | v1.43.0 |
| `symfony/process` | v8.0.13 | v7.4.22 |
| `symfony/routing` | v8.0.15 | v7.4.23 |
| `symfony/string` | v8.0.15 | v7.4.22 |
| `symfony/translation` | v8.0.14 | v7.4.23 |
| `symfony/uid` | v8.0.9 | v7.4.21 |
| `symfony/var-dumper` | v8.0.15 | v7.4.22 |

Ficheiros desta preparação: `composer.json`, `composer.lock`, `README.md`, `docs/BASE-CORE3.md` `tests/Feature/Deployment/DeploymentPackageContentsTest.php` e este relatório; não foi necessário alterar código de aplicação, tabelas ou permissões.
