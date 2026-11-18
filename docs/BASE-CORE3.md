## Starter 2.1.0 / Core 3.5.0

Esta distribuição para novos sites usa Laravel 13.43 e Symfony 7.4 com correções de segurança, resolvidos para PHP 8.3.36. A auditoria do lock anterior identificou nove avisos em cinco pacotes; o lock preparado não apresenta avisos conhecidos no Composer em 18/11/2026. Esta manutenção é exclusiva do Starter inicial e não atualiza as dependências das instalações MaxiHome ou CG Limpezas. Consultar `VALIDACAO-2.1.0.md` para os testes e limites.

A base usa o atualizador central. O Core é instalado pelo repositório publicado; o Starter conserva Composer, lock e vendor nas atualizações de conteúdo. Validar o requisito de Core antes de copiar ficheiros, sem exigir a versão exata do lock da origem. O ecrã comum separa Core, Starter e plugins ativos instalados. Não copiar atualizadores nem criar tabelas de clientes/autenticação nos próximos sites; seguir o AGENTS.md partilhado.

Google Drive usa `/admin/backups/google/callback`, registado uma vez na aplicação OAuth do novo domínio. Configurar o cron `schedule:run` para backups e retenção dos logs. O desenvolvimento não configura o alojamento nem autoriza contas externas.

# Starter 2.0.0 — base para novos sites

- Core: `pcteckserv/cms-core`, linha 3, lock inicial v3.1.12.
- Formulários: `pcteckserv/cms-contact-forms` 2.0.0.
- Origens: repositórios `pcteckserv/cmspcteckserv-core`,
  `pcteckserv/cmspcteckserv-plugins` e `pcteckserv/cmspcteckserv-starter`.
- Nomes atuais: Clientes, Loja e Personalização. Os pacotes Composer mantêm-se.
- Catálogo compatível com pastas antigas durante a transição, sem duplicados.

Criar cada novo site a partir desta base e configurar a sua própria identidade,
domínio, repositório e acesso de instalação. Instalar e ativar apenas os plugins
necessários pelo painel, respeitando as dependências e permissões.

Não copiar `.env`, dados de clientes ou contas administrativas de outro site.
Não utilizar MaxiHome ou CG Limpezas como substitutos do Starter genérico.
Guardar as personalizações do cliente no projeto desse cliente e as capacidades
comuns nos plugins ou no Core, conforme a arquitetura existente.

Core, Starter e plugins continuam com atualizações independentes. Esta base não
autoriza migração automática de instalações Core 2. Para um site já existente,
preservar o seu ambiente e versões, preparar uma cópia e validar a migração antes
de trocar a linha do Core. A nova base não deve ser aplicada sobre um Starter
1.x/Core 2 como uma atualização comum do painel.

Nos sites Core 3 existentes, atualizar primeiro o catálogo do Starter compatível
e só depois os plugins. A mudança de pasta ou label não muda o slug, as tabelas,
os identificadores de clientes, os guards ou as permissões instaladas.
