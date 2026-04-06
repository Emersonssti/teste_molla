# Teste BI Molla — Case Técnico Analista de BI

Aplicação web PHP que processa uma planilha Excel de performance de distribuidores, gera um **dashboard interativo** com KPIs e gráficos, e exporta uma **planilha de apuração mensal** com cálculo de premiação conforme as regras do Programa de Incentivo.

---

## Sumário

- [Pré-requisitos](#pré-requisitos)
- [Como rodar o projeto](#como-rodar-o-projeto)
- [Estrutura de pastas](#estrutura-de-pastas)
- [Regras de negócio](#regras-de-negócio)
- [Detalhes técnicos dos cálculos](#detalhes-técnicos-dos-cálculos)
- [Funcionalidades](#funcionalidades)
- [Tecnologias](#tecnologias)

---

## Pré-requisitos

| Requisito | Versão mínima |
|-----------|---------------|
| PHP | 8.1+ |
| Composer | 2.x |
| Extensões PHP | `zip`, `gd`, `xml`, `mbstring`, `curl` |

> **Nota:** Não é necessário banco de dados. A fonte de dados é um arquivo Excel (`.xlsx`).

---

## Como rodar o projeto

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/teste-bi-molla.git
cd teste-bi-molla

# 2. Instale as dependências PHP
composer install

# 3. Coloque a planilha "Base de Dados.xlsx" na raiz do projeto
#    (mesmo nível do composer.json)

# 4. Inicie o servidor embutido do PHP
php -S localhost:8000 -t public public/router.php

# 5. Acesse no navegador
#    http://localhost:8000
```

### Alternativa com Apache

Aponte o `DocumentRoot` para a pasta `public/`. O `.htaccess` já está configurado para rewrite.

---

## Estrutura de pastas

```
├── app/
│   ├── bootstrap.php                    # Autoload, session, helpers
│   ├── Core/
│   │   ├── Controller.php               # Base controller (render views)
│   │   ├── Router.php                   # Roteamento GET/POST
│   │   └── View.php                     # Engine de views com layout
│   ├── Http/Controllers/
│   │   ├── HomeController.php           # Upload da planilha
│   │   ├── DashboardController.php      # Dashboard com KPIs e gráficos
│   │   ├── TratarController.php         # Exportação da planilha tratada
│   │   └── AnalysisController.php       # Integração com IA (Groq API)
│   ├── Models/
│   │   ├── PerformanceRow.php           # DTO de uma linha da aba Performance
│   │   └── SpreadsheetDataset.php       # Coleção de PerformanceRow
│   ├── Services/
│   │   ├── Dashboard/
│   │   │   ├── DashboardDataProcessor.php   # Agregação de dados e elegibilidade
│   │   │   ├── DashboardDataService.php     # Orquestrador do payload
│   │   │   └── DashboardPayload.php         # DTO para serialização JSON
│   │   ├── Settlement/
│   │   │   ├── MonthlySettlementCalculator.php  # Cálculo de apuração mensal
│   │   │   ├── DistributorApuracao.php          # DTO de resultado por distribuidor
│   │   │   ├── SettlementReport.php             # DTO do relatório completo
│   │   │   └── MonthCodes.php                   # Mapeamento mês ↔ abreviação
│   │   └── Spreadsheet/
│   │       ├── PerformanceSheetReader.php        # Leitura do Excel (14 colunas)
│   │       ├── SpreadsheetTreatmentService.php   # Geração do Excel tratado
│   │       ├── SpreadsheetPathResolver.php       # Resolução do caminho do arquivo
│   │       └── SpreadsheetReaderInterface.php    # Contrato de leitura
│   └── Views/
│       ├── layouts/default.php          # Layout HTML base
│       ├── home/index.php               # Tela de upload
│       ├── dashboard/index.php          # Dashboard completo
│       └── tratar/form.php              # Tela de apuração Excel
├── config/
│   ├── app.php                          # Configurações gerais
│   └── settlement.php                   # Tabela de premiação e acelerador
├── public/
│   ├── index.php                        # Front controller (rotas)
│   ├── router.php                       # Router para PHP built-in server
│   ├── .htaccess                        # Rewrite para Apache
│   └── assets/
│       ├── css/style.css                # Estilos da tela inicial
│       └── js/
│           ├── home.js                  # JS da tela de upload
│           └── dashboard.js             # JS do dashboard (charts, grid, filtros)
├── storage/uploads/                     # Uploads temporários (runtime)
├── material_apoio/                      # PDFs do regulamento (referência)
├── composer.json
└── README.md
```

---

## Regras de negócio

### Programa de Incentivo

- **Período:** 01/09/2024 a 31/08/2025
- **Mecânica:** "Bateu, Levou" — apuração mensal
- **Público:** Distribuidores segmentados em 5 grupos

### Critérios de elegibilidade mensal

Um distribuidor é **elegível** ao prêmio mensal quando atende **simultaneamente** os 3 critérios:

| # | Critério | KPI utilizado | Regra |
|---|----------|---------------|-------|
| 1 | Volume total | `TOTAL_VOLUME` | Somatório de meta vs. realizado de **todas** as categorias ≥ 100% |
| 2 | Positivação foco (total) | `CATEGORY_POSITIVATION_FOCUS` | Somatório da positivação das categorias foco ≥ 100% da meta |
| 3 | Positivação foco (por categoria) | `CATEGORY_POSITIVATION_FOCUS` | Ao menos **2 categorias foco** com positivação individual ≥ 100% |

### Tabela de premiação ("Bateu, Levou")

| Grupo | Prêmio mensal |
|-------|---------------|
| 1 | R$ 6.000,00 |
| 2 | R$ 5.000,00 |
| 3 | R$ 3.000,00 |
| 4 | R$ 2.000,00 |
| 5 | R$ 1.000,00 |

### Acelerador mensal

- Uma categoria é designada como **aceleradora** a cada mês (configurável em `config/settlement.php`)
- Se o distribuidor **elegível** atingir ≥ 100% do volume na categoria aceleradora, recebe **+20%** sobre o prêmio base
- Exemplo: Grupo 1 elegível + acelerador = R$ 6.000 + R$ 1.200 = **R$ 7.200**

---

## Detalhes técnicos dos cálculos

### Fluxo de dados

```
Excel (.xlsx)
  ↓  PerformanceSheetReader (14 colunas fixas)
  ↓
PerformanceRow[] (DTOs imutáveis)
  ↓
  ├── DashboardDataProcessor → KPIs, gráficos, grid de auditoria
  ├── MonthlySettlementCalculator → Apuração mensal com premiação
  └── AnalysisController → Sumarização + IA (Groq API)
```

### Leitura da planilha (`PerformanceSheetReader`)

Lê a aba `Performance` do Excel. Espera exatamente 14 colunas:

```
distributors.id | distributors.cnpj | distributors.name | Grupo |
categories.name | kpiType | isFocusCategory | referencePeriod |
period | mês | ano | meta | realizado | cobertura
```

Cada linha vira um `PerformanceRow` (DTO readonly com tipagem forte).

### Cálculo de elegibilidade (`MonthlySettlementCalculator`)

Para cada distribuidor no mês/ano selecionado:

1. **Filtro:** Seleciona apenas linhas com `referencePeriod = MONTHLY` e mês/ano correspondente
2. **Volume total:**
   - Filtra linhas com `kpiType = TOTAL_VOLUME`
   - Soma `meta` e `realizado` de todas as categorias
   - `volumeOk = (realizado ≥ meta)` (com tolerância `1e-6`)
3. **Positivação foco (total):**
   - Filtra linhas com `kpiType = CATEGORY_POSITIVATION_FOCUS` **e** `isFocusCategory = true`
   - Soma `meta` e `realizado` de todas as categorias foco
   - `posTotalOk = (realizado ≥ meta)`
4. **Positivação foco (por categoria):**
   - Agrupa as mesmas linhas por `categoryName`
   - Para cada categoria: verifica se `realizado ≥ meta`
   - Conta quantas categorias atingiram 100%
   - `cats100 ≥ 2` é exigido
5. **Elegibilidade:** `volumeOk AND posTotalOk AND cats100 ≥ 2`
6. **Premiação:**
   - Se elegível: prêmio base conforme tabela do grupo
   - Acelerador: se elegível **e** volume da categoria aceleradora do mês ≥ 100%, adiciona +20%

### Cálculo do dashboard (`DashboardDataProcessor`)

- Mesma lógica de elegibilidade replicada no processador do dashboard
- Calcula KPIs agregados: total distribuidores, elegíveis, payout total, atingimento médio, taxa de conversão
- Gera dados para 5 gráficos: scatter (distribuidores), barras agrupadas (categorias), ranking top 10, radar (mix foco), linha (histórico)
- Grid de auditoria com busca, ordenação e export CSV
- Todos os cálculos são recalculados client-side quando os filtros (período, categoria, grupo, status) são alterados

### Geração do Excel tratado (`SpreadsheetTreatmentService`)

Gera um `.xlsx` com 3 abas:

| Aba | Conteúdo |
|-----|----------|
| **Apuração Mensal** | Resumo executivo (5 cards) + tabela com 15 colunas: #, Distribuidor, CNPJ, Grupo, Vol. Meta/Realizado/%, Pos. Meta/Realizada/%, Cat. Foco ≥100%, Status, Prêmio Base, Acelerador, Total. Formatação condicional (verde = elegível, vermelho = não), linha de totais, filtro automático. |
| **Movimento Detalhado** | Todas as linhas de dados mensais do distribuidor: categoria, KPI, foco, meta, realizado, atingimento. Coloração por atingimento (verde ≥100%, amarelo ≥95%, vermelho <95%). |
| **Regras do Programa** | Referência rápida: critérios de elegibilidade, tabela de premiação, mecânica do acelerador, e legenda de cores/símbolos. |

---

## Funcionalidades

### Tela inicial
- Upload de planilha Excel (`.xlsx` / `.xls`, máx. 10 MB)
- Validação de estrutura (14 colunas obrigatórias)

### Dashboard interativo
- **5 KPIs:** Distribuidores, Elegíveis, Payout Total, Atingimento Médio, Taxa de Conversão
- **5 Gráficos:** Scatter de performance, Barras meta × realizado, Ranking top 10, Radar de mix foco, Histórico mensal
- **Grid de auditoria:** Tabela com busca, ordenação e export CSV
- **Filtros:** Período, Categoria, Grupo, Status (recalcula tudo em tempo real)
- **Modal de regras:** Referência rápida do regulamento
- **Análise com IA:** Integração com Groq API (LLaMA 3.3 70B) para conclusões estratégicas dos KPIs

### Apuração Excel
- Seleção de mês/ano
- Download da planilha tratada com 3 abas formatadas profissionalmente
- Pronta para envio ao cliente

---

## Tecnologias

| Camada | Tecnologia |
|--------|------------|
| Backend | PHP 8.1+ (MVC custom, sem framework) |
| Leitura/escrita Excel | PhpSpreadsheet 4.x |
| Frontend | HTML5, CSS3, JavaScript vanilla |
| UI Framework | Bootstrap 5.3 + Bootstrap Icons |
| Gráficos | Chart.js |
| Fonte | Google Fonts (Inter) |
| IA | Groq API (LLaMA 3.3 70B Versatile) |
| Servidor | PHP built-in server / Apache |

---

## Configuração

### `config/settlement.php`

Valores de premiação e categoria aceleradora por período:

```php
return [
    'premio_bateu_levou' => [
        1 => 6000.0,  // Grupo 1
        2 => 5000.0,  // Grupo 2
        3 => 3000.0,  // Grupo 3
        4 => 2000.0,  // Grupo 4
        5 => 1000.0,  // Grupo 5
    ],
    'accelerator_category_by_period' => [
        // '2025-05' => 'CREME DE LEITE',
    ],
];
```

Para ativar o acelerador em um mês, descomente/adicione a linha com a chave `YYYY-MM` e o nome exato da categoria conforme aparece na planilha.

---

## Licença

Projeto desenvolvido como case técnico para a Agência Molla.
