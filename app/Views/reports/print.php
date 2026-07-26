<?php
/**
 * Print-optimized report (opens in a new tab; use the browser's
 * "Save as PDF" for a PDF copy). Expects: $report, $params
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($report['title']) ?> · <?= e(setting('shop_name', APP_NAME)) ?></title>
<style>
  body { font: 13px/1.5 "Segoe UI", system-ui, sans-serif; color: #111; margin: 2rem; }
  h1 { font-size: 1.25rem; margin: 0 0 0.15rem; }
  .meta { color: #555; font-size: 0.8rem; margin-bottom: 1.25rem; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
  th { background: #f1f1f1; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; }
  td.num, th.num { text-align: right; font-family: Consolas, monospace; }
  tfoot td { font-weight: bold; background: #fafafa; }
  .no-print { margin-bottom: 1rem; }
  @media print { .no-print { display: none; } body { margin: 0.5rem; } }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<h1><?= e(setting('shop_name', APP_NAME)) ?> — <?= e($report['title']) ?></h1>
<div class="meta">
  Period: <?= e(fmt_date($params['from'])) ?> → <?= e(fmt_date($params['to'])) ?> ·
  Generated <?= e(date('d/m/Y H:i')) ?>
</div>

<table>
  <thead>
    <tr>
      <?php foreach ($report['columns'] as $col): ?>
      <th class="<?= !empty($col['num']) || !empty($col['money']) ? 'num' : '' ?>"><?= e($col['label']) ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php if ($report['rows'] === []): ?>
      <tr><td colspan="<?= count($report['columns']) ?>">No data for this selection.</td></tr>
    <?php endif; ?>
    <?php foreach ($report['rows'] as $row): ?>
    <tr>
      <?php foreach ($report['columns'] as $key => $col): ?>
      <td class="<?= !empty($col['num']) || !empty($col['money']) ? 'num' : '' ?>">
        <?= !empty($col['money']) ? e(money($row[$key] ?? 0)) : e((string) ($row[$key] ?? '')) ?>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <?php if ($report['totals'] !== [] && $report['rows'] !== []): ?>
  <tfoot>
    <tr>
      <?php $first = true; foreach ($report['columns'] as $key => $col): ?>
      <td class="<?= !empty($col['num']) || !empty($col['money']) ? 'num' : '' ?>">
        <?php if ($first): $first = false; ?>
          TOTAL
        <?php elseif (isset($report['totals'][$key])): ?>
          <?= !empty($col['money']) ? e(money($report['totals'][$key])) : e((string) $report['totals'][$key]) ?>
        <?php endif; ?>
      </td>
      <?php endforeach; ?>
    </tr>
  </tfoot>
  <?php endif; ?>
</table>
</body>
</html>
