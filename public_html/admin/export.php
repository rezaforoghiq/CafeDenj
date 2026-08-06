<?php
/**
 * public_html/admin/export.php
 * -----------------------------------------------------------------------
 * Excel export for admin orders and reports, using existing filter logic.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Order.php';
require_once __DIR__ . '/../../classes/Barista.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Jalali.php';

requireLogin();

$type = (string) ($_GET['type'] ?? '');
if ($type !== 'orders' && $type !== 'reports') {
    http_response_code(400);
    echo 'Invalid export type';
    exit;
}

$range = (string) ($_GET['range'] ?? 'today');
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
[$fromDate, $toDate] = Barista::resolveRange($range, $from, $to);

$pdo = Database::getConnection();

function xmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function excelColumn(int $index): string
{
    $letters = '';
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $letters = chr(65 + $remainder) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

function rowXml(int $rowNumber, array $cells, bool $header = false): string
{
    $xml = '<row r="' . $rowNumber . '">';
    $col = 1;
    foreach ($cells as $cell) {
        $ref = excelColumn($col++) . $rowNumber;
        if ($cell === null || $cell === '') {
            $xml .= '<c r="' . $ref . '"/>';
            continue;
        }
        if (is_int($cell) || is_float($cell)) {
            $xml .= '<c r="' . $ref . '"' . ($header ? ' s="1"' : '') . '><v>' . $cell . '</v></c>';
        } else {
            $xml .= '<c r="' . $ref . '" t="inlineStr"' . ($header ? ' s="1"' : '') . '><is><t>' . xmlEscape((string) $cell) . '</t></is></c>';
        }
    }
    $xml .= '</row>';
    return $xml;
}

function worksheetXml(string $sheetName, array $rows): string
{
    $maxCol = 0;
    foreach ($rows as $row) {
        $maxCol = max($maxCol, count($row));
    }
    $colsXml = '';
    if ($maxCol > 0) {
        $colsXml .= '<cols>';
        for ($i = 1; $i <= $maxCol; $i++) {
            $colsXml .= '<col min="' . $i . '" max="' . $i . '" width="20" customWidth="1"/>';
        }
        $colsXml .= '</cols>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>' .
        $colsXml .
        '<sheetData>';

    $rowNumber = 1;
    foreach ($rows as $idx => $row) {
        $sheetXml .= rowXml($rowNumber++, $row, $idx === 0);
    }
    $sheetXml .= '</sheetData>';
    if ($maxCol > 0) {
        $lastCol = excelColumn($maxCol);
        $sheetXml .= '<autoFilter ref="A1:' . $lastCol . '1"/>';
    }
    $sheetXml .= '</worksheet>';
    return $sheetXml;
}

function workbookXml(array $sheetNames): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets>';
    $id = 1;
    foreach ($sheetNames as $name) {
        $xml .= '<sheet name="' . xmlEscape($name) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
        $id++;
    }
    $xml .= '</sheets></workbook>';
    return $xml;
}

function workbookRelsXml(int $sheetCount): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }
    $xml .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $xml .= '</Relationships>';
    return $xml;
}

function stylesXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="2"><font><sz val="11"/><color rgb="FF000000"/><name val="Arial"/></font><font><b val="1"/><sz val="11"/><color rgb="FF000000"/><name val="Arial"/></font></fonts>' .
        '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="2"><xf xfId="0" fontId="0" fillId="0" borderId="0"/><xf xfId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/></cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
        '</styleSheet>';
}

function contentTypesXml(int $sheetCount): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $xml .= '</Types>';
    return $xml;
}

function rootRelsXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';
}

function createZipArchive(array $files): string
{
    $data = '';
    $centralDirectory = '';
    $offset = 0;

    foreach ($files as $name => $content) {
        $crc = crc32($content);
        $compressed = $content;
        $csize = strlen($compressed);
        $usize = $csize;
        $nameLen = strlen($name);

        $localHeader = pack('VvvvVvvvVvv', 0x04034b50, 20, 0, 0, 0, 0, 0, $crc, $csize, $usize, $nameLen, 0);
        $data .= $localHeader . $name . $compressed;

        $centralHeader = pack('VvvvvvvvVvvvVVvV', 0x02014b50, 0, 20, 0, 0, 0, 0, 0, $crc, $csize, $usize, $nameLen, 0, 0, 0, 0, 0, $offset);
        $centralDirectory .= $centralHeader . $name;
        $offset += strlen($localHeader) + $nameLen + $csize;
    }

    $centralSize = strlen($centralDirectory);
    $endRecord = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), $centralSize, $offset, 0);

    return $data . $centralDirectory . $endRecord;
}

function buildXlsx(array $sheets): string
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Unable to create XLSX archive.');
        }

        $sheetNames = array_keys($sheets);
        $zip->addFromString('[Content_Types].xml', contentTypesXml(count($sheetNames)));
        $zip->addFromString('_rels/.rels', rootRelsXml());
        $zip->addFromString('xl/workbook.xml', workbookXml($sheetNames));
        $zip->addFromString('xl/_rels/workbook.xml.rels', workbookRelsXml(count($sheetNames)));
        $zip->addFromString('xl/styles.xml', stylesXml());

        $sheetId = 1;
        foreach ($sheets as $sheetXml) {
            $zip->addFromString('xl/worksheets/sheet' . $sheetId . '.xml', $sheetXml);
            $sheetId++;
        }

        $zip->close();
        $content = file_get_contents($tmpFile);
        unlink($tmpFile);
        return $content;
    }

    // fallback: create a simple ZIP package in pure PHP
    return createZipArchive($sheets);
}

function getStatusLabel(string $status): string
{
    return ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'completed' => 'تکمیل شده'][$status] ?? $status;
}

function getPaymentLabel(?string $method): string
{
    return $method === 'card' ? 'کارتخوان' : ($method === 'cash' ? 'نقدی' : ($method === 'transfer' ? 'کارت به کارت' : ''));
}

function buildOrdersExport(array $filters): array
{
    // Always export only completed orders regardless of passed filters
    $filters['status'] = 'completed';

    $orders = Order::report($filters);

    // ensure we only process orders with status 'completed'
    $orders = array_filter($orders, static fn($order) => isset($order['status']) && $order['status'] === 'completed');

    $orderIds = array_map(static fn($order) => (int) $order['id'], $orders);
    $items = [];
    if (!empty($orderIds)) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = Database::getConnection()->prepare('SELECT order_id, product_name, quantity, price FROM order_items WHERE order_id IN (' . $placeholders . ') ORDER BY order_id, id ASC');
        $stmt->execute($orderIds);
        while ($row = $stmt->fetch()) {
            $items[(int) $row['order_id']][] = $row;
        }
    }

    $rows = [[
        'شناسه سفارش', 'شماره سفارش', 'تاریخ سفارش', 'وضعیت', 'مشتری', 'تلفن', 'روش پرداخت', 'کد تخفیف', 'درصد تخفیف', 'مبلغ تخفیف', 'مبلغ نهایی', 'باریستا', 'نام محصول', 'تعداد', 'قیمت واحد', 'جمع محصول',
    ]];

    foreach ($orders as $order) {
        $orderItems = $items[(int) $order['id']] ?? [];
        if (empty($orderItems)) {
            $orderItems = [['product_name' => '', 'quantity' => 0, 'price' => 0]];
        }
        foreach ($orderItems as $item) {
            $rows[] = [
                (int) $order['id'],
                $order['order_number'],
                Jalali::format($order['created_at']),
                getStatusLabel((string) $order['status']),
                $order['customer_name'] ?: '',
                $order['phone'] ?: '',
                getPaymentLabel($order['payment_method'] ?? null),
                $order['coupon_code'] ?? '',
                $order['coupon_percent'] !== null ? (int) $order['coupon_percent'] : 0,
                $order['discount_amount'] !== null ? (float) $order['discount_amount'] : 0,
                (float) $order['total_price'],
                $order['barista_name'] ?? '',
                $item['product_name'],
                (int) $item['quantity'],
                (float) $item['price'],
                (float) $item['quantity'] * (float) $item['price'],
            ];
        }
    }

    return $rows;
}

function buildReportsExport(string $range, ?string $fromDate, ?string $toDate): array
{
    $pdo = Database::getConnection();
    $where = ['o.status = "completed"'];
    $params = [];
    if ($fromDate) {
        $where[] = 'o.created_at >= :from';
        $params['from'] = $fromDate . ' 00:00:00';
    }
    if ($toDate) {
        $where[] = 'o.created_at <= :to';
        $params['to'] = $toDate . ' 23:59:59';
    }
    $whereSql = implode(' AND ', $where);

    $summaryStmt = $pdo->prepare("SELECT COUNT(*) AS orders_count, COALESCE(SUM(o.total_price), 0) AS sales_total, COALESCE(AVG(o.total_price), 0) AS average_order FROM orders o WHERE $whereSql");
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch() ?: ['orders_count' => 0, 'sales_total' => 0, 'average_order' => 0];

    $bestProductStmt = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.quantity * oi.price) AS revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $whereSql GROUP BY oi.product_id, oi.product_name ORDER BY quantity DESC, revenue DESC LIMIT 1");
    $bestProductStmt->execute($params);
    $bestProduct = $bestProductStmt->fetch();

    $productSalesStmt = $pdo->prepare("SELECT p.id AS product_id, p.name AS product_name, COALESCE(SUM(oi.quantity), 0) AS quantity, COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue, MAX(o.created_at) AS last_order_at FROM products p LEFT JOIN order_items oi ON oi.product_id = p.id LEFT JOIN orders o ON o.id = oi.order_id AND $whereSql GROUP BY p.id, p.name ORDER BY quantity DESC, revenue DESC, p.name ASC");
    $productSalesStmt->execute($params);
    $productSales = $productSalesStmt->fetchAll();

    $lowSellersSql = "SELECT p.id, p.name, COALESCE(SUM(oi.quantity), 0) AS quantity FROM products p LEFT JOIN order_items oi ON oi.product_id = p.id LEFT JOIN orders o ON o.id = oi.order_id AND o.status = 'completed'";
    if ($fromDate) {
        $lowSellersSql .= ' AND o.created_at >= :from';
    }
    if ($toDate) {
        $lowSellersSql .= ' AND o.created_at <= :to';
    }
    $lowSellersSql .= ' GROUP BY p.id, p.name HAVING quantity < 5 ORDER BY quantity ASC, p.name ASC LIMIT 20';
    $lowSellersStmt = $pdo->prepare($lowSellersSql);
    $lowSellersStmt->execute($params);
    $lowSellers = $lowSellersStmt->fetchAll();

    $baristaStmt = $pdo->prepare("SELECT b.id, b.full_name, COUNT(o.id) AS completed_orders, COALESCE(SUM(o.total_price), 0) AS total_revenue, COALESCE(AVG(o.total_price), 0) AS average_amount FROM baristas b LEFT JOIN orders o ON o.barista_id = b.id AND o.status = 'completed'" . ($fromDate ? ' AND o.created_at >= :from' : '') . ($toDate ? ' AND o.created_at <= :to' : '') . " GROUP BY b.id, b.full_name ORDER BY completed_orders DESC, total_revenue DESC");
    $baristaStmt->execute($params);
    $baristaPerformance = $baristaStmt->fetchAll();

    $hourlyStmt = $pdo->prepare("SELECT HOUR(o.created_at) AS hour, COUNT(*) AS order_count FROM orders o WHERE $whereSql GROUP BY hour ORDER BY hour ASC");
    $hourlyStmt->execute($params);
    $hourlyData = $hourlyStmt->fetchAll();

    $summaryRows = [[
        'Metric', 'Value',
    ],[
        'Orders Completed', (int) $summary['orders_count'],
    ],[
        'Total Sales', (float) $summary['sales_total'],
    ],[
        'Average Order Value', (float) $summary['average_order'],
    ],[
        'Best Product', $bestProduct['product_name'] ?? '—',
    ]];

    $productSalesRows = [[
        'Product Name', 'Quantity Sold', 'Revenue', 'Last Order',
    ]];
    foreach ($productSales as $product) {
        $productSalesRows[] = [
            $product['product_name'],
            (int) $product['quantity'],
            (float) $product['revenue'],
            Jalali::format($product['last_order_at']),
        ];
    }

    $bestSellersRows = [[
        'Rank', 'Product Name', 'Quantity Sold',
    ]];
    foreach (array_slice($productSales, 0, 10) as $index => $product) {
        $bestSellersRows[] = [
            $index + 1,
            $product['product_name'],
            (int) $product['quantity'],
        ];
    }

    $lowSellersRows = [[
        'Product Name', 'Quantity Sold',
    ]];
    foreach ($lowSellers as $item) {
        $lowSellersRows[] = [
            $item['name'],
            (int) $item['quantity'],
        ];
    }

    $baristaRows = [[
        'Barista', 'Completed Orders', 'Total Revenue', 'Average Order Value',
    ]];
    foreach ($baristaPerformance as $barista) {
        $baristaRows[] = [
            $barista['full_name'],
            (int) $barista['completed_orders'],
            (float) $barista['total_revenue'],
            (float) $barista['average_amount'],
        ];
    }

    $hourlyRows = [[
        'Hour', 'Orders',
    ]];
    foreach ($hourlyData as $hour) {
        $hourlyRows[] = [
            str_pad((string) $hour['hour'], 2, '0', STR_PAD_LEFT) . ':00',
            (int) $hour['order_count'],
        ];
    }

    // Payment methods report (only completed orders, respecting range)
    $paymentStmt = $pdo->prepare("SELECT o.payment_method, COUNT(*) AS orders_count, COALESCE(SUM(o.total_price), 0) AS total_amount FROM orders o WHERE $whereSql GROUP BY o.payment_method ORDER BY total_amount DESC");
    $paymentStmt->execute($params);
    $paymentRows = [[ 'Payment Method', 'Completed Orders', 'Total Amount' ]];
    while ($row = $paymentStmt->fetch()) {
        $paymentRows[] = [
            getPaymentLabel($row['payment_method'] ?? null),
            (int) $row['orders_count'],
            (float) $row['total_amount'],
        ];
    }

    return [
        'Summary' => $summaryRows,
        'Product Sales' => $productSalesRows,
        'Best Sellers' => $bestSellersRows,
        'Low Selling' => $lowSellersRows,
        'Barista Performance' => $baristaRows,
        'Sales Hours' => $hourlyRows,
        'Payment Methods' => $paymentRows,
    ];
}

$filenameBase = $type === 'orders' ? 'orders' : 'reports';
$filename = $filenameBase . '_' . date('Y-m-d') . '.xlsx';

// Require ZipArchive for proper .xlsx (OOXML) exports
if (!class_exists('ZipArchive')) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="fa"><head><meta charset="utf-8"><title>Export Disabled</title></head><body style="font-family:Tahoma, Arial, sans-serif;direction:rtl;text-align:right;padding:20px">';
    echo '<h2>صدور اکسل غیرفعال است</h2>';
    echo '<p>افزونه PHP <strong>ZipArchive</strong> در سرور فعال نیست. برای خروجی .xlsx لطفاً ماژول zip (php_zip) را فعال کنید و وب‌سرور را مجدداً راه‌اندازی نمایید.</p>';
    echo '<p>مثال (XAMPP/Windows): در فایل <code>php.ini</code> خط <code>extension=zip</code> یا <code>extension=php_zip.dll</code> را فعال کرده و Apache را ری‌استارت کنید.</p>';
    echo '<p>پس از فعال‌سازی می‌توانید مجدداً از دکمه خروجی اکسل استفاده کنید.</p>';
    echo '</body></html>';
    exit;
}

if ($type === 'orders') {
    $filters = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'status' => (string) ($_GET['status'] ?? ''),
        'barista_id' => (string) ($_GET['barista_id'] ?? ''),
        'from' => $from,
        'to' => $to,
        'sort' => (string) ($_GET['sort'] ?? 'date_desc'),
    ];
    $orderRows = buildOrdersExport($filters);
    $sheetsXml = ['Orders' => worksheetXml('Orders', $orderRows)];
    $outData = buildXlsx($sheetsXml);
    $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $ext = 'xlsx';
} else {
    $sheetsRows = buildReportsExport($range, $fromDate, $toDate);
    $sheetsXml = [];
    foreach ($sheetsRows as $name => $rows) {
        $sheetsXml[$name] = worksheetXml($name, $rows);
    }
    $outData = buildXlsx($sheetsXml);
    $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $ext = 'xlsx';
}

// filename with Jalali date (ASCII numbers)
$jalaliDate = Jalali::format(date('Y-m-d'), false); // returns Persian digits e.g. ۱۴۰۵/۰۵/۱۱
$fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
$en = ['0','1','2','3','4','5','6','7','8','9'];
$asciiJalali = str_replace($fa, $en, $jalaliDate);
$asciiJalali = str_replace('/', '-', $asciiJalali);
$filename = $filenameBase . '_' . $asciiJalali . '.' . $ext;

// Clear (and disable) output buffers to avoid corruption of binary output
if (!headers_sent()) {
    if (ob_get_length()) {
        @ob_end_clean();
    }
}

header('Content-Type: ' . $contentType);
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . strlen($outData));
// Support UTF-8 filenames (both fallback and RFC5987)
$disposition = 'attachment; filename="' . $filename . '"';
$disposition .= "; filename*=utf-8''" . rawurlencode($filename);
header('Content-Disposition: ' . $disposition);
header('Cache-Control: max-age=0');
echo $outData;
exit;
