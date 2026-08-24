# AGENTS.md

## 1. Objetivo do Projeto

Este projeto é uma plataforma CMS desenvolvida em Laravel para utilização interna da empresa.

A arquitetura deve separar claramente o núcleo reutilizável e versionado do CMS das implementações específicas de cada site.

O código comum do CMS deve possuir uma única fonte de verdade e ser preparado para distribuição e atualização através de uma package Laravel privada versionada por Composer/Git.

Os projetos de clientes não devem tornar-se cópias independentes do código Core.

O desenvolvimento deve dar prioridade a:

- Segurança
- Reutilização de código
- Modularidade
- Manutenção a longo prazo
- Escalabilidade
- Consistência
- Testabilidade
- Simplicidade

Todas as alterações devem respeitar a arquitetura existente e evitar soluções específicas que dificultem a reutilização futura do CMS noutros projetos.

---

## 2. Idioma e Codificação

Todo o conteúdo destinado ao utilizador deve ser escrito em Português de Portugal (pt-PT).

Isto inclui:

- Labels
- Mensagens de validação
- Mensagens de erro
- Mensagens de sucesso
- Botões
- Tooltips
- Placeholders
- Emails
- Notificações
- Textos da área administrativa
- Comentários relevantes para documentação
- Conteúdo apresentado no frontend

Não utilizar Português do Brasil.

Exemplos:

- Utilizar "utilizador" em vez de "usuário".
- Utilizar "palavra-passe" em vez de "senha".
- Utilizar "ficheiro" em vez de "arquivo", quando aplicável.
- Utilizar "eliminar" ou "remover" em vez de termos brasileiros.

Todos os ficheiros devem utilizar UTF-8.

Nunca introduzir problemas de codificação ou caracteres corrompidos. Antes de concluir alterações que envolvam textos, verificar que caracteres como os seguintes permanecem corretamente codificados:

```text
á à ã â
é ê
í
ó õ ô
ú
ç
Á À Ã Â
É Ê
Í
Ó Õ Ô
Ú
Ç
```

Nunca substituir texto corretamente escrito apenas para contornar problemas de encoding.

---

## 3. Princípios de Desenvolvimento

Todo o desenvolvimento deve respeitar, sempre que aplicável:

- SOLID
- DRY
- KISS
- Separation of Concerns
- Single Responsibility Principle
- Encapsulation
- Dependency Injection
- Composition over inheritance

Evitar overengineering.

Não criar abstrações sem necessidade real. No entanto, código com potencial de reutilização deve ser isolado e desenvolvido de forma reutilizável.

---

## 4. Analisar Antes de Implementar

Antes de alterar ou criar qualquer funcionalidade:

1. Analisar o código existente relacionado com a funcionalidade.
2. Identificar os padrões já utilizados no projeto.
3. Verificar se já existem estruturas reutilizáveis.
4. Evitar duplicar código ou criar implementações paralelas da mesma lógica.
5. Seguir os padrões existentes sempre que estes sejam adequados.

Estruturas a procurar antes de implementar:

- Services
- Actions
- Components
- View Components
- Blade Components
- Traits
- Helpers
- Form Requests
- Policies
- Queries
- Repositories
- Models
- DTOs
- JavaScript modules
- Test utilities

Não criar uma nova arquitetura para resolver um problema que já tem um padrão estabelecido no projeto.

---

## 5. Arquitetura Modular

Sempre que possível, desenvolver funcionalidades de forma modular e reutilizável.

Uma funcionalidade reutilizável não deve ficar presa a uma página, controller ou view específica.

Exemplo: se existir um popover para pesquisar e selecionar categorias de produtos, a lógica desse popover deve ser desenvolvida num componente ou módulo independente, para que possa ser reutilizada noutras partes do CMS.

Preferir, consoante o contexto:

- Blade Components
- View Components
- Services
- Actions
- Form Requests
- Policies
- JavaScript modules
- Classes dedicadas
- Componentes frontend reutilizáveis

Evitar:

- Grandes blocos JavaScript diretamente dentro das views.
- Lógica de negócio dentro de templates Blade.
- Queries complexas diretamente nas views.
- Controllers excessivamente grandes.
- Duplicação de HTML para componentes iguais.
- Copiar e colar funcionalidades entre páginas.

---

## 6. Fluxo Recomendado

O fluxo padrão para lógica de aplicação deve ser:

```text
Request
   ↓
Form Request
   ↓
Controller
   ↓
Policy
   ↓
Action / Service
   ↓
Model / Database
   ↓
Response
```

Não é obrigatório transformar todas as operações numa Action ou Service. Para CRUD simples, aplicar KISS. Quando a lógica deixa de ser trivial, movê-la para uma classe dedicada.

