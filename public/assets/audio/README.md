# Música do site

Coloque **nesta pasta** (`public/assets/audio/`) o arquivo da música ambiente
com exatamente o nome abaixo. Enquanto o arquivo não existir, o player
simplesmente **não aparece** no site — então pode adicionar quando quiser.

## Arquivo esperado

| Arquivo                  | Onde aparece                         |
|--------------------------|--------------------------------------|
| `die-with-a-smile.mp3`   | Controle flutuante em todas as páginas |

Música: **"Die With a Smile" — Lady Gaga & Bruno Mars**.

## Como funciona

- A música tenta tocar automaticamente ao abrir o site. Navegadores modernos
  costumam **bloquear o autoplay com som**; nesse caso, a faixa começa no
  primeiro clique/toque do visitante, ou ao clicar no botão discreto no canto
  inferior direito.
- O botão permite **tocar/pausar** a qualquer momento. Se o visitante pausar,
  o site respeita a escolha e não volta a tocar sozinho durante a sessão.
- A posição e o estado (tocando/pausado) são guardados na sessão do navegador,
  então **a música continua de onde parou** ao navegar entre as páginas — ela
  não reinicia a cada troca de página.
- A faixa toca em **loop**.

## Dicas

- **Formato:** use `.mp3` (ampla compatibilidade entre navegadores) com o
  nome-base exato `die-with-a-smile`.
- **Peso:** otimize o arquivo (ex.: bitrate ~128 kbps) para carregar rápido.
- **Direitos autorais:** garanta que você tem permissão/licença para usar a
  faixa no site. O arquivo de áudio **não é incluído** no projeto.
