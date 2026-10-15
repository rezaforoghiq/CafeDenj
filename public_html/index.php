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

require_once __DIR__ . '/../classes/GuestSession.php';

$categories  = Category::all();
$products    = Product::all();
$activeEvent = Event::getActive();

// بازیابی تعداد آیتم‌های سبد خرید بر اساس guest_token
$guestToken  = GuestSession::getToken();
$cartCount   = array_sum(array_column(Order::cart($guestToken), 'quantity'));
$cartBadgeClass = $cartCount > 0 ? '' : ' is-empty';
$cartCountDisplay = $cartCount > 0 ? toPersianDigits((string) $cartCount) : '';

$cartBadge = '<span class="action-count' . ($cartCount > 0 ? '' : ' is-empty') . '" data-cart-count>'
    . ($cartCount > 0 ? toPersianDigits((string) $cartCount) : '') . '</span>';

$headerCartBtn = '<a class="header-cart-btn' . ($cartCount > 0 ? '' : ' is-hidden') . '" id="headerCartBtn" href="cart" aria-label="سبد خرید" title="سبد خرید">'
    . '<svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
    . '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>'
    . '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>'
    . '</svg>'
    . '<span class="header-cart-badge' . $cartBadgeClass . '" id="headerCartBadge" data-cart-count>' . $cartCountDisplay . '</span>'
    . '</a>';

// ناوبار: لینک‌های سبد خرید و سفارش‌ها (بدون نیاز به لاگین)
$actionLinks = '<a class="user-menu-link" href="orders"><span>سفارش‌های من</span></a>'
    . '<a class="user-menu-link" href="cart"><span>سبد خرید</span>' . $cartBadge . '</a>';

$desktopAccountActions = '<div class="user-menu-simple">' . $actionLinks . '</div>' . $headerCartBtn;
$mobileAccountActions  = $actionLinks;
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
// سبد خرید از قبل بارگذاری شده (برای نمایش تعداد هر محصول در کارت‌ها)
$cartQtyMap  = [];
foreach (Order::cart($guestToken) as $cr) {
    $cartQtyMap[(int) $cr['product_id']] = (int) $cr['quantity'];
}

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
        'CART_ACTION'          => (function() use ($product, $cartQtyMap) {
            if (($product['status'] ?? 'active') !== 'active') {
                return '<button type="button" class="add-to-cart-btn is-disabled" disabled style="background:var(--surface);color:var(--muted);border-color:var(--line);cursor:not-allowed;width:100%;">ناموجود</button>';
            }
            $cQty = $cartQtyMap[(int) $product['id']] ?? 0;
            $hasItems = $cQty > 0;
            $displayQty = $hasItems ? $cQty : 1;
            return '<form method="post" action="cart-action" class="cart-form cart-add" data-cart-add data-product-id="' . (int) $product['id'] . '" data-quantity="' . $cQty . '">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">
                <input type="hidden" name="product_id" value="' . (int) $product['id'] . '">
                <input type="hidden" name="action" value="' . ($hasItems ? 'update' : 'add') . '">
                <input type="hidden" name="quantity" value="' . $cQty . '" data-qty-input>
                <div class="cart-control-slot ' . ($hasItems ? 'has-items' : '') . '">
                  <button type="submit" class="add-to-cart-btn ' . ($hasItems ? 'is-hidden' : '') . '" data-action="initial-add">افزودن به سبد</button>
                  <div class="stepper-wrap ' . ($hasItems ? 'is-active' : '') . '" aria-label="مدیریت تعداد سفارش" role="group">
                    <button type="button" class="step-btn step-minus" data-action="decrease" aria-label="کاهش تعداد">−</button>
                    <span class="step-qty" data-step-qty>' . toPersianDigits((string) $displayQty) . '</span>
                    <button type="button" class="step-btn step-plus" data-action="increase" aria-label="افزایش تعداد">+</button>
                  </div>
                </div>
              </form>';
        })(),
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