---

## 7. Controllers

Os controllers devem ser simples.

Um controller deve principalmente:

1. Receber o pedido.
2. Delegar validação.
3. Verificar autorização.
4. Chamar a lógica de negócio apropriada.
5. Preparar a resposta.

Evitar colocar lógica complexa de negócio diretamente nos controllers. Quando a lógica crescer, movê-la para uma classe apropriada, como:

- Service
- Action
- Domain class
- Query object

Evitar "God Controllers".

---

## 8. Models

Os Models não devem tornar-se classes gigantes com toda a lógica da aplicação.

Utilizar Models principalmente para:

- Relações Eloquent
- Casts
- Scopes
- Atributos relacionados diretamente com a entidade
- Comportamento diretamente associado ao Model

Lógica complexa ou processos envolvendo múltiplas entidades devem ser movidos para Services, Actions ou outras classes apropriadas.

---

## 9. Autenticação e Autorização

A área administrativa deve utilizar o sistema de autenticação do Laravel.

Nunca implementar autenticação personalizada quando os mecanismos seguros fornecidos pelo Laravel forem suficientes.

Todas as rotas administrativas devem estar protegidas por autenticação.

```php
Route::middleware('auth')->group(function () {
    // Área administrativa
});
```

Quando existirem diferentes níveis de acesso, utilizar mecanismos adequados como:

- Policies
- Gates
- Middleware
- Roles
- Permissions

Autenticação e autorização são conceitos diferentes.

O facto de um utilizador estar autenticado não significa automaticamente que está autorizado a executar determinada ação.

Exemplo de risco:

```http
DELETE /admin/users/45
```

Um utilizador autenticado não deve conseguir executar esta ação sem autorização explícita. Policies e permissões devem fazer parte da arquitetura desde o princípio.

---

## 10. Segurança

A segurança é uma prioridade permanente do projeto.

Nunca confiar em dados provenientes do frontend. Todos os inputs devem ser considerados potencialmente maliciosos.

Toda a validação importante deve acontecer no backend, mesmo que exista também validação JavaScript no frontend.

---

## 11. Validação de Dados

