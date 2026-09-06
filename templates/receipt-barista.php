<?php
/**
 * templates/receipt-barista.php
 * -----------------------------------------------------------------------
 * برگه آماده‌سازی باریستا (رسید حرارتی ۸۰ میلی‌متری)
 * مخصوص میز کار باریستا، بدون قیمت‌ها، با نمایش درشت شماره سفارش و اقلام.
 * -----------------------------------------------------------------------
 */

/** @var array $payload */
/** @var bool $autoprint */
/** @var string $cafeTitle */

$autoprint = !empty($autoprint);
$cafeTitle = $cafeTitle ?? 'کافه دنج';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>برگه آماده‌سازی <?= htmlspecialchars((string)($payload['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></title>
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
      font-size: 12px;
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
      padding-bottom: 5px;
      border-bottom: 1.5px dashed #000;
      margin-bottom: 6px;
    }

    .cafe-name {
      font-size: 11.5px;
      font-weight: 600;
      margin-bottom: 2px;
    }

    .badge-barista {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      background: #000;
      color: #fff;
      padding: 1.5px 6px;
      border-radius: 3px;
      margin-bottom: 4px;
    }

    .order-number-box {
      border: 1.5px solid #000;
      padding: 4px 3px;
      margin: 4px 0;
      text-align: center;
      border-radius: 3px;
    }

    .order-num-label {
      font-size: 9px;
      font-weight: 700;
      display: block;
      color: #000;
    }

    .order-num-val {
      font-size: 15px;
      font-weight: 800;
      letter-spacing: 0.5px;
      direction: ltr;
      display: inline-block;
      color: #000;
    }

    .meta-line {
      display: flex;
      justify-content: space-between;
      font-size: 9.5px;
      font-weight: 600;
      margin-bottom: 2.5px;
      line-height: 1.35;
      color: #000;
    }

    .section-title {
      font-size: 10px;
      font-weight: 700;
      padding: 3px 0;
      border-bottom: 1px solid #000;
      margin-bottom: 4px;
      color: #000;
    }

    .item-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 4px 0;
      border-bottom: 1px dashed #000;
      page-break-inside: avoid;
    }

    .item-name {
      font-size: 11px;
      font-weight: 700;
      line-height: 1.3;
      flex: 1;
    }

    .item-qty {
      font-size: 11.5px;
      font-weight: 700;
      background: #f0f0f0;
      border: 1px solid #000;
      border-radius: 3px;
      padding: 1px 6px;
      margin-right: 6px;
      white-space: nowrap;
    }

    .notes-box {
      margin-top: 6px;
      padding: 4px 6px;
      border: 1px dashed #000;
      background: #fafafa;
      border-radius: 3px;
    }

    .notes-box strong {
      display: block;
      font-size: 9.5px;
      font-weight: 700;
      margin-bottom: 2px;
      color: #000;
    }

    .notes-box p {
      font-size: 10px;
      font-weight: 600;
      color: #000;
      margin: 0;
      line-height: 1.4;
      word-break: break-word;
      white-space: pre-line;
    }

    .footer-note {
      text-align: center;
      margin-top: 8px;
      padding-top: 4px;
      border-top: 1px dashed #000;
      font-size: 9px;
      font-weight: 600;
      color: #000;
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

      .badge-barista {
        background: #000 !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .item-qty {
        background: #fff !important;
        border: 2px solid #000 !important;
      }

      .notes-box {
        background: transparent !important;
      }

      .item-row {
        page-break-inside: avoid;
      }
    }
  </style>
</head>
<body>

  <div class="screen-toolbar no-print">
    <button type="button" class="btn-print" onclick="window.print()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
      چاپ برگه آماده‌سازی
    </button>
    <button type="button" class="btn-close" onclick="if(window.history.length > 1) { window.history.back(); } else { window.close(); }">
      بازگشت / بستن
    </button>
  </div>

  <div class="receipt-wrapper">
    <div class="receipt-header">
      <div class="cafe-name"><?= htmlspecialchars($cafeTitle, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="badge-barista">برگه آماده‌سازی باریستا</div>

      <div class="order-number-box">
        <span class="order-num-label">شماره سفارش</span>
        <span class="order-num-val"><?= htmlspecialchars((string)($payload['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <div class="meta-line">
      <span>تاریخ: <?= htmlspecialchars((string)($payload['date_jalali'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      <span>ساعت: <?= htmlspecialchars((string)($payload['time'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="meta-line" style="margin-bottom:8px;">
      <span>مشتری: <strong><?= htmlspecialchars((string)($payload['customer_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></span>
      <?php if (!empty($payload['customer_phone']) && $payload['customer_phone'] !== '-'): ?>
        <span>تماس: <?= htmlspecialchars((string)$payload['customer_phone'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <div class="section-title">اقلام جهت آماده‌سازی:</div>

    <div class="items-list">
      <?php foreach (($payload['items'] ?? []) as $item): ?>
        <div class="item-row">
          <div class="item-name"><?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="item-qty"><?= (int)$item['quantity'] ?> عدد</div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php 
      $orderNotes = trim((string)($payload['notes'] ?? ($payload['customer_note'] ?? '')));
    ?>
    <?php if ($orderNotes !== ''): ?>
      <div class="notes-box">
        <strong>یادداشت و توضیحات مشتری:</strong>
        <p><?= htmlspecialchars($orderNotes, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    <?php endif; ?>

    <div class="footer-note">
      برگه داخلی میز آماده‌سازی باریستا
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