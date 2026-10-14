<?php

declare(strict_types=1);

namespace App\Core;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Geração dos relatórios de confirmações (lista de convidados) em Excel
 * (.xlsx via PhpSpreadsheet) e PDF (via mPDF).
 *
 * As linhas já chegam filtradas e ordenadas alfabeticamente pelo controller.
 * Cada relatório inclui: nome, status, nº de acompanhantes e um total de
 * pessoas confirmadas ao final.
 */
final class RsvpExport
{
    /** Rótulos legíveis dos status. */
    private const STATUS_LABELS = [
        'confirmed' => 'Confirmado',
        'declined'  => 'Não vai',
        'pending'   => 'Sem resposta',
    ];

    /** Rótulos dos filtros, usados no título do relatório. */
    private const FILTER_LABELS = [
        ''          => 'Todos os convidados',
        'confirmed' => 'Convidados confirmados',
        'declined'  => 'Convidados que não vão',
        'pending'   => 'Convidados sem resposta',
    ];

    /**
     * Monta as linhas normalizadas para o relatório a partir das linhas do
     * banco (listWithGuests). Calcula também o total de pessoas confirmadas.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{
     *   items:array<int, array{name:string,status:string,companions:int}>,
     *   confirmed_people:int
     * }
     */
    private static function prepare(array $rows): array
    {
        $items = [];
        $confirmedPeople = 0;

        foreach ($rows as $r) {
            $status     = (string) ($r['status'] ?? '');
            $companions = (int) ($r['companions'] ?? 0);
            $items[] = [
                'name'       => (string) ($r['name'] ?? ''),
                'status'     => self::STATUS_LABELS[$status] ?? $status,
                'companions' => $companions,
            ];
            if ($status === 'confirmed') {
                $confirmedPeople += $companions + 1; // o convidado + acompanhantes
            }
        }

        return ['items' => $items, 'confirmed_people' => $confirmedPeople];
    }

    /** Título do relatório conforme o filtro aplicado. */
    private static function title(string $filter): string
    {
        return self::FILTER_LABELS[$filter] ?? 'Convidados';
    }

