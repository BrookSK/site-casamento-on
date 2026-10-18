<?php

declare(strict_types=1);

/**
 * Configurações da aplicação.
 *
 * ATENÇÃO: Nesta etapa NÃO há conexão com banco de dados, autenticação real,
 * pagamentos ou persistência. Os dados abaixo são PROVISÓRIOS (placeholders)
 * e devem ser substituídos pelas informações reais do casal quando disponíveis.
 */

return [

    // ---- Aplicação ----
    'app' => [
        'name'     => 'Nossa História',
        // URL base pública do site (sem barra final). Usada para montar links
        // absolutos — ex.: a URL do webhook do ASAAS.
        // Deixe vazio APENAS em desenvolvimento local servindo a partir da raiz.
        'base_url' => 'https://zen-raman.186-209-113-140.plesk.page',
        'locale'   => 'pt_BR',
        'timezone' => 'America/Sao_Paulo',
        'debug'    => true,

        // Exibe (ou não) o link "Confirmar presença" no menu e no rodapé do
        // site público. A PÁGINA /confirmacao-interna continua sempre acessível
        // pela URL direta (ex.: QR Code do cartão), independentemente deste
        // valor — ele controla apenas a visibilidade do link na navegação.
        //   false  -> a confirmação NÃO aparece na navegação pública (padrão).
        //   true   -> mostra o link na navegação.
        'show_rsvp_link' => false,
    ],

    /**
     * Dados PROVISÓRIOS do casamento.
     * Nenhuma informação real foi inventada; preencha quando os dados existirem.
     */
    'wedding' => [
        'bride' => 'Noiva',           // PLACEHOLDER
        'groom' => 'Noivo',           // PLACEHOLDER
        'hashtag' => '#NossoCasamento', // PLACEHOLDER

        // Data/hora do evento (PLACEHOLDER). Formato ISO 8601.
        // Usada apenas visualmente (ex.: contagem regressiva no front-end).
        'date_iso'    => '2027-01-01T16:00:00',
        'date_label'  => 'Data a definir',   // PLACEHOLDER
        'time_label'  => 'Horário a definir', // PLACEHOLDER

        'ceremony' => [
            'title'   => 'Cerimônia',
            'place'   => 'Local a definir',          // PLACEHOLDER
            'address' => 'Endereço a definir',       // PLACEHOLDER
            'details' => 'Detalhes serão divulgados em breve.', // PLACEHOLDER
        ],

        'reception' => [
            'title'   => 'Recepção',
            'place'   => 'Local a definir',          // PLACEHOLDER
            'address' => 'Endereço a definir',       // PLACEHOLDER
            'details' => 'Detalhes serão divulgados em breve.', // PLACEHOLDER
        ],
    ],

    /**
     * Lista de presentes — APENAS VISUAL nesta etapa.
     * Não há compra, carrinho, pagamento ou persistência.
     */
    'gifts' => [
        ['name' => 'Presente 1', 'price' => 'R$ ---', 'icon' => 'gift'],   // PLACEHOLDER
        ['name' => 'Presente 2', 'price' => 'R$ ---', 'icon' => 'home'],   // PLACEHOLDER
        ['name' => 'Presente 3', 'price' => 'R$ ---', 'icon' => 'cup'],    // PLACEHOLDER
        ['name' => 'Presente 4', 'price' => 'R$ ---', 'icon' => 'plane'],  // PLACEHOLDER
    ],
];