Preferir Laravel Form Requests para validações não triviais.

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email'],
    ];
}
```

Nunca confiar apenas em:

- Campos hidden
- Selects
- JavaScript
- Atributos disabled
- Validação HTML
- IDs recebidos do browser

Qualquer valor enviado pelo cliente pode ser manipulado.

Sempre validar:

- Tipo
- Formato
- Limites
- Permissões
- Existência
- Relação entre entidades
- Valores permitidos

---

## 12. Proteção das Rotas

Nunca expor dados privados através de rotas públicas.

Verificar sempre:

- Middleware de autenticação
- Middleware de autorização
- Policies
- Ownership dos recursos

Um utilizador nunca deve conseguir aceder aos dados de outro utilizador ou entidade simplesmente alterando um ID no URL.

Exemplo de risco:

```text
/admin/orders/123
/admin/orders/124
```

O backend deve verificar se o utilizador tem autorização para consultar cada recurso.

---

## 13. Mass Assignment

Nunca utilizar dados recebidos diretamente do request sem controlo.

Evitar:

```php
Model::create($request->all());
```

Preferir:

```php
Model::create($request->validated());
```

Ou construir explicitamente os campos necessários.

Manter `$fillable` ou `$guarded` corretamente configurados.

---

## 14. SQL Injection

Utilizar Eloquent e Query Builder sempre que possível.

Nunca concatenar diretamente inputs do utilizador em queries SQL.

Evitar:

```php
DB::select("SELECT * FROM users WHERE email = '$email'");
```

Utilizar parâmetros preparados.

Qualquer utilização de SQL raw deve ser analisada cuidadosamente.

---

## 15. XSS

Nunca apresentar conteúdo fornecido pelo utilizador sem escaping apropriado.

Em Blade, preferir:

```blade
{{ $value }}
```

Em vez de:

```blade
{!! $value !!}
```

Utilizar output não escapado apenas quando estritamente necessário e quando o conteúdo tiver sido previamente sanitizado.

---

## 16. CSRF

Todos os formulários que alterem estado devem utilizar proteção CSRF do Laravel.

Nunca desativar CSRF globalmente para resolver problemas de implementação.

Exceções devem ser extremamente específicas e justificadas.

---

## 17. Upload de Ficheiros

Uploads devem ser tratados como potencialmente perigosos.

Sempre validar:

- MIME type
- Extensão
- Tamanho
- Tipo de ficheiro
- Nome
- Local de armazenamento

Nunca confiar exclusivamente na extensão enviada pelo utilizador.

Evitar guardar uploads arbitrários em local onde possam ser executados pelo servidor.

Nunca utilizar diretamente o nome original do ficheiro como nome final sem sanitização.

Gerar nomes seguros ou UUIDs quando adequado.

---

## 18. Dados Sensíveis

Nunca expor:

- Passwords
- Tokens
- API keys
- Secrets
- Credenciais
- Variáveis de ambiente
- Dados internos desnecessários
- Stack traces em produção
- Informação sensível de outros utilizadores

Nunca colocar secrets diretamente no código.

Utilizar `.env` e configuração Laravel adequada.

O ficheiro `.env` nunca deve ser versionado.

Nunca escrever credenciais reais em:

- Testes
- Fixtures
- Documentação
- Comentários
- Logs

---

## 19. Logs

Os logs devem ajudar na investigação de problemas sem expor dados sensíveis.

Evitar colocar em logs:

- Passwords
- Tokens
- Cookies de sessão
- Authorization headers
- Dados pessoais desnecessários
- Dados bancários
- Secrets

Registar informação suficiente para diagnóstico sem comprometer segurança ou privacidade.

---

## 20. Rate Limiting

Endpoints suscetíveis de abuso devem utilizar rate limiting sempre que adequado.

Exemplos:

- Login
- Recuperação de palavra-passe
- Formulários públicos
- Pesquisa pesada
- APIs
- Envio de emails
- Uploads
- Webhooks

Utilizar os mecanismos de Rate Limiting fornecidos pelo Laravel.

---

## 21. Base de Dados

Alterações à base de dados devem utilizar migrations.

Nunca alterar manualmente a estrutura da base de dados como solução definitiva.

As migrations devem:

- Ser reversíveis sempre que possível.
- Preservar dados existentes.
- Utilizar tipos apropriados.
- Criar índices quando necessários.
- Respeitar foreign keys.
- Evitar operações destrutivas desnecessárias.

Antes de remover ou alterar colunas existentes, analisar o impacto no projeto.

---

## 22. Transações

Operações que alterem múltiplos registos relacionados e que devam ser atómicas devem utilizar transações.

```php
DB::transaction(function () {
    // Operações relacionadas.
});
```

Evitar estados inconsistentes provocados por operações parcialmente concluídas.

---

## 23. Performance e Queries

Evitar problemas de performance previsíveis.

Ter especial atenção a:

- N+1 queries
- Queries dentro de loops
- Carregamento desnecessário de relações
- Selects de colunas desnecessárias
- Paginação
- Índices da base de dados
- Cache quando adequado

Utilizar eager loading quando necessário.

```php
Product::with('categories')->paginate();
```

Não otimizar prematuramente, mas também não introduzir problemas óbvios de performance.

---

## 24. Paginação

Listagens administrativas potencialmente grandes devem utilizar paginação.

Evitar carregar milhares de registos de uma só vez sem necessidade.

---

## 25. JavaScript

Evitar JavaScript global desnecessário.

Funcionalidades reutilizáveis devem ser desenvolvidas em módulos próprios.

Evitar código JavaScript duplicado entre páginas.

Não colocar grandes implementações JavaScript diretamente dentro de templates Blade.

Quando uma funcionalidade frontend for reutilizável, criar um módulo ou componente dedicado.

Exemplo:

```text
resources/js/components/entity-selector.js
```

Utilização prevista:

```js
createEntitySelector({
    element: '#product-category',
    endpoint: '/admin/categories/search',
});

createEntitySelector({
    element: '#blog-category',
    endpoint: '/admin/blog-categories/search',
});

createEntitySelector({
    element: '#brand',
    endpoint: '/admin/brands/search',
});
```

Evitar:

```text
products/edit.blade.php
   └── 150 linhas de JS para pesquisar categorias
