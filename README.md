# CMS PCTECKSERV — Starter

Aplicação Laravel utilizada como ponto de partida para novos websites. O CMS é fornecido pela package `pcteckserv/cms-core`; o tema, o frontend e as personalizações do cliente pertencem a cada projeto.

Este guia cobre a instalação numa máquina nova, a execução local e a criação de um novo projeto a partir do Starter. Executar os comandos na pasta da aplicação, salvo indicação em contrário. Os exemplos `bash` também funcionam em PowerShell; substituir os valores entre `<...>` antes de executar.

## 1. Requisitos

- Git e acesso aos repositórios privados da empresa.
- PHP 8.3 ou superior, compatível com o `composer.lock`, e Composer 2.
- Extensões PHP exigidas pelas dependências; para SQLite, ativar `pdo_sqlite` e `sqlite3`. Para MySQL, ativar `pdo_mysql`. A compilação de pacotes de instalação também necessita de `zip`.
- Node.js compatível com o Vite instalado: `^20.19.0 || >=22.12.0`, e npm.
- SQLite para começar rapidamente, ou uma base de dados MySQL/MariaDB dedicada ao projeto.
- Permissões de escrita em `storage/` e `bootstrap/cache/`.

Confirmar as ferramentas disponíveis no terminal:

```bash
git --version
php --version
php --ini
composer --version
node --version
npm --version
```

No Windows, confirmar que o PHP do terminal é o pretendido, sobretudo quando existem várias versões no WAMP. O PHP utilizado pelo servidor web deve ter as mesmas extensões necessárias.

## 2. Clonar o Starter

Numa pasta de trabalho:

```bash
git clone https://github.com/pcteckserv/cmspcteckserv-starter.git
cd cmspcteckserv-starter
```

Para criar diretamente um projeto de cliente, utilizar o nome desse projeto:

```bash
git clone https://github.com/pcteckserv/cmspcteckserv-starter.git novo-projeto
cd novo-projeto
```

Escolher apenas uma destas alternativas. A partir daqui, seguir os passos de instalação nessa pasta. A secção 8 explica como associar o projeto ao seu próprio repositório.

## 3. Preparar as dependências privadas antes de instalar

O Core é obtido diretamente de `https://github.com/diogoleitean/pcteckcms_core.git`. A restrição mantém-se em `2.*`; o lock atual fixa a versão `v2.6.10`.

O plugin de formulários pertence ao repositório `https://github.com/diogoleitean/pcteckcms_plugins.git`. Antes da primeira instalação, descarregar o catálogo para o caminho relativo esperado pelo Composer:

```bash
git clone --depth 1 https://github.com/diogoleitean/pcteckcms_plugins.git storage/framework/cache/cms-plugin-repository
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
composer install
composer check-platform-reqs
```

Executar o clone apenas se essa pasta ainda não existir. O repositório contém o catálogo completo, mas apenas as packages declaradas no Composer são instaladas. O plugin é copiado para `vendor/` com `symlink: false`; o Core é instalado por Git, sem depender de uma pasta irmã da aplicação.

A pasta `packages/cms-core/` não é a dependência configurada e não deve ser utilizada como cópia alternativa do Core.

Autenticar o Git/Composer com uma conta autorizada a ler os dois repositórios. Nunca colocar tokens nos URLs ou em ficheiros versionados. O token `CMS_GITHUB_TOKEN` do `.env` é utilizado pelo CMS; o Composer executado diretamente no terminal tem a sua própria autenticação.

Em clones seguintes, preservar as versões de `composer.lock` e executar `composer install`. Guardar `composer.json` e `composer.lock` no repositório do projeto. Nunca editar diretamente `vendor/`.

## 4. Configurar o ambiente

Criar o `.env` apenas se ainda não existir, sem substituir uma configuração existente:

```bash
php -r "file_exists('.env') || copy('.env.example', '.env');"
```

Editar o `.env` e definir os dados do projeto:

```dotenv
APP_NAME="Novo Projeto"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=pt_PT
APP_FALLBACK_LOCALE=pt_PT
APP_FAKER_LOCALE=pt_PT

DB_CONNECTION=sqlite

ADMIN_USER_NAME="Administrador"
ADMIN_USER_EMAIL=
ADMIN_USER_PASSWORD=
```

Preencher `ADMIN_USER_EMAIL` com o endereço do administrador e `ADMIN_USER_PASSWORD` com uma palavra-passe forte e exclusiva antes de executar o seeder. Colocar valores com espaços entre aspas. Não existem credenciais de acesso universais.

O `.env.example` usa sessões, cache e filas em base de dados, pelo que as migrations são necessárias antes de utilizar a aplicação. Com `MAIL_MAILER=log`, os emails ficam nos logs locais; configurar SMTP quando for necessário entregar emails reais.

O `.env.example` já define `CMS_CORE_REPOSITORY_URL` e `CMS_PLUGINS_REPOSITORY_URL` com as novas origens. Configurar `CMS_GITHUB_TOKEN` com acesso de leitura aos dois repositórios para consultas e instalações pelo CMS. As variáveis `STARTER_GITHUB_REPOSITORY` e `STARTER_GITHUB_TOKEN` mantêm a configuração própria das atualizações do website. A autenticação do Composer no terminal é configurada separadamente.

