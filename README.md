# HidroDados — Sistema de Monitoramento de Água

**Sistema web para monitoramento de produção, faturamento, consumo de energia e perdas de água**, desenvolvido em PHP e SQLite.

O HidroDados apresenta indicadores operacionais em um dashboard, permite cadastrar e consultar medições e gerar relatórios em CSV.

🇧🇷 Português | [English](#english-version)

---

## Funcionalidades

- Dashboard com indicadores de produção, faturamento, perdas estimadas e energia consumida.
- Cadastro de medições de água por data e setor.
- Exclusão de registros.
- Filtros por período e setor.
- Gráfico de produção versus volume faturado.
- Gráfico de perdas estimadas por setor.
- Cálculo automático do volume e percentual de perdas.
- Cálculo da intensidade energética por metro cúbico produzido.
- Exportação de relatórios em CSV.
- Persistência dos dados utilizando SQLite.

## Tecnologias utilizadas

- PHP 8.3
- SQLite
- PDO
- HTML5
- CSS3
- JavaScript
- Chart.js

## Indicadores calculados

| Indicador | Cálculo |
|---|---|
| Perdas estimadas | Volume produzido − volume faturado |
| Taxa de perdas | Perdas estimadas ÷ volume produzido × 100 |
| Intensidade energética | Energia consumida ÷ volume produzido |

## Estrutura do projeto

```text
monitoramento-agua-php/
├── data/
│   └── .gitkeep
├── public/
│   ├── assets/
│   │   └── style.css
│   └── index.php
├── src/
│   └── Database.php
├── .gitignore
└── README.md
```

O banco SQLite é utilizado para armazenar as medições e é inicializado pela aplicação quando necessário.

## Requisitos

- PHP 8.3 ou superior.
- Extensão PDO.
- Driver PDO SQLite.
- Extensão SQLite3.
- Navegador atualizado.
- Conexão com a internet para carregar o Chart.js pela CDN.

## Como executar

Clone o repositório:

```bash
git clone https://github.com/vitor1914/monitoramento-agua-php.git
```

Entre na pasta:

```bash
cd monitoramento-agua-php
```

Verifique a versão do PHP:

```bash
php -v
```

Verifique as extensões necessárias:

```bash
php -m | grep -Ei 'PDO|pdo_sqlite|sqlite3'
```

Inicie o servidor de desenvolvimento:

```bash
php -S 127.0.0.1:8000 -t public
```

Acesse no navegador:

```text
http://127.0.0.1:8000
```

## Validações e cuidados de segurança

O projeto utiliza:

- PDO com consultas preparadas.
- Validação dos dados recebidos nos formulários.
- Token CSRF nas operações via formulário.
- Escape de conteúdo HTML com `htmlspecialchars`.
- Validação de valores numéricos e datas.
- Restrições para evitar registros com valores negativos ou volume faturado superior ao produzido.
- Integridade referencial e índices no SQLite.

## Verificações realizadas

- Verificação de sintaxe PHP com `php -l`.
- Verificação de integridade do banco SQLite.
- Conferência dos índices da tabela de medições.
- Teste de inicialização do servidor PHP.
- Requisição HTTP local com resposta `200`.
- Confirmação da preservação dos 10 registros de demonstração existentes durante as verificações.

## Objetivo do projeto

Este projeto demonstra a aplicação de conhecimentos de desenvolvimento web, modelagem e manipulação de banco de dados, validação de dados e visualização de indicadores.

Foi desenvolvido como projeto de portfólio, utilizando dados de demonstração.

---

# English Version

## HidroDados — Water Monitoring System

A web application for monitoring water production, billed volume, estimated losses, and energy consumption.

The system provides an operational dashboard, measurement management, data filtering, charts, and CSV reports.

### Features

- Dashboard with operational KPIs.
- Water measurement registration and deletion.
- Filtering by date and sector.
- Production versus billed volume chart.
- Estimated losses by sector chart.
- Automatic water loss calculations.
- Energy intensity calculation.
- CSV report export.
- SQLite database persistence.

### Technologies

- PHP 8.3
- SQLite
- PDO
- HTML5
- CSS3
- JavaScript
- Chart.js

### Run locally

```bash
git clone https://github.com/vitor1914/monitoramento-agua-php.git

cd monitoramento-agua-php

php -S 127.0.0.1:8000 -t public
```

Open:

```text
http://127.0.0.1:8000
```

### Project Purpose

This project demonstrates practical skills in PHP development, database management, data validation, operational metrics, and data visualization.

It was developed as a portfolio project using demonstration data.

---

**GitHub:** [vitor1914](https://github.com/vitor1914)