```

---

## 26. CSS

Evitar estilos duplicados e regras excessivamente específicas.

Utilizar os padrões definidos pelo projeto.

Componentes reutilizáveis devem ter estilos reutilizáveis.

Não introduzir estilos inline sem necessidade.

---

## 27. API e AJAX

Todas as rotas utilizadas por AJAX ou APIs devem aplicar as mesmas regras de segurança que qualquer outra rota.

Nunca assumir que uma rota é segura apenas porque não aparece diretamente na interface.

Validar:

- Autenticação
- Autorização
- Inputs
- Tipos
- Ownership
- Rate limits quando adequado

Retornar apenas os dados realmente necessários.

---

## 28. Minimização de Dados

Nunca devolver mais informação do que a funcionalidade necessita.

Por exemplo, se um autocomplete apenas necessita de:

```json
{
    "id": 1,
    "name": "Categoria"
}
```

Não retornar o objeto completo da categoria com campos internos desnecessários.

Aplicar o princípio de menor exposição possível.

---

## 29. Tratamento de Erros

Erros devem ser tratados de forma controlada.

O utilizador não deve receber:

- Stack traces
- Queries SQL
- Paths internos do servidor
- Configurações
- Secrets
- Informação técnica sensível

Mensagens apresentadas ao utilizador devem ser claras e em pt-PT.

Informação técnica necessária para diagnóstico deve ser enviada para logs de forma segura.

---

## 30. Testes Automatizados

Toda a funcionalidade nova deve ser coberta por testes automatizados.

Ao alterar comportamento existente, os testes existentes devem ser atualizados quando necessário.

Criar testes para:

- Funcionamento esperado
- Casos de erro
- Validação
- Autorização
- Utilizadores não autenticados
- Casos limite relevantes
- Regressões prováveis

Sempre que uma alteração corrigir um bug, criar, quando possível, um teste que reproduza esse bug antes da correção e confirme que não regressa futuramente.

---

## 31. Testes de Segurança

Para funcionalidades protegidas, testar também cenários como:

- Guest tenta aceder à área administrativa.
- Utilizador autenticado sem permissão tenta executar uma ação.
- Utilizador tenta alterar um recurso que não lhe pertence.
- Input inválido.
- IDs inexistentes.
- Payload manipulado.
- Campos adicionais não autorizados.
- Upload de ficheiro inválido.

Não testar apenas o "happy path".

---

## 32. Obrigatoriedade de Executar Testes

O agente nunca deve considerar uma tarefa concluída sem tentar executar os testes automatizados relevantes.

Depois de qualquer implementação:

1. Executar os testes específicos relacionados com a alteração.
2. Corrigir eventuais falhas.
3. Executar novamente os testes afetados.
4. Executar a suite completa de testes disponível.

Exemplo:

```bash
php artisan test --filter=NomeDoTeste
php artisan test
```

Se o projeto utilizar outros comandos ou suites, utilizar os comandos definidos pelo próprio projeto.

---

## 33. Falhas da Suite Completa

Se a suite completa não puder terminar por razões operacionais, explicar claramente a limitação.

Exemplos:

- Falta de memória
- Timeout
- Dependência externa indisponível
- Limitações do ambiente
- Problemas de infraestrutura

Nesses casos:

- Executar os testes relevantes por ficheiro, módulo ou suite.
- Validar o máximo possível.
- Nunca afirmar que "todos os testes passaram" se isso não tiver sido realmente confirmado.

---

## 34. Não Esconder Testes Falhados

Nunca:

- Remover um teste para fazer a suite passar.
- Desativar um teste válido.
- Alterar uma assertion apenas para aceitar comportamento incorreto.
- Marcar um teste como skipped sem justificação.
- Ignorar uma falha existente relacionada com a alteração.

Corrigir a causa do problema.

---

## 35. Preservar Compatibilidade

Antes de alterar:

- Rotas
- APIs
- Estrutura de dados
- Nomes de campos
- Componentes reutilizados
- Assinaturas de métodos
- Eventos
- Jobs

Analisar onde são utilizados.

Evitar quebrar funcionalidades existentes desnecessariamente.

---

## 36. Reutilização Antes de Duplicação

Antes de implementar algo semelhante a uma funcionalidade existente, procurar primeiro uma forma de reutilizar ou generalizar o componente existente.

Exemplo: se já existir um seletor AJAX de categorias utilizado nos produtos, não criar outro seletor independente para outra página.

Transformar ou reutilizar o componente existente de forma configurável.

---

## 37. Componentes Configuráveis

Sempre que fizer sentido, componentes reutilizáveis devem receber configuração em vez de dependerem de valores hardcoded.

Exemplos de configuração:

- Endpoint
- Tipo de entidade
- Placeholder
- Número máximo de resultados
- Callback
- Permissões
- Labels

Evitar componentes acoplados desnecessariamente a uma única página.

---

## 38. Dependências Externas

Não instalar uma nova package ou dependência sem primeiro avaliar se:

- Laravel ou PHP já fornecem a funcionalidade.
- O projeto já possui uma dependência equivalente.
- A dependência é realmente necessária.
- É mantida ativamente.
- Não introduz riscos de segurança conhecidos.
- Não aumenta significativamente a complexidade.

Evitar dependências para funcionalidades triviais.

---

## 39. Laravel

Utilizar funcionalidades nativas do Laravel sempre que estas resolverem corretamente o problema.

Preferir recursos como:

- Form Requests
- Policies
- Gates
- Middleware
- Events
- Listeners
- Jobs
- Notifications
- Mailables
- Service Container
- Dependency Injection
- Validation Rules
- API Resources
- Eloquent relationships
- Query scopes
- Cache
- Rate Limiting
- Queues

Evitar reinventar funcionalidades que o framework já fornece de forma segura e testada.

---

## 40. Configuração

Valores dependentes do ambiente não devem ficar hardcoded.

Utilizar:

```php
config()
```

O acesso a:

```php
env()
```

Deve ficar principalmente nos ficheiros de configuração.

Evitar utilizar `env()` diretamente espalhado pela aplicação.

---

## 41. Código Morto

Não deixar:

- Código comentado desnecessário
- Debug statements
- `dd()`
- `dump()`
- `var_dump()`
- `console.log()` temporários
- Imports não utilizados
- Métodos abandonados
- Código duplicado

A implementação final deve ficar limpa.

---

## 42. Alterações Focadas

Não alterar ficheiros ou funcionalidades não relacionados com a tarefa sem necessidade.

Evitar grandes refactors paralelos enquanto se implementa uma alteração pequena.

Se for identificado um problema relevante fora do âmbito da tarefa, reportá-lo separadamente.

---

## 43. Nomes e Legibilidade

Utilizar nomes claros e descritivos.

Evitar abreviaturas obscuras.

Preferir:

```php
$productCategories
```

Em vez de:

```php
$pc
```

O código deve ser compreensível sem depender excessivamente de comentários.

Comentários devem explicar principalmente o "porquê", e não simplesmente repetir o que o código já mostra.

---

## 44. Métodos Pequenos

Métodos devem ter uma responsabilidade clara.

Quando um método começar a executar demasiadas operações diferentes, considerar a divisão em métodos ou classes menores.

Evitar métodos gigantes difíceis de testar e reutilizar.

---

## 45. Segurança por Defeito

Sempre que houver dúvida entre tornar uma funcionalidade pública ou protegida, escolher a opção mais restritiva até existir uma necessidade explícita para exposição pública.

Aplicar o princípio de menor privilégio.

Cada utilizador deve ter apenas as permissões necessárias para desempenhar as suas funções.

---

## 46. CMS Multi-site e Reutilização

Como este CMS servirá de base a vários sites, evitar introduzir lógica específica de um cliente diretamente no núcleo do CMS.

Separar, sempre que possível:

- Core do CMS
- Funcionalidades opcionais
- Configuração
- Tema
- Conteúdo
- Integrações específicas
- Customizações de cliente

Uma funcionalidade específica de um site não deve comprometer a reutilização do CMS noutros projetos.

Preferir configuração e módulos a condicionais espalhadas pelo código como:

```php
if ($client === 'cliente_x') {
    // ...
}
```

Estrutura conceptual:

```text
CMS
│
├── Core
│   ├── Autenticação
│   ├── Utilizadores
│   ├── Media
│   ├── Páginas
│   ├── Menus
│   ├── SEO
│   └── Configurações
│
├── Módulos
│   ├── Produtos
│   ├── Blog
│   ├── Galerias
│   ├── Formulários
│   ├── Eventos
│   └── ...
│
├── Componentes
│   ├── Seletores
│   ├── Modais
│   ├── Uploads
│   ├── Tabelas
│   └── ...
│
└── Site
    ├── Tema
    ├── Configuração
    ├── Conteúdo
    └── Personalizações