### SQLite

Criar o ficheiro sem apagar uma base de dados existente:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Deixar `DB_DATABASE` por definir para utilizar o ficheiro SQLite padrão do Laravel. Se optar por outra localização, definir o caminho absoluto em `DB_DATABASE`.

### MySQL/MariaDB

Em alternativa, criar uma base de dados vazia dedicada ao projeto e um utilizador com acesso a essa base de dados. Ajustar o `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=novo_projeto
DB_USERNAME=
DB_PASSWORD=
```

Preencher as credenciais localmente. Não utilizar a base de dados de outro projeto nem dados de produção para esta instalação.

## 5. Instalar e inicializar

Se ainda não o fez, executar agora os comandos Composer da opção escolhida na secção 3. Depois, numa instalação nova:

```bash
php artisan key:generate
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan storage:link
npm ci --ignore-scripts
npm run build
```

- Gerar `APP_KEY` uma única vez por ambiente novo. Preservar a chave de instalações existentes, pois protege dados cifrados.
- `migrate` executa as migrations da aplicação e das packages descobertas pelo Laravel.
- `db:seed` chama o `AdminUserSeeder` do Core com os valores `ADMIN_USER_*`. Utilizá-lo para a inicialização; não o executar como passo obrigatório de cada arranque.
- `storage:link` disponibiliza os ficheiros do disco público. No Windows, pode ser necessário ativar o Modo de Programador ou obter permissões para criar a ligação.
- `npm ci` utiliza o `package-lock.json`. A opção `--ignore-scripts` mantém a política definida em `.npmrc`.
- O build inclui os assets públicos e administrativos do Core; instalar as dependências PHP antes de compilar.

Existe também `composer run setup`, que instala dependências, cria o `.env` se faltar, gera a chave, executa migrations com `--force`, corre `npm install --ignore-scripts` e compila os assets. Utilizá-lo apenas numa instalação nova, depois de preparar os repositórios e a base de dados. Não substitui a configuração do administrador, o seeder ou `storage:link`, e volta a gerar a chave se for repetido.

## 6. Executar localmente

### Arranque conjunto

```bash
composer run dev
```

O comando inicia os processos registados pelo Laravel: servidor PHP, consumidor da fila e Vite. Para confirmar a lista:

```bash
php artisan dev:list
```

Manter o terminal aberto e utilizar `Ctrl+C` para parar. Se o arranque conjunto não funcionar no ambiente utilizado, abrir terminais separados na pasta da aplicação:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

```bash
npm run dev
```

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Para funcionalidades com tarefas agendadas, iniciar também, num terminal separado:

```bash
php artisan schedule:work
```

### Endereços e acesso

- Website: `http://localhost:8000/`.
- Iniciar sessão: `http://localhost:8000/login`.
- Administração: `http://localhost:8000/admin`.

Utilizar o email e a palavra-passe definidos para o administrador. Se alterar a porta ou utilizar um domínio local, ajustar `APP_URL` e executar `php artisan config:clear`.

Com WAMP, Apache ou Nginx, apontar a raiz pública do site para a pasta `public/` da aplicação. Nunca servir a raiz do projeto. Nesse caso, o servidor web substitui `php artisan serve`; o Vite e o consumidor da fila continuam a ser processos separados.

### Arranques seguintes

Com o ambiente já instalado, basta iniciar os processos. Após obter alterações do repositório que atualizem dependências ou migrations, parar os processos e executar:

```bash
composer install
npm ci --ignore-scripts
php artisan migrate
npm run build
```

Voltar a iniciar os processos de desenvolvimento. Não regenerar a chave nem recriar a base de dados a cada arranque.

## 7. Verificar a instalação e executar testes

```bash
php artisan migrate:status
php artisan route:list
php artisan test tests/Feature/Admin/AdminUserSeederTest.php
php artisan test tests/Feature/Auth/AuthenticationTest.php
php artisan test
```

A suite utiliza SQLite em memória através de `phpunit.xml`. Confirmar que `pdo_sqlite` está ativo e que não existe configuração em cache a interferir com o ambiente de testes. O script `composer run test` limpa a configuração em cache antes de executar a suite completa.

Confirmar também no navegador que a página inicial abre, que o administrador consegue iniciar sessão e que a área administrativa carrega os estilos e o JavaScript.

## 8. Iniciar um novo projeto com repositório próprio

Depois de clonar para `novo-projeto` e seguir as secções 3 a 7:

1. Definir o nome, URL, base de dados e administrador exclusivos do projeto no `.env`.
2. Atualizar os metadados do projeto no `composer.json` e adaptar este README ao site.
3. Criar um repositório Git vazio para o cliente, sem o inicializar com ficheiros adicionais.
4. Na pasta do novo projeto, mudar os remotos para impedir que alterações do cliente sejam enviadas para o Starter:

