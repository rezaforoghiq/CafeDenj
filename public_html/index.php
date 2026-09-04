<?php
/**
 * public_html/index.php
 * -----------------------------------------------------------------------
 * صفحهٔ اصلی منوی عمومی کافه.
 *
 * این فایل فقط PHP و منطق است — هیچ HTML/CSS/JS مستقیم داخلش نیست.
 * کل طراحی ظاهری در این فایل‌ها زندگی می‌کند و هرکدام را می‌توانید
 * بدون دست‌زدن به این فایل تغییر بدهید:
 *   - templates/menu.html          (ساختار HTML)
 *   - public_html/assets/css/menu.css   (استایل)
 *   - public_html/assets/js/menu.js     (رفتار/تعامل)
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Template.php';
require_once __DIR__ . '/../classes/Category.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/Order.php';

$categories  = Category::all();
$products    = Product::all(['status' => 'active']);
$activeEvent = Event::getActive();

$isLoggedIn = isset($_SESSION['customer_id'], $_SESSION['customer_display_name']);
$desktopAccountActions = '';
$mobileAccountActions = '';

if ($isLoggedIn) {
    $customerId = (int) $_SESSION['customer_id'];
    $cartCount = array_sum(array_column(Order::cart($customerId), 'quantity'));
    $pendingOrdersCount = count(array_filter(Order::mine($customerId), fn($o) => ($o['status'] ?? '') === 'pending'));
    $cartBadge = $cartCount > 0 ? '<span class="action-count" data-cart-count>' . toPersianDigits((string) $cartCount) . '</span>' : '<span class="action-count is-empty" data-cart-count></span>';
    $ordersBadge = $pendingOrdersCount > 0 ? '<span class="action-count">' . toPersianDigits((string) $pendingOrdersCount) . '</span>' : '';
    $displayName = htmlspecialchars((string) $_SESSION['customer_display_name'], ENT_QUOTES, 'UTF-8');
    $initial = htmlspecialchars(mb_substr((string) $_SESSION['customer_display_name'], 0, 1), ENT_QUOTES, 'UTF-8');
    $actionLinks = '<a class="user-menu-link" href="orders"><span>سفارش‌ها</span>' . $ordersBadge . '</a>' . '<a class="user-menu-link" href="cart"><span>سبد خرید</span>' . $cartBadge . '</a>' . '<a class="user-menu-link" href="profile"><span>پروفایل</span></a>' . '<a class="user-menu-link user-menu-logout" href="customer-logout"><span>خروج</span></a>';
    $headerCartBtn = '<a class="header-cart-btn' . ($cartCount > 0 ? '' : ' is-hidden') . '" id="headerCartBtn" href="cart" aria-label="سبد خرید" title="سبد خرید">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>'
        . '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>'
        . '</svg>'
        . '<span class="header-cart-badge' . ($cartCount > 0 ? '' : ' is-empty') . '" id="headerCartBadge" data-cart-count>' . ($cartCount > 0 ? toPersianDigits((string) $cartCount) : '') . '</span>'
        . '</a>';
    $desktopAccountActions = '<div class="user-menu"><button class="profile-trigger" id="profileTrigger" type="button" aria-label="منوی حساب کاربری" aria-haspopup="true" aria-controls="profileMenu" aria-expanded="false"><span class="profile-avatar" aria-hidden="true">' . $initial . '</span><span class="profile-label">' . $displayName . '</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></button><div class="profile-menu" id="profileMenu"><div class="profile-menu-head"><span class="profile-avatar" aria-hidden="true">' . $initial . '</span><div><b>' . $displayName . '</b><small>حساب کاربری</small></div></div>' . $actionLinks . '</div></div>' . $headerCartBtn;
    $mobileAccountActions = '<span class="account-welcome">سلام، ' . $displayName . '</span>' . $actionLinks;
} else {
    $desktopAccountActions = '<a class="account-link" href="login">ورود</a><a class="account-link account-link-primary" href="register">ثبت‌نام</a>';
    $mobileAccountActions = $desktopAccountActions;
}
$html = Template::load(__DIR__ . '/../templates/menu.html');

// ---------------------------------------------------------------
// نوار دسته‌بندی
// ---------------------------------------------------------------
[$html, $categoryBlock] = Template::extractBlock($html, 'CATEGORY_ITEM');

$categoryHtml = '';
foreach ($categories as $cat) {
    $categoryHtml .= Template::fill($categoryBlock, [
        'SLUG' => htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8'),
        'NAME' => htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'),
    ]);
}

// ---------------------------------------------------------------
// کارت‌های محصول
// ---------------------------------------------------------------
[$html, $productBlock] = Template::extractBlock($html, 'PRODUCT_ITEM');

$categorySlugById = [];
foreach ($categories as $cat) {
    $categorySlugById[(int) $cat['id']] = $cat['slug'];
}

$productHtml = '';
foreach ($products as $product) {
    $name = htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8');

    $thumbHtml = $product['image']
        ? '<img src="' . UPLOAD_DIR_URL . '/' . htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '">'
        : 'تصویر محصول';

    $descriptionHtml = $product['description']
        ? '<p class="item-desc">' . htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') . '</p>'
        : '';

    $badgeHtml = $product['badge']
        ? '<span class="badge">' . htmlspecialchars($product['badge'], ENT_QUOTES, 'UTF-8') . '</span>'
        : '';

    // ---- تخفیف (اگر فعال و در بازهٔ زمانی معتبر باشد) ----
    $discount = Product::calculateDiscount($product);

    if ($discount['has_discount']) {
        $priceHtml = '<span class="price-original">' . toPersianDigits(number_format($discount['original'])) . '</span> '
            . '<span class="price-final">' . toPersianDigits(number_format($discount['final'])) . '</span>';
        $discountBadgeHtml = '<span class="discount-badge">' . toPersianDigits((string) $discount['percent']) . '٪ تخفیف</span>';
    } else {
        $priceHtml = toPersianDigits(number_format($discount['final']));
        $discountBadgeHtml = '';
    }

    $productHtml .= Template::fill($productBlock, [
        'CATEGORY_SLUG'        => htmlspecialchars($categorySlugById[(int) $product['category_id']] ?? '', ENT_QUOTES, 'UTF-8'),
        'NAME'                 => $name,
        'THUMB_HTML'           => $thumbHtml,
        'PRICE_HTML'           => $priceHtml,
        'DISCOUNT_BADGE_HTML'  => $discountBadgeHtml,
        'DESCRIPTION_HTML'     => $descriptionHtml,
        'BADGE_HTML'           => $badgeHtml,
        'CART_ACTION'          => isset($_SESSION['customer_id'])
            ? '<form method="post" action="cart-action" class="cart-add" data-cart-add><input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="product_id" value="' . (int) $product['id'] . '"><button type="submit">افزودن به سبد</button></form>'
            : '<a class="cart-login" href="login">برای سفارش وارد شوید</a>',
    ]);
}

// ---------------------------------------------------------------
// پاپ‌آپ ایونت (فقط اگر ایونتی فعال باشد رندر می‌شود)
// ---------------------------------------------------------------
[$html, $eventBlock] = Template::extractBlock($html, 'EVENT_POPUP');

$eventHtml = '';
if ($activeEvent !== null) {
    $eventHtml = Template::fill($eventBlock, [
        'EVENT_TITLE'          => htmlspecialchars($activeEvent['title'], ENT_QUOTES, 'UTF-8'),
        'EVENT_CONTENT'        => nl2br(htmlspecialchars($activeEvent['content'], ENT_QUOTES, 'UTF-8')),
    ]);
}

// ---------------------------------------------------------------
// جایگزینی نهایی همهٔ placeholder ها
// ---------------------------------------------------------------
echo Template::fill($html, [
    'PAGE_TITLE'     => 'کافه دنج | منو',
    'CATEGORY_ITEM'  => $categoryHtml,
    'PRODUCT_ITEM'   => $productHtml,
    'EVENT_POPUP'    => $eventHtml,
    'DESKTOP_ACCOUNT_ACTIONS' => $desktopAccountActions,
    'MOBILE_ACCOUNT_ACTIONS'  => $mobileAccountActions,
]);