```

---

## 47. Core vs Personalização

Sempre que uma funcionalidade possa ser útil a vários sites, desenvolvê-la no Core de forma genérica.

Quando uma funcionalidade for específica de determinado projeto, tentar isolá-la num módulo, configuração ou camada própria.

Evitar modificar diretamente funcionalidades Core para acomodar exceções específicas de clientes.

---

## 48. Preparação para Extensibilidade

Desenvolver funcionalidades pensando na possibilidade de serem utilizadas futuramente noutros contextos, mas sem criar abstrações especulativas.

O objetivo é encontrar equilíbrio entre:

- Reutilização
- Simplicidade
- Extensibilidade
- Manutenção

Aplicar KISS.

---

## 49. Cache

Quando utilizar cache:

- Definir estratégia de invalidação.
- Não deixar dados antigos indefinidamente.
- Evitar cache de dados sensíveis sem necessidade.
- Garantir que dados de diferentes utilizadores/sites não se misturam.
- Criar chaves de cache claras e suficientemente específicas.

---

## 50. Jobs e Filas

Operações lentas devem ser consideradas para processamento através de queues.

Exemplos:

- Envio massivo de emails
- Processamento de imagens
- Importações
- Exportações
- Integrações externas
- Tarefas pesadas

Jobs devem, quando possível, ser:

- Idempotentes
- Seguros em caso de retry
- Preparados para falhas
- Observáveis através de logs adequados

---

## 51. Operações Destrutivas

Operações destrutivas devem ser tratadas com especial cuidado.

Exemplos:

- Eliminar utilizadores
- Eliminar páginas
- Eliminar produtos
- Remover ficheiros
- Alterações massivas
- Reset de configurações

Quando adequado utilizar:

- Confirmação explícita
- Policies
- Soft Deletes
- Transactions
- Auditoria

---

## 52. Soft Deletes

Para entidades onde recuperação ou auditoria possam ser importantes, avaliar a utilização de Soft Deletes.

Não utilizar indiscriminadamente.

A decisão deve depender do domínio da entidade.

---

## 53. Auditoria

Para ações administrativas importantes, considerar registar:

- Utilizador responsável
- Tipo de ação
- Entidade afetada
- Data/hora
- Alterações relevantes

Especialmente em operações como:

- Alteração de permissões
- Eliminação
- Publicação
- Configurações
- Gestão de utilizadores

Não guardar dados sensíveis desnecessários no histórico.

---

## 54. Concorrência

Quando duas operações simultâneas possam provocar inconsistências, considerar mecanismos como:

- Transactions
- Unique constraints
- Locks
- Atomic updates

Nunca depender apenas de uma validação anterior para garantir unicidade ou consistência.

A base de dados deve também proteger invariantes importantes.

---

## 55. Integridade da Base de Dados

Sempre que possível utilizar também garantias ao nível da base de dados:

- Foreign keys
- Unique indexes
- Not null
- Índices
- Constraints apropriadas

A aplicação não deve ser a única camada responsável pela integridade dos dados.

---

## 56. Datas e Horas

Utilizar Carbon e mecanismos nativos do Laravel para tratamento de datas.

Evitar manipulação manual de datas através de strings quando existir alternativa segura.

Ter atenção a:

- Timezones
- Formatos apresentados ao utilizador
- Armazenamento consistente

---

## 57. APIs Externas

Integrações externas devem:

- Definir timeouts.
- Tratar falhas.
- Tratar respostas inesperadas.
- Evitar exposição de credenciais.
- Utilizar retry apenas quando apropriado.
- Validar respostas antes de as utilizar.

Uma indisponibilidade de terceiros não deve provocar comportamentos inseguros ou corromper dados.

---

## 58. Documentação

Funcionalidades complexas ou decisões arquiteturais relevantes devem ser documentadas quando necessário.

A documentação deve permanecer simples e útil.

Não criar documentação extensa para código trivial.

---

## 59. Checklist Antes de Concluir

Antes de terminar qualquer tarefa, verificar:

- A implementação segue os padrões existentes?
- Existe código duplicado que possa ser reutilizado?
- A solução respeita SOLID, DRY e KISS?
- Existe validação backend?
- Existe autorização backend quando necessária?
- As rotas estão corretamente protegidas?
- Existe risco de exposição de dados?
- Existe risco de XSS?
- Existe risco de SQL Injection?
- Existe risco de Mass Assignment?
- Uploads foram validados, se aplicável?
- A funcionalidade está devidamente modularizada?
- Foram criados ou atualizados testes?
- Foram testados cenários de erro e autorização?
- Os testes específicos foram executados?
- A suite completa foi executada?
- Não ficaram `dd()`, `dump()` ou debug statements?
- Os textos estão em pt-PT?
- A codificação UTF-8 está correta?
- Foi evitada alteração desnecessária de código não relacionado?

---

## 60. Relatório Final Obrigatório

No final de cada tarefa de desenvolvimento, apresentar um resumo objetivo contendo:

### Ficheiros Alterados

Indicar todos os ficheiros criados, modificados ou eliminados.

### Implementação

Explicar resumidamente o que foi desenvolvido.

### Segurança

Indicar as validações e proteções de segurança relevantes aplicadas.

### Testes Criados ou Atualizados

Indicar os testes adicionados ou modificados.

### Testes Executados

Indicar exatamente os comandos executados.

Exemplo:

```bash
php artisan test tests/Feature/Admin/ProductTest.php
php artisan test
```

### Resultados

Indicar quantos testes passaram ou falharam, quando essa informação estiver disponível.

### Limitações ou Riscos

Indicar qualquer ponto que não tenha sido possível validar completamente.

Nunca declarar a tarefa como concluída se os testes relevantes não tiverem sido executados.

---

## 61. Regra Principal

Uma funcionalidade não está concluída apenas porque "funciona".

Está concluída quando:

- Está corretamente implementada.
- Está segura.
- Está validada no backend.
- Está autorizada corretamente.
- É suficientemente reutilizável.
- Respeita a arquitetura do projeto.
- Tem testes automatizados.
- Os testes relevantes foram executados.
- Não introduz regressões conhecidas.
- Mantém a codificação e os textos pt-PT corretos.

## 62. Arquitetura do CMS Distribuível

Este projeto deve ser desenvolvido com o objetivo de permitir que o núcleo do CMS seja atualizado independentemente dos sites criados a partir dele.

O CMS não deve ser tratado como uma aplicação que é copiada e posteriormente modificada de forma independente.

A arquitetura deve distinguir claramente:

```text
CMS Core
├── funcionalidades comuns
├── lógica administrativa
├── autenticação
├── autorização
├── gestão de páginas
├── gestão de menus
├── media
├── SEO
├── configurações
└── componentes reutilizáveis

