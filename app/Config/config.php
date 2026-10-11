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
        'base_url' => '', // Deixe vazio quando servir a partir da raiz (ex.: php -S)
        'locale'   => 'pt_BR',
        'timezone' => 'America/Sao_Paulo',
        'debug'    => true,
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
