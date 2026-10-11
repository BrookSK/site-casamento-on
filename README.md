# Site de Casamento

Site de casamento moderno, elegante e responsivo, construído em **PHP puro com
arquitetura MVC**. Esta é a **Etapa 01**: estrutura do projeto + página inicial
visual, **sem banco de dados** e sem funcionalidades que dependam de persistência.

---

## Requisitos

- **PHP 8.0 ou superior** (usa `str_starts_with`, `match`, tipos e `declare(strict_types)`).
  Verifique com: `php -v`
- Nenhuma dependência externa, nenhum gerenciador de pacotes, nenhum build.

> As fontes (Google Fonts) são carregadas via `<link>` no HTML — não é uma
> dependência de instalação.

---

## Como executar localmente

### Opção A — Servidor embutido do PHP (recomendado para testar)

Na raiz do projeto, aponte o document root para a pasta `public/`:

```bash
php -S localhost:8000 -t public
```

Depois abra: **http://localhost:8000**

### Opção B — Apache (XAMPP, Laragon, WAMP…)

1. Coloque o projeto dentro do diretório servido (ex.: `htdocs/`).
2. Configure o **DocumentRoot** (ou um VirtualHost) apontando para a pasta
   `public/` do projeto.
3. Garanta que o módulo **`mod_rewrite`** esteja ativo (o `.htaccess` já cuida
   do roteamento).
4. Acesse pelo navegador o host configurado.

> O diretório `public/` é o **único** exposto na web. Todo o código da
> aplicação (`app/`) fica fora da raiz pública por segurança.

---

## Fotos do casal

Coloque as imagens em **`public/assets/img/`** usando os nomes indicados em
[`public/assets/img/README.md`](public/assets/img/README.md). Enquanto um
arquivo não existir, o site exibe um **placeholder elegante** no lugar.

Nomes principais: `hero.jpg`, `couple-1.jpg`, `gallery-1.jpg` … `gallery-6.jpg`.

---

## Estrutura do projeto

```
site-casamento-on/
├── public/                  # Raiz pública (document root)
│   ├── index.php            # Front controller (ponto de entrada único)
│   ├── .htaccess            # Reescrita de URL (Apache)
│   └── assets/
│       ├── css/style.css    # Estilos (paleta, layout, responsivo)
│       ├── js/main.js       # Interações (menu, scroll reveal, countdown)
│       └── img/             # Fotos do casal (ver README da pasta)
├── app/
│   ├── Core/                # App, Router, Controller base, View helpers
│   ├── Config/config.php    # Configuração + dados PROVISÓRIOS do evento
│   ├── Controllers/         # HomeController
│   ├── Models/              # Vazio nesta etapa (sem banco de dados)
│   ├── Routes/web.php       # Definição das rotas
│   └── Views/
│       ├── layouts/         # header.php, footer.php
│       ├── partials/        # hero, couple, event, gallery, gifts
│       ├── errors/          # 404
│       └── home.php         # Monta a página inicial
├── .gitignore
└── README.md
```

### Como o MVC funciona aqui

1. Toda requisição entra por `public/index.php` (front controller).
2. `app/Core/App.php` carrega a config, registra o autoload e as rotas.
3. `app/Core/Router.php` mapeia a URL para um `Controller@metodo`.
4. O **Controller** escolhe a **View** e passa os dados.
5. `Controller::view()` renderiza `header` + conteúdo + `footer`.

---

## O que foi implementado nesta etapa

- Estrutura MVC em PHP puro (Core, Config, Controllers, Models, Routes, Views).
- Front controller, roteamento e autoload PSR-4 simples.
- **Página inicial** com as seções: Hero, Apresentação do casal, Informações do
  evento, Galeria, Lista de presentes e Rodapé.
- Identidade visual própria (areia + verde-sálvia + acento rosé), tipografia
  serifada elegante, layout responsivo e animações sutis.
- Acessibilidade: navegação por teclado, `skip link`, textos alternativos,
  foco visível e respeito a `prefers-reduced-motion`.
- Página de erro **404**.

## O que é apenas visual (não funcional)

- **Lista de presentes:** cards com botões "Em breve" desabilitados. Sem
  carrinho, sem pagamento.
- **Confirmação de presença (RSVP):** botão "Em breve" desabilitado. Sem envio
  nem armazenamento.
- **Contagem regressiva:** usa a data provisória da config; some se a data não
  for válida/futura.
- **Textos e dados do evento:** placeholders claramente identificados em
  `app/Config/config.php`.

## O que ficará para as próximas etapas

- Banco de dados e conexão (MySQL/MariaDB).
- Cadastro, login e recuperação de senha de convidados.
- Lista de presentes funcional e pagamentos online.
- Confirmação de presença (RSVP) com persistência.
- Painel administrativo.
- Envio de e-mails.

---

## Personalização rápida

Edite **`app/Config/config.php`** para alterar nomes dos noivos, data, horário,
locais e os itens (provisórios) da lista de presentes. As cores ficam nas
variáveis CSS no topo de **`public/assets/css/style.css`** (`:root`).