Módulos
├── Blog
├── Produtos
├── Eventos
├── Reservas
├── Imóveis
└── outras funcionalidades opcionais

Site
├── tema
├── frontend
├── configuração
├── conteúdo
└── personalizações específicas do cliente
```

O objetivo é permitir que vários sites utilizem a mesma versão do CMS Core e possam receber futuras atualizações sem substituir ou destruir as personalizações próprias de cada site.

---

## 63. CMS Core como Package Laravel

O núcleo do CMS deve ser preparado para funcionar como uma package Laravel privada e versionada.

Sempre que uma funcionalidade fizer parte do comportamento comum do CMS, deve ser implementada no Core e não diretamente na aplicação específica de um cliente.

Exemplos de funcionalidades pertencentes ao Core:

* Autenticação administrativa
* Gestão de utilizadores
* Roles e permissões
* Gestão de páginas
* Gestão de menus
* Media
* SEO
* Configurações
* Redirects
* Logs
* Auditoria
* Componentes administrativos reutilizáveis
* Gestão genérica de formulários

O Core deve poder ser instalado pelos projetos através do Composer.

Estrutura conceptual:

```text
pcteckserv/cms-core
```

Cada site deverá depender de uma versão do Core através do respetivo `composer.json`.

Exemplo conceptual:

```json
{
    "require": {
        "pcteckserv/cms-core": "^1.0"
    }
}
```

Nunca modificar diretamente ficheiros instalados em:

```text
vendor/
```

Qualquer necessidade de personalização deve ser resolvida através de mecanismos próprios de extensibilidade.

---

## 64. Separação entre Core e Site

Antes de implementar qualquer funcionalidade, determinar a que camada pertence.

### Core

Colocar no Core quando:

* A funcionalidade pode ser utilizada por vários sites.
* Faz parte da administração genérica do CMS.
* Resolve uma necessidade comum.
* Deve receber atualizações juntamente com o CMS.
* Uma correção de segurança deve poder ser distribuída para todos os sites.

### Site

Colocar no projeto específico do site quando:

* A funcionalidade é exclusiva de um cliente.
* Está diretamente relacionada com o design desse site.
* Representa regras de negócio exclusivas.
* Não faz sentido ser distribuída para outros projetos.

### Módulo

Criar ou considerar um módulo separado quando:

* A funcionalidade pode ser reutilizada em vários sites, mas não deve fazer parte obrigatória do Core.
* A funcionalidade pertence a um domínio específico.

Exemplos:

```text
cms-core
cms-blog
cms-products
cms-events
cms-bookings
cms-properties
```

---

## 65. Proibição de Lógica Específica de Clientes no Core

Nunca adicionar ao CMS Core condicionais específicas de um cliente.

Evitar:

```php
if ($client === 'cliente_x') {
    // comportamento específico
}
```

Evitar também:

```php
if (config('app.name') === 'Empresa XPTO') {
    // comportamento específico
}
```

O Core não deve conhecer clientes concretos.

Personalizações devem ser realizadas através de:

* Configuração
* Service Providers
* Events e Listeners
* Contracts
* Interfaces
* Dependency Injection
* Blade overrides
* Temas
* Hooks
* Módulos
* Classes específicas da aplicação

---

## 66. Extensibilidade do Core

Quando o Core necessitar de permitir comportamento específico dos sites, criar pontos de extensão claros sem duplicar ou modificar o código do Core.

Preferir, conforme adequado:

* Interfaces
* Contracts
* Laravel Service Container
* Events
* Listeners
* Configuração
* Blade view overrides
* Service Providers
* Callbacks bem definidos
* Classes substituíveis através de dependency injection

Não criar sistemas de plugins complexos sem necessidade.

Aplicar KISS.

---

## 67. Views do CMS

As views administrativas genéricas pertencentes ao CMS devem poder ser fornecidas pelo Core.

Quando for necessário permitir personalização, utilizar mecanismos Laravel adequados para publicação ou substituição de views.

Nunca obrigar um site a modificar diretamente ficheiros internos do Core.

As views específicas do frontend do cliente devem permanecer na aplicação ou tema desse site.

Separação conceptual:

```text
CMS Core
└── Views administrativas genéricas