```bash
git remote rename origin starter
git remote add origin <URL_GIT_DO_NOVO_PROJETO>
git remote -v
git switch -c projeto/inicial
```

Esta opção conserva o histórico do Starter e associa `origin` ao novo projeto. Se for necessário começar sem esse histórico, criar o repositório através da funcionalidade de template da plataforma Git, quando disponível, e clonar esse novo repositório.

Antes do primeiro commit, rever os ficheiros:

```bash
git status --short
git diff
```

Confirmar que `.env`, credenciais, tokens, bases de dados locais, uploads e backups não estão incluídos. Depois, adicionar apenas os ficheiros pretendidos e enviar a branch:

```bash
git add README.md composer.json composer.lock
git commit -m "Configura o projeto a partir do Starter"
git push -u origin projeto/inicial
```

Adicionar explicitamente os restantes ficheiros do tema ou da aplicação que tenham sido criados. Integrar esta branch segundo o fluxo do repositório do cliente.

### Organização e atualizações

- Frontend do cliente: `resources/views/public/`, `resources/css/site.scss` e `resources/js/site.js`.
- Rotas específicas do site: `routes/`.
- Configuração e lógica próprias do cliente: `config/` e `app/`.
- Funcionalidades comuns do CMS: package Core.
- Funcionalidades opcionais: plugins independentes.

Não copiar controllers, models ou services do Core para os modificar no site. Utilizar os mecanismos de extensão, configuração e substituição de views disponibilizados pela package.

Depois da criação, o site evolui no seu repositório. As correções comuns chegam através de versões do Core e dos plugins, sem sincronizar o código do cliente com o Starter. Num projeto que já utiliza repositórios VCS, atualizar o Core de forma controlada:

```bash
composer update pcteckserv/cms-core --with-dependencies
php artisan migrate
npm run build
php artisan test
```

Rever as versões e o diff do lock e testar antes de publicar. Se ainda utilizar packages locais, confirmar o checkout efetivamente utilizado; o Composer pode apontar para código partilhado por outros projetos.

## 9. Preparar publicação

O projeto inclui a rota autenticada `/compilar` para gerar um pacote de instalação. O Core é incluído a partir da dependência instalada, sem sobreposição de uma pasta externa. O compilador inclui as origens dos plugins `path` no pacote de produção. O instalador escreve os novos URLs do Core e dos plugins no `.env` de produção; o token deve ter acesso aos dois repositórios.

Antes de publicar, executar o build e a suite de testes, configurar o ambiente de produção com `APP_ENV=production`, `APP_DEBUG=false`, HTTPS e credenciais próprias, e apontar o servidor para `public/`. Preservar a `APP_KEY` nas atualizações. Configurar os consumidores das filas e o agendamento no servidor quando necessários. Não utilizar `php artisan serve` como servidor de produção.

## 10. Resolução de problemas

| Sintoma | Verificação / ação |
| --- | --- |
| Composer não encontra uma package `path` | Preparar a cópia do repositório de plugins conforme a secção 3. Não substituir nem eliminar essa pasta enquanto o Composer a utilizar como origem de packages. |
| Acesso negado a um repositório privado | Confirmar acesso da conta e autenticação Git/Composer, sem guardar tokens no código. |
| `could not find driver` | Ativar o driver PDO da base de dados no PHP utilizado pelo terminal e pelo servidor. |
| SQLite inexistente ou tabelas em falta | Criar `database/database.sqlite`, confirmar o `.env` e executar `php artisan migrate`. |
| Falta de `APP_KEY` | Numa instalação nova, executar `php artisan key:generate`. Numa instalação existente, recuperar a chave original. |
| O seeder pede email ou palavra-passe | Preencher `ADMIN_USER_EMAIL` e `ADMIN_USER_PASSWORD`, executar `php artisan config:clear` e repetir a inicialização. |
| `Vite manifest not found` ou assets indisponíveis | Instalar primeiro as packages PHP e executar `npm ci --ignore-scripts` e `npm run build`, ou manter `npm run dev` ativo. |
| Vite recusa a versão do Node.js | Utilizar uma versão que cumpra os requisitos da secção 1. |
| Falha na transferência de fontes durante o build | O Vite utiliza Bunny Fonts; confirmar conectividade e repetir o build. |
| Falha ao criar ligações simbólicas no Windows | Verificar permissões/Modo de Programador; para packages Composer, considerar `symlink: false`. |
| Alterações do `.env` não são aplicadas | Executar `php artisan config:clear` e reiniciar os processos persistentes. |
| Uploads públicos não aparecem | Confirmar `php artisan storage:link`, o disco configurado e permissões de escrita. |
| Emails não chegam | O valor inicial `MAIL_MAILER=log` não envia emails; configurar SMTP e confirmar o consumidor da fila quando aplicável. |
| Porta 8000 ocupada | Usar outra porta em `php artisan serve --port=8001` e ajustar `APP_URL`. |

Não utilizar `migrate:fresh`, `db:wipe` ou a eliminação da base de dados como solução para erros de instalação: estes procedimentos destroem dados.
