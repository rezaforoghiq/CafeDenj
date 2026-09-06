<?php
/**
 * templates/receipt-customer.php
 * -----------------------------------------------------------------------
 * قالب چاپ فاکتور مشتری (رسید حرارتی ۸۰ میلی‌متری)
 * استفاده از فونت وزیرمتن محلی و استایل بهینه‌سازی‌شده برای پرینترهای حرارتی.
 * -----------------------------------------------------------------------
 */

/** @var array $payload */
/** @var bool $autoprint */
/** @var string $cafeTitle */
/** @var string $cafePhone */
/** @var string $cafeAddress */

$autoprint = !empty($autoprint);
$cafeTitle = $cafeTitle ?? 'کافه دنج';
$cafePhone = $cafePhone ?? '09053680080';
$cafeAddress = $cafeAddress ?? 'کرج، بلوار شهید مطهری، نبش خیابان پیروزی، کافه دنج';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>فاکتور سفارش <?= htmlspecialchars((string)($payload['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    @font-face {
      font-family: 'Vazirmatn';
      src: url('assets/fonts/vazirmatn/Vazirmatn-Regular.woff2') format('woff2');
      font-weight: 400;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'Vazirmatn';
      src: url('assets/fonts/vazirmatn/Vazirmatn-Medium.woff2') format('woff2');
      font-weight: 500;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'Vazirmatn';
      src: url('assets/fonts/vazirmatn/Vazirmatn-SemiBold.woff2') format('woff2');
      font-weight: 600;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'Vazirmatn';
      src: url('assets/fonts/vazirmatn/Vazirmatn-Bold.woff2') format('woff2');
      font-weight: 700;
      font-style: normal;
      font-display: swap;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, Tahoma, sans-serif;
      font-size: 11.5px;
      line-height: 1.45;
      color: #000;
      background: #111a24;
      direction: rtl;
      text-align: right;
      padding: 20px 10px;
    }

    .screen-toolbar {
      max-width: 76mm;
      margin: 0 auto 15px auto;
      display: flex;
      gap: 8px;
      justify-content: center;
    }

    .btn-print {
      background: #b58d3d;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 8px 16px;
      font-family: inherit;
      font-size: 12.5px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-close {
      background: #233142;
      color: #cbd5e1;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 6px;
      padding: 8px 14px;
      font-family: inherit;
      font-size: 12.5px;
      cursor: pointer;
      text-decoration: none;
    }

    .receipt-wrapper {
      width: 76mm;
      max-width: 76mm;
      margin: 0 auto;
      background: #fff;
      color: #000;
      padding: 4mm 4.5mm;
      box-shadow: 0 4px 20px rgba(0,0,0,0.35);
      border-radius: 4px;
    }

    .receipt-header {
      text-align: center;
      padding-bottom: 6px;
      border-bottom: 1px dashed #000;
      margin-bottom: 6px;
    }

    .receipt-title {
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 2px;
      letter-spacing: -0.2px;
    }

    .receipt-subtitle {
      font-size: 11px;
      font-weight: 600;
      margin-bottom: 3px;
    }

    .receipt-meta {
      font-size: 10px;
      color: #000;
      display: flex;
      flex-direction: column;
      gap: 2.5px;
      margin-bottom: 6px;
      border-bottom: 1px dashed #000;
      padding-bottom: 5px;
    }

    .meta-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      line-height: 1.35;
    }

    .meta-label {
      font-weight: 600;
      font-size: 9.5px;
      color: #000;
    }

    .meta-value {
      font-weight: 700;
      font-size: 10px;
      color: #000;
      direction: ltr;
      text-align: left;
    }

    .meta-customer {
      font-weight: 700;
      font-size: 10px;
      color: #000;
      text-align: left;
    }

    .meta-order-num {
      font-size: 11.5px;
      font-weight: 800;
      letter-spacing: 0.3px;
    }

    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 5px;
      font-size: 10.5px;
    }

    .items-table th {
      border-bottom: 1px solid #000;
      padding: 3px 1px;
      font-weight: 700;
      font-size: 9.5px;
      color: #000;
      text-align: right;
    }

    .items-table th:last-child {
      text-align: left;
    }

    .items-table td {
      padding: 4px 1px 3px 1px;
      border-bottom: 1px dashed #000;
      vertical-align: top;
      color: #000;
    }

    .items-table tr {
      page-break-inside: avoid;
    }

    .item-name {
      font-weight: 700;
      color: #000;
      font-size: 11px;
      line-height: 1.3;
    }

    .item-disc-tag {
      display: block;
      font-size: 8.5px;
      font-weight: 600;
      color: #000;
      margin-top: 1px;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .totals-box {
      border-top: 1px dashed #000;
      padding-top: 4px;
      margin-top: 4px;
      display: flex;
      flex-direction: column;
      gap: 2.5px;
      font-size: 10px;
      color: #000;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .total-row.final-amount {
      font-size: 12px;
      font-weight: 700;
      border-top: 1px solid #000;
      border-bottom: 1px solid #000;
      padding: 3px 0;
      margin: 2px 0;
    }

    .receipt-notes {
      margin-top: 5px;
      padding: 3px 4px;
      background: #f4f4f4;
      border: 1px dashed #999;
      border-radius: 2px;
      font-size: 9.5px;
    }

    .receipt-footer {
      text-align: center;
      margin-top: 8px;
      padding-top: 5px;
      border-top: 1px dashed #000;
      font-size: 9px;
      font-weight: 600;
      color: #000;
      display: flex;
      flex-direction: column;
      gap: 2.5px;
      line-height: 1.4;
    }

    @media print {
      @page {
        size: 80mm auto;
        margin: 0;
      }

      * {
        color: #000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      html, body {
        width: 100% !important;
        max-width: 80mm !important;
        margin: 0 auto !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
      }

      .no-print {
        display: none !important;
      }

      .receipt-wrapper {
        width: 72mm !important;
        max-width: 72mm !important;
        margin: 0 auto !important;
        padding: 2mm 3.5mm !important;
        box-sizing: border-box !important;
        box-shadow: none !important;
        border-radius: 0 !important;
      }

      .items-table tr {
        page-break-inside: avoid;
      }
    }
  </style>
</head>
<body>

  <div class="screen-toolbar no-print">
    <button type="button" class="btn-print" onclick="window.print()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
      چاپ فاکتور
    </button>
    <button type="button" class="btn-close" onclick="if(window.history.length > 1) { window.history.back(); } else { window.close(); }">
      بازگشت / بستن
    </button>
  </div>

  <div class="receipt-wrapper" id="receiptContent">
    <div class="receipt-header">
      <div class="receipt-title"><?= htmlspecialchars($cafeTitle, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="receipt-subtitle">فاکتور فروش</div>
    </div>

    <div class="receipt-meta">
      <div class="meta-row">
        <span class="meta-label">شماره سفارش:</span>
        <span class="meta-value meta-order-num"><?= htmlspecialchars((string)($payload['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="meta-row">
        <span class="meta-label">تاریخ و زمان:</span>
        <span class="meta-value"><?= htmlspecialchars((string)($payload['date_jalali'] ?? ''), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)($payload['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="meta-row">
        <span class="meta-label">مشتری:</span>
        <span class="meta-customer"><?= htmlspecialchars((string)($payload['customer_name'] ?? 'مشتری محترم'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php if (!empty($payload['customer_phone']) && $payload['customer_phone'] !== '-'): ?>
      <div class="meta-row">
        <span class="meta-label">شماره تماس:</span>
        <span class="meta-value" style="letter-spacing:0.5px;"><?= htmlspecialchars((string)$payload['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>
    </div>

    <table class="items-table">
      <thead>
        <tr>
          <th style="width:48%;">شرح کالا</th>
          <th style="width:14%; text-align:center;">تعداد</th>
          <th style="width:18%; text-align:center;">قیمت واحد</th>
          <th style="width:20%;">جمع سطر</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($payload['items'] ?? []) as $item): ?>
          <?php 
            $hasItemDisc = !empty($item['discount_percent']) && (int)$item['discount_percent'] > 0;
            $origPrice = (float)($item['original_price'] ?? $item['price']);
            $finalPrice = (float)$item['price'];
            $lineTotal = (float)($item['line_total'] ?? ($finalPrice * (int)$item['quantity']));
          ?>
          <tr>
            <td>
              <div class="item-name"><?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php if ($hasItemDisc): ?>
                <div class="item-disc-tag">تخفیف: <?= (int)$item['discount_percent'] ?>٪ | قبل: <?= number_format($origPrice) ?></div>
              <?php endif; ?>
            </td>
            <td style="text-align:center; font-weight:700;"><?= (int)$item['quantity'] ?></td>
            <td style="text-align:center;"><?= number_format($finalPrice) ?></td>
            <td style="text-align:left; font-weight:700;"><?= number_format($lineTotal) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="totals-box">
      <div class="total-row">
        <span>جمع کل اقلام:</span>
        <span><b><?= number_format((float)($payload['subtotal'] ?? $payload['total_amount'] ?? 0)) ?></b> تومان</span>
      </div>

      <?php if (!empty($payload['discount_amount']) && (float)$payload['discount_amount'] > 0): ?>
        <div class="total-row" style="color:#000;">
          <span>تخفیف سفارش:</span>
          <span><b><?= number_format((float)$payload['discount_amount']) ?>-</b> تومان</span>
        </div>
      <?php endif; ?>

      <div class="total-row final-amount">
        <span>مبلغ نهایی:</span>
        <span><?= number_format((float)($payload['total_amount'] ?? 0)) ?> تومان</span>
      </div>

      <div class="total-row" style="font-size:10px;">
        <span>روش پرداخت:</span>
        <span><b><?= htmlspecialchars((string)($payload['payment_method'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></b></span>
      </div>
    </div>

    <?php if (!empty($payload['notes'])): ?>
      <div class="receipt-notes">
        <strong>یادداشت:</strong> <?= htmlspecialchars((string)$payload['notes'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="receipt-footer">
      <div style="font-weight:700; font-size:9.5px;">با تشکر از حضور گرم شما در <?= htmlspecialchars($cafeTitle, ENT_QUOTES, 'UTF-8') ?></div>
      <?php if ($cafeAddress): ?><div><?= htmlspecialchars($cafeAddress, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($cafePhone): ?><div>تلفن: <b><?= htmlspecialchars($cafePhone, ENT_QUOTES, 'UTF-8') ?></b></div><?php endif; ?>
    </div>
  </div>

  <?php if ($autoprint): ?>
  <script>
    (function() {
      function startPrint() {
        if (document.fonts && document.fonts.ready) {
          document.fonts.ready.then(function() {
            setTimeout(function() { window.print(); }, 100);
          });
        } else {
          setTimeout(function() { window.print(); }, 120);
        }
      }
      if (document.readyState === 'complete' || document.readyState === 'interactive') {
        startPrint();
      } else {
        window.addEventListener('DOMContentLoaded', startPrint);
      }
    })();
  </script>
  <?php endif; ?>

</body>
</html>