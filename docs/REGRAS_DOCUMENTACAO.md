# 📌 REGRAS OBRIGATÓRIAS DE DOCUMENTAÇÃO

> **Este arquivo é fixo. Leia-o ANTES de iniciar qualquer tarefa e RELEIA-O ao terminar.**
> Vale para desenvolvedores e agentes de IA.

## 1. Princípio

A documentação faz parte do código. **Nenhuma tarefa está concluída** se os arquivos `.md`
afetados não tiverem sido atualizados na mesma entrega.

## 2. Mapa: o que mudou → o que atualizar

| Tipo de alteração | Arquivos que DEVEM ser atualizados |
|---|---|
| Nova tabela, coluna, índice, relacionamento ou migration | Diagrama/estrutura do banco (`docs/banco-de-dados.md`), Guia do desenvolvedor, Log de desenvolvimento |
| Nova rotina, Service, Job, Command ou regra de negócio | Guia do desenvolvedor, Documento inicial do projeto, Log de desenvolvimento |
| Nova rota, tela ou componente Livewire | Guia do desenvolvedor, Documento inicial do projeto, Log de desenvolvimento |
| Nova dependência (pacote composer/npm) ou mudança de configuração/.env | Guia do desenvolvedor (instalação), Log de desenvolvimento |
| Mudança de decisão técnica (banco, ORM, tema, padrão) | Documento inicial do projeto, Log de desenvolvimento |
| Correção de bug relevante | Log de desenvolvimento |
| Remoção ou renomeação de qualquer item acima | Todos os arquivos que o mencionam (buscar pelo nome antigo) |

> Ajuste os nomes dos arquivos na tabela conforme os nomes reais existentes em `docs/`.

## 3. Checklist ao finalizar CADA tarefa

- [ ] As migrations foram aplicadas e o diagrama do banco reflete o estado real?
- [ ] Novas rotinas/Services estão descritas no Guia do desenvolvedor?
- [ ] O documento inicial do projeto continua verdadeiro?
- [ ] Uma entrada foi adicionada ao Log de desenvolvimento (formato abaixo)?
- [ ] Buscou por nomes antigos que ficaram desatualizados nos `.md`?
- [ ] Os exemplos de código e comandos nos `.md` ainda funcionam?

## 4. Formato da entrada no Log de desenvolvimento

- **Data:** AAAA-MM-DD
- **Autor:** (nome ou agente de IA)
- **O que foi feito:** resumo curto
- **Arquivos de código alterados:** lista
- **Documentos atualizados:** lista
- **Impacto no banco:** (nenhum / descrever migrations)
- **Pendências / próximos passos:** lista

## 5. Regras de conduta

1. Nunca apague histórico do Log; apenas acrescente novas entradas ao final.
2. Se houver conflito entre documento e código, **o código real é a verdade**: corrija o documento.
3. Se não tiver certeza do que mudou, pergunte ao responsável antes de documentar.
4. Não inclua senhas, tokens ou chaves nos documentos.
5. Ao receber uma tarefa, informe quais arquivos `.md` serão atualizados.
6. Ao concluir, liste quais `.md` foram de fato atualizados.

## 6. Instrução para agentes de IA

Sempre que alterar a estrutura do projeto (banco, rotinas, rotas, dependências, decisões),
atualize os arquivos `.md` correspondentes **na mesma resposta/entrega**, conforme a
tabela da seção 2. Não espere o usuário pedir. Finalize informando o que foi atualizado.
