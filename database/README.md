# Banco de dados

Esta pasta contém o esquema e os scripts do banco. SGBD: **MySQL / MariaDB**.

> O site continua funcionando **sem** banco nesta etapa. A camada de dados está
> pronta, mas as funcionalidades (RSVP, login, presentes, admin) só serão
> ligadas nas próximas etapas.

## Arquivos

| Arquivo | O que é |
|---|---|
| `schema.sql` | Cria as tabelas (estrutura). Execute primeiro. |
| `seed.sql` | Dados de exemplo (opcional), para testar. Execute depois do schema. |
| `check_connection.php` | Testa a conexão e lista as tabelas (rodar via linha de comando). |

## Passo a passo (produção — Plesk)

### 1. Criar o banco e o usuário
No Plesk: **Bancos de dados → Adicionar banco de dados**.
- Dê um nome ao banco (ex.: `casamento`).
- Crie um usuário e uma senha forte.
- Anote: nome do banco, usuário, senha e host (geralmente `localhost`).

### 2. Configurar as credenciais no projeto
1. Copie `app/Config/database.example.php` para `app/Config/database.php`.
2. Preencha com as credenciais do passo 1.
3. **Não** versione o `database.php` — ele já está no `.gitignore`.

> No Plesk, você pode editar o `database.php` direto pela aba **Arquivos**.

### 3. Importar o esquema
No Plesk: **Bancos de dados → (seu banco) → phpMyAdmin → aba Importar**,
selecione o arquivo `database/schema.sql` e execute.

(Opcional) Repita com `database/seed.sql` para inserir dados de exemplo.

### 4. Verificar
Pela linha de comando (SSH no servidor, se disponível):

```bash
php database/check_connection.php
```

Deve mostrar "[OK] Conexão estabelecida" e listar as tabelas.

## Passo a passo (ambiente local)

Se você tem MySQL/MariaDB local (ex.: WAMP):

```bash
# 1. Crie o banco (ajuste o caminho do mysql conforme sua instalação)
mysql -u root -p -e "CREATE DATABASE casamento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Importe o esquema
mysql -u root -p casamento < database/schema.sql

# 3. (Opcional) dados de exemplo
mysql -u root -p casamento < database/seed.sql

# 4. Configure app/Config/database.php e teste
php database/check_connection.php
```

## Tabelas

| Tabela | Finalidade |
|---|---|
| `settings` | Dados editáveis do casamento (nomes, data, locais) em chave/valor. |
| `guests` | Convidados (base para cadastro, login e confirmação). |
| `rsvps` | Confirmações de presença (vínculo com `guests`). |
| `gifts` | Itens da lista de presentes (preço em centavos). |
| `gift_purchases` | Registro de quem escolheu/pagou cada presente (pagamentos futuros). |
| `admins` | Usuários do painel administrativo (futuro). |

Todas em InnoDB + utf8mb4, com chaves estrangeiras, índices e timestamps.
