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

### Estado atual do Starter

O `composer.json` inclui:

- `pcteckserv/cms-core`, com restrição `2.*`, um repositório `path` em `../cmspcteckserv-core` e versão local declarada `2.6.10`.
- `pcteckserv/cms-contact-forms`, com restrição `*@dev`, proveniente de um repositório `path` com um caminho absoluto da máquina de desenvolvimento.
- Um repositório VCS do Core em `https://github.com/pcteckserv/cmspcteckserv-core.git`.

O `composer.lock` também contém referências `path` para estas duas packages. **Clonar apenas o Starter e executar `composer install` numa nova localização pode falhar.** É necessário preparar uma das opções seguintes antes da instalação. A pasta `packages/cms-core/` não é o repositório configurado em `../cmspcteckserv-core`; não a utilizar como cópia alternativa do Core.

### Opção A — desenvolvimento com packages locais

Clonar o Core ao lado da aplicação, a partir da pasta que contém os projetos:

```bash
git clone https://github.com/pcteckserv/cmspcteckserv-core.git cmspcteckserv-core
```

Utilizar o checkout do Core correspondente à versão que se pretende desenvolver. A versão declarada em `repositories[].options.versions` deve corresponder ao código desse checkout; alterar esse número não muda o código instalado.

Obter junto da equipa o URL e a referência Git do plugin `pcteckserv/cms-contact-forms` e cloná-lo também numa pasta irmã:

```bash
git clone <URL_GIT_DO_PLUGIN> cmspcteckserv-formularios-de-contacto
```

A estrutura final será:

```text
pasta-de-trabalho/
├── cmspcteckserv-core/
├── cmspcteckserv-formularios-de-contacto/
└── cmspcteckserv-starter/  (ou novo-projeto/)
```

No `composer.json` da aplicação, substituir apenas o `url` do repositório com `name: cms-plugin-contact-forms` pelo caminho relativo:

```json
{
    "name": "cms-plugin-contact-forms",
    "type": "path",
    "url": "../cmspcteckserv-formularios-de-contacto"
}
```

Manter o repositório local do Core e o restante conteúdo do ficheiro. No Windows, as ligações simbólicas podem exigir o Modo de Programador ou permissões adequadas. Quando não for possível utilizá-las, configurar `"symlink": false` nas opções dos repositórios `path` para o Composer copiar as packages.

Depois de preparar o `.env` na secção 4, atualizar apenas as referências afetadas no lock e instalar:

```bash
composer update pcteckserv/cms-core pcteckserv/cms-contact-forms --no-install
composer install
composer check-platform-reqs
```

Rever o diff de `composer.lock`: este comando volta a resolver as duas packages e pode selecionar uma referência mais recente do plugin. Validar o checkout e as versões antes de guardar o lock.

### Opção B — projeto independente com packages por Git/Composer

No novo projeto, remover os dois repositórios `path` de `composer.json`, manter o repositório VCS do Core e adicionar o repositório privado oficial do plugin. Exemplo da propriedade `repositories`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/pcteckserv/cmspcteckserv-core.git"
    },
    {
        "type": "vcs",
        "url": "<URL_GIT_DO_PLUGIN>"
    }
]
```

Confirmar com a equipa as tags disponíveis e as restrições em `require`. O Core deve continuar a ser uma dependência versionada; o plugin está atualmente em desenvolvimento e deve usar uma versão estável quando esta estiver disponível.

Autenticar o Git/Composer com uma conta autorizada. Se o Composer pedir um token, fornecê-lo pelo mecanismo de autenticação do Composer, fora dos ficheiros versionados. Nunca colocar tokens no URL do repositório, no `composer.json` ou no README.

Depois de preparar o `.env` na secção 4, executar os mesmos comandos de atualização e instalação da opção A. A alteração de `repositories` não reescreve automaticamente o lock: confirmar que as duas packages deixaram de ter `dist.type: path` e caminhos locais antes de distribuir o projeto.

Em clones seguintes do projeto já preparado, utilizar normalmente:

```bash
composer install
composer check-platform-reqs
```

Guardar `composer.json` e `composer.lock` no repositório do projeto. Nunca editar diretamente `vendor/`.

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

As variáveis `CMS_GITHUB_TOKEN`, `STARTER_GITHUB_REPOSITORY` e `STARTER_GITHUB_TOKEN` estão disponíveis no exemplo para integrações do CMS. Configurá-las apenas quando a funcionalidade utilizada o exigir. A autenticação do Composer é configurada separadamente.

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

O projeto inclui a rota autenticada `/compilar` para gerar um pacote de instalação. Rever primeiro `config/deploy.php`: existe um `local_package_overlays` do Core em `../cmspcteckserv-core`. Num projeto independente, ajustar ou remover essa entrada conforme a origem das packages, para não depender da estrutura da máquina do Starter.

Antes de publicar, executar o build e a suite de testes, configurar o ambiente de produção com `APP_ENV=production`, `APP_DEBUG=false`, HTTPS e credenciais próprias, e apontar o servidor para `public/`. Preservar a `APP_KEY` nas atualizações. Configurar os consumidores das filas e o agendamento no servidor quando necessários. Não utilizar `php artisan serve` como servidor de produção.

## 10. Resolução de problemas

| Sintoma | Verificação / ação |
| --- | --- |
| Composer não encontra uma package `path` | Preparar os diretórios da secção 3 ou migrar os repositórios e o lock para VCS. O fallback VCS não corrige referências locais já guardadas no lock. |
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