    /**
     * Gera o arquivo Excel (.xlsx) e o envia ao navegador (download).
     * Encerra a execução após o envio.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public static function downloadXlsx(array $rows, string $filter, string $coupleNames = ''): void
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new RuntimeException('Biblioteca de Excel ausente. Rode "composer install" no servidor.');
        }

        $data  = self::prepare($rows);
        $title = self::title($filter);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Convidados');

        // Cabeçalho/título do relatório.
        $sheet->setCellValue('A1', $coupleNames !== '' ? ('Casamento — ' . $coupleNames) : 'Lista de convidados');
        $sheet->setCellValue('A2', $title);
        $sheet->setCellValue('A3', 'Gerado em ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');
        $sheet->mergeCells('A3:C3');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3')->getFont()->setSize(9);

        // Cabeçalho das colunas (linha 5).
        $headerRow = 5;
        $sheet->setCellValue('A' . $headerRow, 'Nome do convidado');
        $sheet->setCellValue('B' . $headerRow, 'Status');
        $sheet->setCellValue('C' . $headerRow, 'Acompanhantes');

        $headerStyle = $sheet->getStyle('A' . $headerRow . ':C' . $headerRow);
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6E7256');
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Linhas de dados.
        $row = $headerRow + 1;
        foreach ($data['items'] as $item) {
            $sheet->setCellValue('A' . $row, $item['name']);
            $sheet->setCellValue('B' . $row, $item['status']);
            $sheet->setCellValue('C' . $row, $item['companions']);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // Linha de total (pessoas confirmadas).
        $totalRow = $row + 1;
        $sheet->setCellValue('A' . $totalRow, 'Total de pessoas confirmadas');
        $sheet->setCellValue('C' . $totalRow, $data['confirmed_people']);
        $sheet->mergeCells('A' . $totalRow . ':B' . $totalRow);
        $sheet->getStyle('A' . $totalRow . ':C' . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('C' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Bordas da tabela e larguras.
        $lastDataRow = max($headerRow, $row - 1);
        $sheet->getStyle('A' . $headerRow . ':C' . $lastDataRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(16);

        // Habilita o filtro automático nas colunas (facilita a consulta).
        $sheet->setAutoFilter('A' . $headerRow . ':C' . $lastDataRow);

        self::sendXlsxHeaders(self::filename($filter) . '.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Gera o PDF e o envia ao navegador (download/inline). Encerra a execução.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public static function downloadPdf(array $rows, string $filter, string $coupleNames = ''): void
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('Biblioteca de PDF ausente. Rode "composer install" no servidor.');
        }

        $data  = self::prepare($rows);
        $title = self::title($filter);
        $html  = self::buildPdfHtml($data['items'], $data['confirmed_people'], $title, $coupleNames);

        // Diretório temporário próprio (evita problemas de permissão no padrão).
        $tmp = BASE_PATH . '/storage/mpdf';
        if (!is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode'        => 'utf-8',
            'format'      => 'A4',
            'margin_top'  => 18,
            'margin_bottom' => 16,
            'tempDir'     => is_dir($tmp) ? $tmp : null,
        ]);
        $mpdf->SetTitle($title);
        $mpdf->WriteHTML($html);
        // 'D' força download; 'I' abriria inline. Usamos download.
        $mpdf->Output(self::filename($filter) . '.pdf', 'D');
        exit;
    }

    /** Monta o HTML limpo e organizado do PDF. */
    private static function buildPdfHtml(array $items, int $confirmedPeople, string $title, string $coupleNames): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $rowsHtml = '';
        if ($items === []) {
            $rowsHtml = '<tr><td colspan="3" style="text-align:center;color:#777;padding:14px;">'
                . 'Nenhum convidado neste filtro.</td></tr>';
        } else {
            foreach ($items as $item) {
                $rowsHtml .= '<tr>'
                    . '<td>' . $esc($item['name']) . '</td>'
                    . '<td>' . $esc($item['status']) . '</td>'
                    . '<td style="text-align:center;">' . (int) $item['companions'] . '</td>'
                    . '</tr>';
            }
        }

        $heading = $coupleNames !== '' ? ('Casamento — ' . $esc($coupleNames)) : 'Lista de convidados';
        $generated = 'Gerado em ' . date('d/m/Y H:i');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'body { font-family: sans-serif; color: #2c2a26; }'
            . 'h1 { font-size: 16pt; margin: 0 0 2px; }'
            . 'h2 { font-size: 12pt; margin: 0 0 2px; color: #6e7256; font-weight: normal; }'
            . '.meta { font-size: 8pt; color: #888; margin-bottom: 12px; }'
            . 'table { width: 100%; border-collapse: collapse; font-size: 10pt; }'
            . 'th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }'
            . 'th { background: #6e7256; color: #fff; }'
            . 'tr:nth-child(even) td { background: #f6f4ef; }'
            . '.total { margin-top: 14px; font-size: 11pt; font-weight: bold; }'
            . '</style></head><body>'
            . '<h1>' . $heading . '</h1>'
            . '<h2>' . $esc($title) . '</h2>'
            . '<div class="meta">' . $generated . '</div>'
            . '<table><thead><tr>'
            . '<th>Nome do convidado</th><th>Status</th><th style="text-align:center;">Acompanhantes</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<p class="total">Total de pessoas confirmadas: ' . $confirmedPeople . '</p>'
            . '</body></html>';
    }

    /** Nome-base do arquivo conforme o filtro. */
    private static function filename(string $filter): string
    {
        $suffix = [
            ''          => 'todos',
            'confirmed' => 'confirmados',
            'declined'  => 'nao-vao',
            'pending'   => 'sem-resposta',
        ][$filter] ?? 'lista';
        return 'convidados-' . $suffix . '-' . date('Ymd-His');
    }

    /** Cabeçalhos HTTP para o download do .xlsx. */
    private static function sendXlsxHeaders(string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
    }
}