Site
└── Views públicas e tema específico
```

---

## 68. Assets Frontend

Bootstrap, GSAP, JavaScript, SCSS e restantes assets específicos do design público devem, por regra, pertencer ao site ou ao tema.

Assets necessários exclusivamente à área administrativa comum podem pertencer ao CMS Core.

Evitar acoplar o design público dos clientes ao Core.

---

## 69. Base de Dados e Migrations do Core

Alterações à estrutura de dados necessárias ao CMS Core devem ser distribuídas através de migrations pertencentes ao Core.

Uma atualização do Core poderá introduzir novas migrations.

As migrations devem:

* Ser seguras para instalações existentes.
* Preservar dados sempre que possível.
* Evitar operações destrutivas automáticas.
* Ser compatíveis com atualizações incrementais.
* Poder ser executadas em sites que já se encontram em produção.

Nunca assumir que uma migration será executada apenas numa instalação nova.

---

## 70. Compatibilidade de Atualizações

Ao alterar funcionalidades do Core, assumir sempre que existem instalações anteriores do CMS em produção.

Antes de alterar:

* Estruturas de tabelas
* Rotas
* Configurações
* Assinaturas de métodos
* Contracts
* Eventos
* Views reutilizáveis
* APIs internas
* Nomes de configuração

Avaliar compatibilidade com versões anteriores.

Evitar breaking changes sem necessidade.

Quando uma alteração incompatível for inevitável, esta deve ser claramente identificada como alteração de versão major.

---

## 71. Versionamento do CMS Core

O CMS Core deve utilizar versionamento semântico.

Formato:

```text
MAJOR.MINOR.PATCH
```

Exemplos:

```text
1.0.0
1.0.1
1.1.0
2.0.0
```

Utilizar:

* PATCH para correções compatíveis.
* MINOR para novas funcionalidades compatíveis.
* MAJOR para alterações incompatíveis.

Não introduzir breaking changes numa atualização PATCH ou MINOR.

---

## 72. Starter Project

Pode existir um projeto base destinado à criação de novos sites.

Este projeto funciona apenas como ponto de partida e não deve ser confundido com o CMS Core.

Estrutura conceptual:

```text
cms-starter
    ↓
