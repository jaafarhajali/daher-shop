<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Report;

final class ReportController extends Controller
{
    public function index(): void
    {
        $type = $this->queryString('type', 'sales-daily');
        if (!array_key_exists($type, Report::TYPES)) {
            $type = 'sales-daily';
        }

        $params = $this->params();
        $report = (new Report())->build($type, $params);

        $inline = null;
        if ($report['chart'] !== null && $report['rows'] !== []) {
            $inline = 'window.REPORT_CHART = ' . json_encode([
                'labels'   => array_column($report['rows'], $report['chart']['x']),
                'values'   => array_map('floatval', array_column($report['rows'], $report['chart']['y'])),
                'label'    => $report['chart']['label'],
                'currency' => setting('currency_symbol', '$'),
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';';
        }

        $this->render('reports/index', [
            'type'       => $type,
            'params'     => $params,
            'report'     => $report,
            'types'      => Report::TYPES,
            'categories' => (new Category())->all(),
            'customers'  => (new Customer())->all(),
            'pageScript' => 'reports',
            'inlineScript' => $inline,
        ], 'Reports');
    }

    /** GET reports/export — ?format=csv|xls|print plus the same filters. */
    public function export(): void
    {
        $type = $this->queryString('type', 'sales-daily');
        if (!array_key_exists($type, Report::TYPES)) {
            redirect('reports/index');
        }

        $params = $this->params();
        $report = (new Report())->build($type, $params);
        $filename = $type . '_' . $params['from'] . '_' . $params['to'];

        $format = $this->queryString('format', 'csv');
        if ($format === 'xls') {
            $this->exportExcel($report, $filename);
        }
        if ($format === 'print') {
            $this->renderBare('reports/print', [
                'report' => $report,
                'params' => $params,
            ], $report['title']);
            exit;
        }
        $this->exportCsv($report, $filename);
    }

    // ------------------------------------------------------------- private --

    /** @return array{from:string, to:string, category_id:int, customer_id:int, method:string} */
    private function params(): array
    {
        $from = $this->queryString('from', date('Y-m-01'));
        $to = $this->queryString('to', date('Y-m-d'));

        // Guard against invalid or reversed ranges.
        $validDate = static fn (string $d): bool =>
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        if (!$validDate($from)) {
            $from = date('Y-m-01');
        }
        if (!$validDate($to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from'        => $from,
            'to'          => $to,
            'category_id' => $this->queryInt('category_id'),
            'customer_id' => $this->queryInt('customer_id'),
            'method'      => $this->queryString('method'),
        ];
    }

    private function exportCsv(array $report, string $filename): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens accents correctly.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array_map(
            static fn (array $c): string => $c['label'],
            array_values($report['columns'])
        ));

        foreach ($report['rows'] as $row) {
            $line = [];
            foreach (array_keys($report['columns']) as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($out, $line);
        }

        if ($report['totals'] !== []) {
            $line = [];
            $first = true;
            foreach (array_keys($report['columns']) as $key) {
                if ($first) {
                    $line[] = 'TOTAL';
                    $first = false;
                    continue;
                }
                $line[] = $report['totals'][$key] ?? '';
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }

    /** Excel-compatible .xls (HTML table format — opens natively in Excel). */
    private function exportExcel(array $report, string $filename): never
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
        echo '<h3>' . e($report['title']) . '</h3><table border="1"><tr>';
        foreach ($report['columns'] as $col) {
            echo '<th>' . e($col['label']) . '</th>';
        }
        echo '</tr>';

        foreach ($report['rows'] as $row) {
            echo '<tr>';
            foreach (array_keys($report['columns']) as $key) {
                echo '<td>' . e((string) ($row[$key] ?? '')) . '</td>';
            }
            echo '</tr>';
        }

        if ($report['totals'] !== []) {
            echo '<tr><td><b>TOTAL</b></td>';
            $keys = array_keys($report['columns']);
            array_shift($keys);
            foreach ($keys as $key) {
                echo '<td><b>' . e((string) ($report['totals'][$key] ?? '')) . '</b></td>';
            }
            echo '</tr>';
        }

        echo '</table></body></html>';
        exit;
    }
}
