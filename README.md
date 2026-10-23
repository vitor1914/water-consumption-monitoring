# HidroDados — Monitoramento de Consumo e Perdas de Água

[Português](#português) | [English](#english)

## Português

### Visão geral

HidroDados é uma aplicação web demonstrativa em PHP para registrar e analisar indicadores operacionais de abastecimento de água. O painel reúne volumes produzidos e faturados, perdas estimadas, consumo energético, gráficos por data e setor, filtros e exportação CSV.

> Os dados iniciais são sintéticos e servem apenas para demonstração. As perdas são calculadas como volume produzido menos volume faturado; essa diferença não substitui uma metodologia oficial de balanço hídrico.

### Funcionalidades

- Cadastro e exclusão de medições.
- Persistência em SQLite usando PDO e consultas preparadas.
- Validação de datas, volumes e campos obrigatórios no servidor.
- Indicadores de volume produzido, volume faturado, perdas estimadas e intensidade energética.
- Gráficos de produção/faturamento ao longo do tempo e perdas estimadas por setor.
- Filtros por período e setor.
- Exportação dos registros filtrados para CSV.
- Token CSRF para formulários e escape de saída HTML.
- Interface responsiva para desktop e dispositivos móveis.

### Tecnologias

- PHP 8.1 ou superior
- PDO SQLite (`pdo_sqlite`)
- SQLite
- HTML5 e CSS3
- JavaScript e Chart.js (carregado via CDN)

### Requisitos no Ubuntu

```bash
sudo apt update
sudo apt install php-cli php-sqlite3
```

Verifique as extensões:

```bash
php -v
php -m | grep -E 'PDO|pdo_sqlite|sqlite3'
```

### Executar localmente

Na pasta raiz do projeto:

```bash
php -S 127.0.0.1:8000 -t public
```

Abra `http://127.0.0.1:8000` no navegador. O banco SQLite será criado automaticamente em `data/monitoramento.sqlite`, com dados demonstrativos inseridos na primeira execução.

### Estrutura

```text
monitoramento-agua-php/
├── data/                 # Banco SQLite criado durante a execução
├── public/
│   ├── assets/style.css
│   └── index.php
├── src/Database.php
├── .gitignore
└── README.md
```

### Indicadores utilizados

- **Perdas estimadas (m³):** volume produzido − volume faturado.
- **Taxa de perdas (%):** perdas estimadas ÷ volume produzido × 100.
- **Intensidade energética (kWh/m³):** energia consumida ÷ volume produzido.

Os indicadores são agregados sobre os registros filtrados. Em aplicações reais, a interpretação exige contexto operacional, validação dos medidores e metodologia aprovada pela organização.

### Exportação

Clique em **Exportar CSV** para baixar os registros que correspondem aos filtros atuais. O arquivo pode ser aberto em planilhas para análises complementares.

### Observações de segurança e produção

Este projeto foi criado para demonstração e portfólio. O servidor embutido do PHP é destinado a desenvolvimento local, não a exposição pública. Antes de produção, adicione autenticação e autorização, logging, política de backup, proteção de infraestrutura, testes automatizados, controles de acesso e revisão de segurança. O gráfico usa Chart.js por CDN e requer conexão com a internet para carregar a biblioteca.

---

## English

### Overview

HidroDados is a demonstration PHP web application for recording and analyzing operational water-supply indicators. The dashboard combines produced and billed volumes, estimated losses, energy consumption, charts by date and sector, filters, and CSV export.

> Initial records are synthetic and intended for demonstration only. Estimated losses are calculated as produced volume minus billed volume; this difference is not a substitute for an official water-balance methodology.

### Features

- Create and delete measurement records.
- SQLite persistence through PDO and prepared statements.
- Server-side validation for dates, volumes, and required fields.
- KPIs for produced volume, billed volume, estimated losses, and energy intensity.
- Charts for production versus billing over time and estimated losses by sector.
- Date-range and sector filters.
- CSV export for filtered records.
- CSRF tokens for forms and HTML output escaping.
- Responsive desktop and mobile layout.

### Technology stack

- PHP 8.1 or later
- PDO SQLite (`pdo_sqlite`)
- SQLite
- HTML5 and CSS3
- JavaScript and Chart.js (loaded from a CDN)

### Ubuntu requirements

```bash
sudo apt update
sudo apt install php-cli php-sqlite3
```

Check the runtime and extensions:

```bash
php -v
php -m | grep -E 'PDO|pdo_sqlite|sqlite3'
```

### Run locally

From the project root:

```bash
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000` in your browser. The SQLite database is automatically created at `data/monitoramento.sqlite`, and sample records are inserted on the first run.

### Key metrics

- **Estimated losses (m³):** produced volume − billed volume.
- **Loss rate (%):** estimated losses ÷ produced volume × 100.
- **Energy intensity (kWh/m³):** energy consumption ÷ produced volume.

Metrics are aggregated over the filtered records. In real-world deployments, interpretation requires operational context, meter validation, and an organization-approved methodology.

### Export

Select **Exportar CSV** to download records matching the current filters. The file can be opened in spreadsheet software for further analysis.

### Security and production notes

This project is intended for demonstration and portfolio use. PHP's built-in server is for local development, not public deployment. Before production, add authentication and authorization, logging, backup policies, infrastructure protections, automated tests, access controls, and a security review. The charts load Chart.js from a CDN and therefore require an internet connection.