novo projeto cliente
    ↓
depende de cms-core
```

Depois de criado um novo site, este pode evoluir independentemente do Starter.

As futuras atualizações comuns devem chegar ao site através do CMS Core e dos respetivos módulos, e não através da sincronização com o Starter.

---

## 73. Regra de Decisão Arquitetural

Antes de criar ou alterar uma funcionalidade, responder mentalmente às seguintes perguntas:

1. Esta funcionalidade é comum a vários sites?
2. Deve receber atualizações juntamente com o CMS?
3. É específica apenas deste cliente?
4. Poderá ser útil em alguns sites, mas não em todos?
5. Estou prestes a duplicar código que deveria pertencer ao Core?
6. Estou a modificar o Core apenas para satisfazer uma exceção específica?

Aplicar:

```text
Comum a praticamente todos os sites
→ CMS Core

Reutilizável mas opcional
→ Módulo

Específico de um cliente
→ Site

Design e apresentação pública
→ Tema/Site
```

---

## 74. Proteção Contra Divergência

O agente deve evitar qualquer implementação que provoque a existência de várias cópias independentes do mesmo código do CMS.

Não copiar código do Core para o projeto do cliente apenas para o modificar.

Não duplicar controllers, services, models ou componentes do Core quando existe uma forma apropriada de extensão.

Quando uma necessidade do site exigir alteração de comportamento, avaliar primeiro:

1. Configuração.
2. Extensão.
3. Event/Listener.
4. Contract/Interface.
5. Implementação específica no site.
6. Módulo separado.

Alterar diretamente o Core apenas quando a alteração representar uma melhoria genérica do próprio CMS.

---

## 75. Regra Principal de Distribuição

O código comum deve ter uma única fonte de verdade.

Uma correção efetuada no CMS Core deve poder ser disponibilizada a todos os sites através de uma nova versão do Core.

Nunca criar uma arquitetura onde a mesma correção tenha de ser implementada manualmente em cada site.

O objetivo estrutural do projeto é:

```text
              CMS Core
                 │
       ┌─────────┼─────────┐
       │         │         │
       ▼         ▼         ▼
     Site A    Site B    Site C
       │         │         │
     Tema A    Tema B    Tema C
```

Cada site é independente ao nível da sua implementação e conteúdo, mas continua a utilizar uma versão controlada do mesmo CMS Core.