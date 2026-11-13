# Reparação de uma instalação existente

Na página **Compilar o site**, utilize **Modo de reparação**, defina e confirme uma palavra-passe e selecione **Gerar pacote de reparação**. A palavra-passe é obrigatória, sem um número mínimo de caracteres. Esta operação não compila assets nem exporta a base de dados, os uploads ou o código local do site.

Extraia o ZIP no computador. Envie apenas `repair-<identificador>.php` para a pasta pública de produção, ao lado do `index.php`, e abra esse endereço por HTTPS. Não elimine a pasta `app` nem remova `.cms_installed`. O reparador reconhece a aplicação na pasta `app` ao lado da pasta pública ou na pasta que contém a pasta pública.

O acesso expira 24 horas após a geração. A palavra-passe fica guardada apenas como hash no ficheiro gerado. A sessão de acesso expira após 15 minutos de inatividade. Cinco tentativas falhadas bloqueiam novas tentativas durante cinco minutos, mesmo entre sessões distintas. Os formulários têm proteção CSRF e as operações de reparação utilizam um bloqueio por aplicação.

## Operações

1. **Diagnosticar instalação**: compara as versões dos packages CMS no lock com o registo instalado, sem arrancar o Laravel.
2. **Reparar dependências do lock**: instala as versões do `composer.lock` que já existe em produção, com `--no-dev --prefer-dist --optimize-autoloader --no-scripts --no-plugins`. Exige confirmação de backup. Pode substituir ficheiros em `vendor` e remover dependências de desenvolvimento. Não executa scripts Composer ou migrations e não altera a constraint do Core.
3. **Limpar caches**: remove apenas os manifests conhecidos de arranque em `bootstrap/cache` e executa a limpeza de configuração, rotas, views e eventos. Não executa `cache:clear`, evitando limpar a cache de dados da aplicação.
4. **Descobrir packages**: executa `package:discover` após a recuperação das dependências e das caches.

### Erro «The .git directory is missing» no Core

Um pacote exportado pode conter o código do Core sem a pasta `.git`, embora o Composer o registe como instalado por Git/source. Nesse estado, a atualização Git normal falha.

Utilize **Recuperar Core sem metadados Git** e confirme a autorização. O reparador copia o Core, os metadados de `vendor/composer` e o autoload para uma pasta privada em `storage/app/private/cms-repair-backups`. Valida a instalação do lock através de uma simulação e prepara o commit exato do Core indicado no `composer.lock` de produção. Assim, a recuperação funciona mesmo quando o commit antigo já não existe na origem Git. A pasta original é preservada antes de trocar o Core. Em seguida, o próprio Composer conclui a instalação do lock, sem scripts, plugins ou migrations. O `.env`, os uploads e a base de dados não são importados ou substituídos.

Se a simulação, a clonagem ou o checkout falhar, a pasta original permanece no lugar. Se a troca de pastas falhar, tenta repor o Core, o autoload e os metadados anteriores. Depois de iniciar a instalação efetiva do lock, uma falha mantém o Core recuperado com Git e preserva a cópia anterior, sem repor metadados antigos sobre outras dependências que possam ter sido parcialmente instaladas. Nesse caso, utilize **Reparar dependências do lock** para continuar. Uma interrupção por timeout não inicia a reposição automática. O resultado indica o identificador da cópia; mantenha-a até validar o site.

Depois do sucesso da recuperação, siga os passos de limpeza de caches e descoberta de packages. O sucesso só é confirmado depois de verificar que a versão e o commit instalados do Core coincidem com o lock. Não elimine toda a pasta `app` ou `vendor`.

Também é possível reconstruir o autoload e consultar as migrations. **Aplicar migrations pendentes** é uma ação separada: exige confirmação explícita e pode alterar a estrutura e os dados conforme as migrations existentes. Não há operações de reset, importação ou seed.

Os resultados aparecem no browser, com valores sensíveis do `.env`, credenciais em URLs e cabeçalhos de autenticação ocultados. Relatórios acima de 64 KiB apresentam a parte final para preservar o erro. Não são criados logs públicos.

Ao terminar, selecione **Terminar e desativar reparador** e elimine o ficheiro PHP por WinSCP. A desativação mantém-se entre sessões.

## Requisitos e limites

- Faça um backup e evite atualizações simultâneas pelo CMS, Composer ou outro processo de deploy.
- O alojamento precisa de permitir `proc_open`, ficheiros temporários, sessões e um executável PHP CLI 8.3 ou superior. A ferramenta inclui o Composer; não exige Composer instalado no servidor.
- Se o PHP CLI não for detetado, configure `CMS_PHP_CLI_BINARY` no `.env` com o caminho fornecido pelo alojamento. O reparador não altera o `.env`.
- Para repositórios privados GitHub, é utilizado `CMS_GITHUB_TOKEN` do `.env`, sem o incluir nos argumentos dos comandos ou na página. Outros mecanismos de autenticação podem exigir configuração no alojamento.
- O reparador não descarta alterações locais no Git. Uma falha de permissões, autenticação, compatibilidade ou ficheiros modificados é apresentada para diagnóstico.
- Cada operação tem um limite de 300 segundos. Limites inferiores do servidor web ou do alojamento também se aplicam. Após uma interrupção, verifique o estado antes de repetir.
- A ferramenta não atualiza o código para uma versão diferente do lock de produção e não substitui automaticamente o código por uma cópia local.
