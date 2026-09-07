<?php
/**
 * public_html/admin/menu-display.php
 * -----------------------------------------------------------------------
 * مدیریت ترتیب و چیدمان نمایش دسته‌بندی‌ها و محصولات در منوی عمومی کافه.
 * پشتیبانی کامل از کشیدن و رها کردن (Drag & Drop)، دکمه‌های بالا/پایین و
 * ذخیرهٔ فوری با AJAX و پشتیبانی فرم عادی.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Category.php';
require_once __DIR__ . '/../../classes/ActivityLog.php';

requireLogin();
requirePermission('products.manage');

// ---------------------------------------------------------------
// پردازش درخواست‌های AJAX برای ذخیره ترتیب
// ---------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$isJson = false;
$jsonData = null;
if (!empty($rawInput)) {
    $jsonData = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        $isJson = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $jsonData['action'] ?? $_POST['action'] ?? '';
    $token  = $jsonData['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;

    if (!verifyCsrfToken((string) $token)) {
        if ($isJson || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'نشست شما منقضی شده است. لطفاً صفحه را بازخوانی کنید.']);
            exit;
        }
        $_SESSION['flash_error'] = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
        header('Location: menu-display');
        exit;
    }

    // تغییر ترتیب دسته‌بندی‌ها
    if ($action === 'reorder_categories') {
        $order = $jsonData['order'] ?? $_POST['order'] ?? [];
        if (is_string($order)) {
            $order = array_filter(array_map('intval', explode(',', $order)));
        }

        if (is_array($order) && !empty($order)) {
            Category::updateSortOrder($order);
            ActivityLog::record(
                'menu_reorder_categories',
                Auth::role(),
                Auth::id(),
                Auth::username(),
                null,
                'تغییر ترتیب نمایش دسته‌بندی‌ها در منوی کافه'
            );

            if ($isJson || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'ترتیب دسته‌بندی‌ها با موفقیت ذخیره شد.']);
                exit;
            }

            $_SESSION['flash_success'] = 'ترتیب دسته‌بندی‌ها با موفقیت ذخیره شد.';
            header('Location: menu-display');
            exit;
        }
    }

    // تغییر ترتیب محصولات یک دسته
    if ($action === 'reorder_products') {
        $categoryId = (int) ($jsonData['category_id'] ?? $_POST['category_id'] ?? 0);
        $order = $jsonData['order'] ?? $_POST['order'] ?? [];
        if (is_string($order)) {
            $order = array_filter(array_map('intval', explode(',', $order)));
        }

        if ($categoryId > 0 && is_array($order)) {
            Product::updateSortOrder($categoryId, $order);
            $cat = Category::find($categoryId);
            $catName = $cat ? $cat['name'] : "شناسه {$categoryId}";
            ActivityLog::record(
                'menu_reorder_products',
                Auth::role(),
                Auth::id(),
                Auth::username(),
                null,
                "تغییر ترتیب محصولات دسته «{$catName}» در منوی کافه"
            );

            if ($isJson || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'ترتیب محصولات با موفقیت ذخیره شد.']);
                exit;
            }

            $_SESSION['flash_success'] = 'ترتیب محصولات با موفقیت ذخیره شد.';
            header('Location: menu-display?cat=' . $categoryId);
            exit;
        }
    }

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامترهای ارسالی نامعتبر است.']);
        exit;
    }
}

$activePage = 'menu-display';
$pageTitle  = 'مدیریت نمایش محصولات';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// دریافت لیست دسته‌ها
$categories = Category::all();

// مشخص کردن دسته انتخابی
$selectedCatId = isset($_GET['cat']) ? (int) $_GET['cat'] : null;
$selectedCategory = null;
if ($selectedCatId !== null) {
    foreach ($categories as $cat) {
        if ((int) $cat['id'] === $selectedCatId) {
            $selectedCategory = $cat;
            break;
        }
    }
} elseif (!empty($categories)) {
    $selectedCategory = $categories[0];
    $selectedCatId = (int) $categories[0]['id'];
}

// دریافت محصولات دستهٔ انتخاب‌شده (مرتب بر اساس sort_order)
$categoryProducts = [];
if ($selectedCategory !== null) {
    $categoryProducts = Product::all(['category_id' => (int) $selectedCategory['id']]);
}

$csrfToken = csrfToken();

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="menu-display-page">
  <!-- Apple Design: Translucent Ambient Hero with Dynamic Status Capsule -->
  <div class="apple-menu-hero">
    <div class="hero-inner-content">
      <div class="hero-title-group">
        <h1>مدیریت چیدمان و نمایش منوی آنلاین</h1>
        <p>ترتیب قرارگیری دسته‌بندی‌ها و محصولات را با لمس، کشیدن مستقیم (Direct Manipulation) یا دکمه‌های جهت‌نما تنظیم کنید. چیدمان تعیین‌شده بلافاصله در منوی عمومی مشتریان همگام‌سازی می‌شود.</p>
      </div>

      <div class="hero-actions-cluster">
        <!-- Apple Dynamic Status Capsule (Dynamic Island feel) -->
        <div class="apple-status-capsule" id="appleGlobalStatus" role="status" aria-live="polite">
          <span class="apple-pulse-dot" id="statusPulseDot"></span>
          <span id="appleStatusText">منو کاملاً همگام است</span>
          <button type="button" class="btn-undo-action" id="btnUndoAction" title="بازگرداندن آخرین تغییر ترتیب" aria-label="بازگرداندن تغییر">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
            <span>بازگشت</span>
          </button>
        </div>

        <!-- Live Menu Preview Button with Translucent Glass Material -->
        <a href="../" target="_blank" class="apple-glass-btn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <span>مشاهده منوی مشتریان</span>
        </a>
      </div>
    </div>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success py-2 px-3 mb-3 apple-flash-alert" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="alert alert-danger py-2 px-3 mb-3 apple-flash-alert" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <!-- Apple Metrics Strip -->
  <div class="apple-metrics-strip">
    <div class="metric-bubble">
      <span>تعداد کل دسته‌بندی‌ها:</span>
      <strong><?= count($categories) ?> دسته</strong>
    </div>
    <div class="metric-bubble">
      <span>دسته‌بندی فعال:</span>
      <strong style="color:var(--apple-accent);"><?= $selectedCategory ? htmlspecialchars($selectedCategory['name'], ENT_QUOTES, 'UTF-8') : '—' ?></strong>
    </div>
    <div class="metric-bubble">
      <span>محصولات دسته فعال:</span>
      <strong><?= count($categoryProducts) ?> محصول</strong>
    </div>
  </div>

  <div class="row g-4">
    <!-- ستون ۱: ترتیب دسته‌بندی‌ها -->
    <div class="col-lg-5 col-xl-4">
      <div class="apple-panel-card h-100">
        <div class="apple-panel-header">
          <div class="apple-panel-title">
            <span class="apple-title-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/></svg>
            </span>
            <span>ترتیب دسته‌بندی‌ها</span>
            <span class="apple-title-badge"><?= count($categories) ?></span>
          </div>
          <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn-apple-ghost" id="btnSortCatAlpha" title="مرتب‌سازی سریع الفبایی دسته‌ها">
              الفبا
            </button>
            <button type="button" class="btn-apple-primary" id="btnSaveCategoriesManual">
              ذخیره
            </button>
          </div>
        </div>

        <div class="apple-panel-body">
          <p class="apple-hint-text">
            <span>💡</span>
            <span>اولویت نمایش تب‌ها در بالای منوی کافه بر اساس همین چینش است:</span>
          </p>

          <?php if (empty($categories)): ?>
            <div class="apple-empty-state">
              <div class="empty-icon">📁</div>
              <h6>هنوز دسته‌بندی ثبت نشده است</h6>
              <p>برای شروع چیدمان ابتدا دسته‌بندی جدید بسازید.</p>
            </div>
          <?php else: ?>
            <div class="apple-sortable-list" id="categorySortableList" data-action="categories">
              <?php foreach ($categories as $index => $cat): 
                $isCurrent = $selectedCategory && (int)$cat['id'] === (int)$selectedCategory['id'];
                $catCount = (int)($cat['product_count'] ?? 0);
              ?>
                <div class="apple-sort-item <?= $isCurrent ? 'is-selected-item' : '' ?>" 
                     data-id="<?= (int)$cat['id'] ?>"
                     data-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"
                     draggable="true">
                  <div class="apple-drag-handle" title="بکشید تا جابجا شود" aria-label="جابجایی دسته‌بندی">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                      <circle cx="9" cy="6" r="1.8"/><circle cx="15" cy="6" r="1.8"/>
                      <circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/>
                      <circle cx="9" cy="18" r="1.8"/><circle cx="15" cy="18" r="1.8"/>
                    </svg>
                  </div>

                  <span class="apple-index-badge"><?= $index + 1 ?></span>

                  <div class="apple-item-info">
                    <div class="apple-item-title">
                      <a href="menu-display?cat=<?= (int)$cat['id'] ?>" class="category-name-link">
                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                      </a>
                      <?php if ($isCurrent): ?>
                        <span class="apple-tag-pill tag-active">فعال</span>
                      <?php endif; ?>
                    </div>
                    <div class="apple-item-meta">
                      <code><?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?></code> • <?= $catCount ?> محصول
                    </div>
                  </div>

                  <div class="apple-item-controls">
                    <button type="button" class="btn-apple-stepper move-up" title="انتقال به بالا" aria-label="بالا" <?= $index === 0 ? 'disabled' : '' ?>>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                    </button>
                    <button type="button" class="btn-apple-stepper move-down" title="انتقال به پایین" aria-label="پایین" <?= $index === count($categories) - 1 ? 'disabled' : '' ?>>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <a href="menu-display?cat=<?= (int)$cat['id'] ?>" class="btn-apple-cat-select <?= $isCurrent ? 'is-active-btn' : '' ?>" title="مشاهده محصولات این دسته">
                      <?= $isCurrent ? 'محصولات ✓' : 'انتخاب' ?>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="apple-panel-footer">
          <div class="apple-footer-tip">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span id="catStatus">ذخیره‌سازی به محض جابجایی خودکار است.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ستون ۲: ترتیب محصولات دسته انتخابی -->
    <div class="col-lg-7 col-xl-8">
      <div class="apple-panel-card h-100">
        <div class="apple-panel-header">
          <div class="apple-panel-title">
            <span class="apple-title-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 9h11v5a5.5 5.5 0 0 1-11 0V9Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M8 4v2M12 4v2M4 21h14"/></svg>
            </span>
            <span>
              محصولات دسته:
              <strong style="color:var(--apple-accent);"><?= $selectedCategory ? htmlspecialchars($selectedCategory['name'], ENT_QUOTES, 'UTF-8') : '—' ?></strong>
            </span>
            <span class="apple-title-badge"><?= count($categoryProducts) ?></span>
          </div>

          <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn-apple-ghost" id="btnSortProdPrice" title="مرتب‌سازی بر اساس قیمت">
              بر اساس قیمت
            </button>
            <button type="button" class="btn-apple-primary" id="btnSaveProductsManual" <?= empty($categoryProducts) ? 'disabled' : '' ?>>
              ذخیره
            </button>
          </div>
        </div>

        <div class="apple-panel-body">
          <!-- Apple Segmented Bar for category quick-switching -->
          <div class="apple-segmented-bar-wrapper" role="tablist" aria-label="انتخاب دسته‌بندی">
            <?php foreach ($categories as $cat): 
              $isActive = $selectedCategory && (int)$cat['id'] === (int)$selectedCategory['id'];
              $pCount = (int)($cat['product_count'] ?? 0);
            ?>
              <a href="menu-display?cat=<?= (int)$cat['id'] ?>" 
                 class="apple-segment-pill <?= $isActive ? 'is-active' : '' ?>"
                 role="tab" 
                 aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                <span><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="apple-segment-count"><?= $pCount ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if (!$selectedCategory): ?>
            <div class="apple-empty-state">
              <div class="empty-icon">📂</div>
              <h6>هیچ دسته‌بندی انتخاب نشده است</h6>
              <p>لطفاً ابتدا یک دسته‌بندی را از ستون کناری انتخاب کنید.</p>
            </div>
          <?php elseif (empty($categoryProducts)): ?>
            <div class="apple-empty-state">
              <div class="empty-icon">☕</div>
              <h6>هیچ محصولی در «<?= htmlspecialchars($selectedCategory['name'], ENT_QUOTES, 'UTF-8') ?>» وجود ندارد</h6>
              <p>می‌توانید برای این دسته محصول جدید اضافه کنید و سپس چیدمان آن را مشخص نمایید.</p>
              <a href="add-product?category_id=<?= (int)$selectedCategory['id'] ?>" class="btn-apple-primary mt-2" style="text-decoration:none; display:inline-flex;">
                + افزودن محصول به این دسته
              </a>
            </div>
          <?php else: ?>
            <p class="apple-hint-text">
              <span>💡</span>
              <span>ترتیب چینش این محصولات را در منوی مشتریان تعیین کنید (تب اختصاصی و تب همه):</span>
            </p>

            <div class="apple-sortable-list" id="productSortableList" data-category-id="<?= (int)$selectedCategory['id'] ?>">
              <?php foreach ($categoryProducts as $index => $prod): 
                $thumb = !empty($prod['image']) 
                  ? (defined('UPLOAD_DIR_URL') ? UPLOAD_DIR_URL : '../uploads') . '/' . htmlspecialchars($prod['image'], ENT_QUOTES, 'UTF-8')
                  : null;
                $priceNum = (float)($prod['price'] ?? 0);
              ?>
                <div class="apple-sort-item" 
                     data-id="<?= (int)$prod['id'] ?>"
                     data-price="<?= $priceNum ?>"
                     data-name="<?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>"
                     draggable="true">
                  <div class="apple-drag-handle" title="بکشید تا جابجا شود" aria-label="جابجایی محصول">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                      <circle cx="9" cy="6" r="1.8"/><circle cx="15" cy="6" r="1.8"/>
                      <circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/>
                      <circle cx="9" cy="18" r="1.8"/><circle cx="15" cy="18" r="1.8"/>
                    </svg>
                  </div>

                  <span class="apple-index-badge"><?= $index + 1 ?></span>
                  
                  <div class="apple-product-thumb">
                    <?php if ($thumb): ?>
                      <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                      <span class="thumb-placeholder">☕</span>
                    <?php endif; ?>
                  </div>

                  <div class="apple-item-info">
                    <div class="apple-item-title">
                      <strong class="product-title-text"><?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                      <?php if (!empty($prod['badge'])): ?>
                        <span class="apple-tag-pill tag-badge"><?= htmlspecialchars($prod['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                      <?php if (($prod['status'] ?? 'active') !== 'active'): ?>
                        <span class="apple-tag-pill tag-danger">غیرفعال</span>
                      <?php endif; ?>
                    </div>
                    <div class="apple-item-meta">
                      <span><?= number_format($priceNum) ?> تومان</span>
                      <?php if (!empty($prod['description'])): ?>
                        <span class="d-none d-sm-inline">• <?= htmlspecialchars(mb_substr($prod['description'], 0, 32), ENT_QUOTES, 'UTF-8') ?>...</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="apple-item-controls">
                    <button type="button" class="btn-apple-stepper move-up" title="یک پله به بالا" aria-label="بالا" <?= $index === 0 ? 'disabled' : '' ?>>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                    </button>
                    <button type="button" class="btn-apple-stepper move-down" title="یک پله به پایین" aria-label="پایین" <?= $index === count($categoryProducts) - 1 ? 'disabled' : '' ?>>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <a href="edit-product?id=<?= (int)$prod['id'] ?>" class="btn-apple-quick-edit" title="ویرایش محصول" aria-label="ویرایش">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="apple-panel-footer">
          <div class="apple-footer-tip">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span id="prodStatus">تغییرات به صورت آنی در دیتابیس ثبت و در منو منعکس می‌شوند.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* ==========================================================================
   Apple Design System: Fluid Interfaces, Spring Physics & Translucency
   WWDC Fluid Interfaces & Optical Sizing Standards
   ========================================================================== */

:root {
  --apple-spring: cubic-bezier(0.16, 1, 0.3, 1);
  --apple-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
  --apple-blur: blur(20px) saturate(180%);
  --apple-accent: #3b82f6;
  --apple-accent-soft: rgba(59, 130, 246, 0.12);
  --apple-success: #10b981;
}

/* Translucent Ambient Hero */
.apple-menu-hero {
  position: relative;
  background: radial-gradient(120% 140% at 100% 0%, #172439 0%, #0d1522 100%);
  border-radius: 20px;
  padding: 22px 26px;
  color: #fff;
  margin-bottom: 22px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 16px 36px rgba(8, 16, 29, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.18);
  overflow: hidden;
}
.apple-menu-hero::after {
  content: '';
  position: absolute;
  top: -40%;
  left: -10%;
  width: 300px;
  height: 300px;
  background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.hero-inner-content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}
.hero-title-group h1 {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 6px 0;
  color: #ffffff;
  letter-spacing: -0.02em;
  line-height: 1.25;
}
.hero-title-group p {
  font-size: 13px;
  color: #a3b8d4;
  margin: 0;
  max-width: 600px;
  line-height: 1.6;
}
.hero-actions-cluster {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

/* Apple Dynamic Status Capsule (Dynamic Island feel) */
.apple-status-capsule {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: var(--apple-blur);
  -webkit-backdrop-filter: var(--apple-blur);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 9999px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  color: #e2e8f0;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.15);
  transition: transform 0.15s var(--apple-spring), background 0.2s ease, border-color 0.2s ease;
}
.apple-status-capsule.is-syncing {
  background: rgba(59, 130, 246, 0.28);
  border-color: rgba(59, 130, 246, 0.5);
  color: #ffffff;
}
.apple-status-capsule.is-saved {
  background: rgba(16, 185, 129, 0.2);
  border-color: rgba(16, 185, 129, 0.4);
  color: #ecfdf5;
}
.apple-pulse-dot {
  width: 7.5px;
  height: 7.5px;
  border-radius: 50%;
  background: var(--apple-success);
  box-shadow: 0 0 0 2.5px rgba(16, 185, 129, 0.25);
  transition: background-color 0.2s ease, transform 0.2s var(--apple-spring);
}
.apple-status-capsule.is-syncing .apple-pulse-dot {
  background: #60a5fa;
  box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.35);
  animation: apple-breath 0.9s var(--apple-spring) infinite alternate;
}
@keyframes apple-breath {
  from { transform: scale(0.85); opacity: 0.7; }
  to { transform: scale(1.25); opacity: 1; }
}

.btn-undo-action {
  background: rgba(255, 255, 255, 0.18);
  border: none;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 9px;
  border-radius: 9999px;
  cursor: pointer;
  display: none;
  align-items: center;
  gap: 4px;
  transition: transform 0.1s ease, background 0.15s ease;
}
.btn-undo-action:active {
  transform: scale(0.92);
}

/* Apple Glass Button */
.apple-glass-btn {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff !important;
  border: 1px solid rgba(255, 255, 255, 0.2);
  padding: 8px 16px;
  border-radius: 12px;
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  transition: transform 0.15s var(--apple-spring), background 0.18s ease, box-shadow 0.18s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.apple-glass-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.16);
}
.apple-glass-btn:active {
  transform: scale(0.96) translateY(0);
  transition: transform 80ms ease-out;
}

/* Apple Metric Capsules */
.apple-metrics-strip {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.metric-bubble {
  background: rgba(255, 255, 255, 0.045);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  padding: 8px 14px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 12.5px;
  color: #cbd5e1;
}
.metric-bubble strong {
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 700;
}

/* Apple Panel Cards */
.apple-panel-card {
  background: rgba(21, 31, 48, 0.85);
  backdrop-filter: var(--apple-blur);
  -webkit-backdrop-filter: var(--apple-blur);
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 18px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: box-shadow 0.25s ease;
}
.apple-panel-header {
  padding: 16px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  background: rgba(255, 255, 255, 0.03);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.apple-panel-title {
  font-size: 14.5px;
  font-weight: 700;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 9px;
}
.apple-title-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--apple-accent-soft);
  color: var(--apple-accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.apple-title-badge {
  background: rgba(255, 255, 255, 0.1);
  color: #e2e8f0;
  border-radius: 9999px;
  padding: 2px 9px;
  font-size: 11px;
  font-weight: 700;
}
.apple-panel-body {
  padding: 16px 18px;
  flex: 1;
}
.apple-panel-footer {
  padding: 12px 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(0, 0, 0, 0.15);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

/* Apple Buttons */
.btn-apple-primary {
  background: var(--apple-accent);
  color: #ffffff !important;
  border: none;
  border-radius: 10px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  transition: transform 0.12s var(--apple-spring), background-color 0.18s ease, box-shadow 0.18s ease;
}
.btn-apple-primary:hover {
  background: #2563eb;
  box-shadow: 0 6px 16px rgba(59, 130, 246, 0.45);
}
.btn-apple-primary:active {
  transform: scale(0.94);
  transition: transform 60ms ease-out;
}
.btn-apple-primary:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  pointer-events: none;
  box-shadow: none;
}

.btn-apple-ghost {
  background: rgba(255, 255, 255, 0.06);
  color: #cbd5e1;
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 9px;
  padding: 5px 11px;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.12s var(--apple-spring), background 0.15s ease, color 0.15s ease;
}
.btn-apple-ghost:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
}
.btn-apple-ghost:active {
  transform: scale(0.93);
}

.apple-hint-text {
  font-size: 12px;
  color: #94a3b8;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
  line-height: 1.5;
}

/* Apple Segmented Bar */
.apple-segmented-bar-wrapper {
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 4px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 4px;
  overflow-x: auto;
  scrollbar-width: none;
}
.apple-segmented-bar-wrapper::-webkit-scrollbar {
  display: none;
}
.apple-segment-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 14px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  text-decoration: none;
  white-space: nowrap;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: transform 0.12s var(--apple-spring), color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
}
.apple-segment-pill:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.06);
}
.apple-segment-pill:active {
  transform: scale(0.96);
}
.apple-segment-pill.is-active {
  background: var(--apple-accent);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
}
.apple-segment-count {
  font-size: 10.5px;
  background: rgba(0, 0, 0, 0.25);
  color: inherit;
  border-radius: 9999px;
  padding: 1px 7px;
  font-weight: 700;
}
.apple-segment-pill.is-active .apple-segment-count {
  background: rgba(255, 255, 255, 0.25);
}

/* Sortable List & Items */
.apple-sortable-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
}
.apple-sort-item {
  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 13px;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  user-select: none;
  position: relative;
  will-change: transform, box-shadow;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
  transition: transform 0.22s var(--apple-spring),
              box-shadow 0.22s var(--apple-spring),
              border-color 0.18s ease,
              background-color 0.18s ease,
              opacity 0.2s ease;
}
.apple-sort-item:hover {
  border-color: rgba(59, 130, 246, 0.4);
  background: rgba(255, 255, 255, 0.065);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
  transform: translateY(-1px);
}
.apple-sort-item.is-selected-item {
  border-color: var(--apple-accent);
  background: rgba(59, 130, 246, 0.12);
}
.apple-sort-item.is-dragging {
  opacity: 0.35;
  background: rgba(59, 130, 246, 0.15) !important;
  border: 1.5px dashed var(--apple-accent) !important;
  transform: scale(0.985);
  box-shadow: none !important;
}
.apple-sort-item.drag-over-top {
  border-top: 2.5px solid var(--apple-accent) !important;
  transform: translateY(2px);
}
.apple-sort-item.drag-over-bottom {
  border-bottom: 2.5px solid var(--apple-accent) !important;
  transform: translateY(-2px);
}

/* Apple 6-dot drag handle */
.apple-drag-handle {
  cursor: grab;
  color: #64748b;
  padding: 6px 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: transform 0.12s var(--apple-spring), background-color 0.15s ease, color 0.15s ease;
}
.apple-drag-handle:hover,
.apple-sort-item:hover .apple-drag-handle {
  color: #e2e8f0;
  background: rgba(255, 255, 255, 0.08);
}
.apple-drag-handle:active {
  cursor: grabbing;
  transform: scale(0.9);
}

/* Index Badge with Spring Pop */
.apple-index-badge {
  width: 25px;
  height: 25px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
  color: #e2e8f0;
  font-weight: 700;
  font-size: 11.5px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  transition: transform 0.22s var(--apple-bounce), background-color 0.2s ease, color 0.2s ease;
}
.apple-sort-item:hover .apple-index-badge {
  background: var(--apple-accent-soft);
  color: var(--apple-accent);
}

/* Apple Squircle Thumbnail */
.apple-product-thumb {
  width: 44px;
  height: 44px;
  border-radius: 11px;
  background: #0d131e;
  border: 1px solid rgba(255, 255, 255, 0.1);
  display: grid;
  place-items: center;
  overflow: hidden;
  flex-shrink: 0;
  transition: transform 0.18s var(--apple-spring);
}
.apple-sort-item:hover .apple-product-thumb {
  transform: scale(1.05);
}
.apple-product-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Item info & typography */
.apple-item-info {
  flex: 1;
  min-width: 0;
}
.apple-item-title {
  font-size: 13.5px;
  font-weight: 600;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 7px;
  flex-wrap: wrap;
  line-height: 1.35;
}
.category-name-link {
  color: #ffffff;
  text-decoration: none;
  transition: color 0.15s ease;
}
.category-name-link:hover {
  color: var(--apple-accent);
}
.product-title-text {
  color: #ffffff;
  font-weight: 600;
}
.apple-item-meta {
  font-size: 11.5px;
  color: #94a3b8;
  margin-top: 2px;
}

/* Pills & Tags */
.apple-tag-pill {
  font-size: 10.5px;
  border-radius: 6px;
  padding: 2px 7px;
  font-weight: 700;
}
.apple-tag-pill.tag-active {
  background: rgba(59, 130, 246, 0.2);
  color: #93c5fd;
}
.apple-tag-pill.tag-badge {
  background: rgba(245, 158, 11, 0.2);
  color: #fcd34d;
}
.apple-tag-pill.tag-danger {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}

/* Tactile steppers */
.apple-item-controls {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-shrink: 0;
}
.btn-apple-stepper {
  width: 29px;
  height: 29px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  color: #cbd5e1;
  cursor: pointer;
  display: grid;
  place-items: center;
  padding: 0;
  transition: transform 0.1s var(--apple-spring),
              background-color 0.15s ease,
              color 0.15s ease,
              border-color 0.15s ease;
}
.btn-apple-stepper:hover {
  background: var(--apple-accent);
  color: #ffffff;
  border-color: var(--apple-accent);
}
.btn-apple-stepper:active {
  transform: scale(0.86);
  transition: transform 60ms ease-out;
}
.btn-apple-stepper:disabled {
  opacity: 0.28;
  cursor: not-allowed;
  pointer-events: none;
}

.btn-apple-quick-edit {
  width: 29px;
  height: 29px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.05);
  display: grid;
  place-items: center;
  text-decoration: none;
  color: #cbd5e1;
  transition: transform 0.1s var(--apple-spring),
              background-color 0.15s ease,
              border-color 0.15s ease;
}
.btn-apple-quick-edit:hover {
  background: rgba(59, 130, 246, 0.2);
  color: var(--apple-accent);
  border-color: rgba(59, 130, 246, 0.4);
}
.btn-apple-quick-edit:active {
  transform: scale(0.88);
}

.btn-apple-cat-select {
  font-size: 11.5px;
  font-weight: 600;
  padding: 5px 11px;
  border-radius: 8px;
  text-decoration: none;
  color: var(--apple-accent);
  background: var(--apple-accent-soft);
  border: 1px solid rgba(59, 130, 246, 0.25);
  white-space: nowrap;
  transition: transform 0.12s var(--apple-spring), background-color 0.15s ease, color 0.15s ease;
}
.btn-apple-cat-select:hover, .btn-apple-cat-select.is-active-btn {
  background: var(--apple-accent);
  color: #ffffff;
  border-color: var(--apple-accent);
}
.btn-apple-cat-select:active {
  transform: scale(0.94);
}

/* Empty State */
.apple-empty-state {
  border: 2px dashed rgba(255, 255, 255, 0.12);
  border-radius: 16px;
  padding: 42px 20px;
  text-align: center;
  background: rgba(255, 255, 255, 0.02);
}
.apple-empty-state .empty-icon {
  font-size: 38px;
  margin-bottom: 10px;
}
.apple-empty-state h6 {
  color: #ffffff;
  font-weight: 700;
  margin-bottom: 4px;
}
.apple-empty-state p {
  color: #94a3b8;
  font-size: 12.5px;
  margin-bottom: 12px;
}

.apple-footer-tip {
  color: #94a3b8;
  font-size: 11.5px;
  display: flex;
  align-items: center;
  gap: 6px;
}

@media (max-width: 991px) {
  .hero-inner-content {
    flex-direction: column;
    align-items: flex-start;
  }
}
@media (prefers-reduced-motion: reduce) {
  .apple-sort-item,
  .btn-apple-stepper,
  .apple-index-badge,
  .apple-segment-pill {
    transition: none !important;
    animation: none !important;
    transform: none !important;
  }
}
</style>

<script>
(function() {
  const CSRF_TOKEN = '<?= $csrfToken ?>';

  // Global Status Capsule & Undo State
  const statusCapsule = document.getElementById('appleGlobalStatus');
  const statusText = document.getElementById('appleStatusText');
  const btnUndo = document.getElementById('btnUndoAction');
  let undoStack = null;

  function updateStatus(state, message) {
    if (!statusCapsule) return;
    statusCapsule.classList.remove('is-syncing', 'is-saved');
    if (state === 'syncing') {
      statusCapsule.classList.add('is-syncing');
      statusText.textContent = message || 'در حال همگام‌سازی چیدمان...';
    } else if (state === 'saved') {
      statusCapsule.classList.add('is-saved');
      statusText.textContent = message || 'چیدمان با موفقیت ذخیره شد ✓';
      setTimeout(() => {
        if (!statusCapsule.classList.contains('is-syncing')) {
          statusCapsule.classList.remove('is-saved');
          statusText.textContent = 'منو کاملاً همگام است';
        }
      }, 3000);
    } else {
      statusText.textContent = message || 'منو کاملاً همگام است';
    }
  }

  function showNotification(message, type = 'success') {
    const region = document.querySelector('.toast-region');
    if (region) {
      const item = document.createElement('div');
      item.className = 'toast toast-' + type;
      item.innerHTML = '<span>' + (type === 'success' ? '✓' : '!') + '</span><p>' + message + '</p><button aria-label="بستن">×</button>';
      region.append(item);
      requestAnimationFrame(() => item.classList.add('show'));
      const remove = () => { item.classList.remove('show'); setTimeout(() => item.remove(), 220); };
      item.querySelector('button').onclick = remove;
      setTimeout(remove, 3500);
    }
  }

  function updateOrderBadges(container) {
    const badges = container.querySelectorAll('.apple-sort-item .apple-index-badge');
    badges.forEach((b, idx) => {
      const nextNum = String(idx + 1);
      if (b.textContent !== nextNum) {
        b.textContent = nextNum;
        b.style.transform = 'scale(1.22)';
        setTimeout(() => { b.style.transform = ''; }, 180);
      }
    });

    // Update disabled stepper states
    const items = container.querySelectorAll('.apple-sort-item');
    items.forEach((item, idx) => {
      const up = item.querySelector('.move-up');
      const down = item.querySelector('.move-down');
      if (up) up.disabled = idx === 0;
      if (down) down.disabled = idx === items.length - 1;
    });
  }

  // FLIP animation helper for smooth swapping transitions
  function animateFlip(container, mutateDomFn) {
    const items = Array.from(container.querySelectorAll('.apple-sort-item'));
    const firstPositions = new Map();
    items.forEach(el => {
      firstPositions.set(el, el.getBoundingClientRect());
    });

    mutateDomFn();

    items.forEach(el => {
      const first = firstPositions.get(el);
      const last = el.getBoundingClientRect();
      if (first) {
        const deltaY = first.top - last.top;
        if (Math.abs(deltaY) > 1) {
          el.style.transform = `translateY(${deltaY}px)`;
          el.style.transition = 'none';
          requestAnimationFrame(() => {
            el.style.transition = 'transform 0.26s cubic-bezier(0.16, 1, 0.3, 1)';
            el.style.transform = '';
          });
        }
      }
    });
  }

  function getContainerOrder(container) {
    const items = container.querySelectorAll('.apple-sort-item');
    return Array.from(items).map(el => parseInt(el.getAttribute('data-id'), 10)).filter(n => !isNaN(n));
  }

  async function saveCategoryOrder(order, statusEl, enableUndo = true) {
    updateStatus('syncing', 'در حال ذخیره ترتیب دسته‌ها...');
    if (statusEl) statusEl.textContent = 'در حال ذخیره‌سازی...';
    try {
      const targetUrl = window.location.href;
      const res = await fetch(targetUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
          action: 'reorder_categories',
          order: order,
          csrf_token: CSRF_TOKEN
        })
      });
      const rawText = await res.text();
      let data = null;
      try {
        data = JSON.parse(rawText);
      } catch (parseErr) {
        console.error('Invalid server response:', rawText);
        throw new Error('پاسخ سرور در قالب نامعتبر دریافت شد.');
      }
      if (res.ok && data && data.success) {
        updateStatus('saved', 'ترتیب دسته‌ها ذخیره شد ✓');
        if (statusEl) statusEl.textContent = 'ترتیب دسته‌ها ذخیره شد ✓';
        showNotification(data.message || 'ترتیب دسته‌بندی‌ها با موفقیت ذخیره شد.');
      } else {
        const msg = (data && data.message) ? data.message : 'خطا در ذخیره ترتیب دسته‌ها';
        updateStatus('idle', 'خطا در ذخیره');
        if (statusEl) statusEl.textContent = 'خطا در ذخیره';
        showNotification(msg, 'danger');
      }
    } catch (e) {
      console.error('Save categories error:', e);
      updateStatus('idle', 'خطای شبکه');
      if (statusEl) statusEl.textContent = 'خطای ارتباط با سرور';
      showNotification('خطای شبکه یا ارتباط با سرور رخ داد.', 'danger');
    }
  }

  async function saveProductOrder(categoryId, order, statusEl, enableUndo = true) {
    updateStatus('syncing', 'در حال ذخیره ترتیب محصولات...');
    if (statusEl) statusEl.textContent = 'در حال ذخیره‌سازی...';
    try {
      const targetUrl = window.location.href;
      const res = await fetch(targetUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
          action: 'reorder_products',
          category_id: categoryId,
          order: order,
          csrf_token: CSRF_TOKEN
        })
      });
      const rawText = await res.text();
      let data = null;
      try {
        data = JSON.parse(rawText);
      } catch (parseErr) {
        console.error('Invalid server response:', rawText);
        throw new Error('پاسخ سرور در قالب نامعتبر دریافت شد.');
      }
      if (res.ok && data && data.success) {
        updateStatus('saved', 'ترتیب محصولات ذخیره شد ✓');
        if (statusEl) statusEl.textContent = 'ترتیب محصولات ذخیره شد ✓';
        showNotification(data.message || 'ترتیب محصولات با موفقیت ذخیره شد.');
      } else {
        const msg = (data && data.message) ? data.message : 'خطا در ذخیره ترتیب محصولات';
        updateStatus('idle', 'خطا در ذخیره');
        if (statusEl) statusEl.textContent = 'خطا در ذخیره';
        showNotification(msg, 'danger');
      }
    } catch (e) {
      console.error('Save products error:', e);
      updateStatus('idle', 'خطای شبکه');
      if (statusEl) statusEl.textContent = 'خطای ارتباط با سرور';
      showNotification('خطای شبکه یا ارتباط با سرور رخ داد.', 'danger');
    }
  }

  function setupSortable(container, onSave) {
    if (!container) return;

    let draggedItem = null;

    container.addEventListener('dragstart', (e) => {
      const item = e.target.closest('.apple-sort-item');
      if (!item) return;
      draggedItem = item;
      item.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', item.dataset.id || '');
    });

    container.addEventListener('dragend', (e) => {
      if (draggedItem) {
        draggedItem.classList.remove('is-dragging');
        draggedItem = null;
      }
      container.querySelectorAll('.apple-sort-item').forEach(el => {
        el.classList.remove('drag-over-top', 'drag-over-bottom');
      });
    });

    container.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      const target = e.target.closest('.apple-sort-item');
      if (!target || target === draggedItem) return;

      const rect = target.getBoundingClientRect();
      const midY = rect.top + rect.height / 2;
      container.querySelectorAll('.apple-sort-item').forEach(el => el.classList.remove('drag-over-top', 'drag-over-bottom'));
      if (e.clientY < midY) {
        target.classList.add('drag-over-top');
      } else {
        target.classList.add('drag-over-bottom');
      }
    });

    container.addEventListener('drop', (e) => {
      e.preventDefault();
      const target = e.target.closest('.apple-sort-item');
      if (!target || target === draggedItem || !draggedItem) return;

      const rect = target.getBoundingClientRect();
      const midY = rect.top + rect.height / 2;

      // Save previous order for Undo
      const prevOrder = getContainerOrder(container);

      target.classList.remove('drag-over-top', 'drag-over-bottom');
      if (e.clientY < midY) {
        container.insertBefore(draggedItem, target);
      } else {
        container.insertBefore(draggedItem, target.nextSibling);
      }

      updateOrderBadges(container);
      const newOrder = getContainerOrder(container);

      // Register undo action
      registerUndoAction(container, prevOrder, onSave);
      onSave(newOrder);
    });

    // Stepper buttons (up / down) with FLIP animation
    container.addEventListener('click', (e) => {
      const upBtn = e.target.closest('.move-up');
      const downBtn = e.target.closest('.move-down');

      if (upBtn) {
        const item = upBtn.closest('.apple-sort-item');
        const prev = item.previousElementSibling;
        if (prev && prev.classList.contains('apple-sort-item')) {
          const prevOrder = getContainerOrder(container);
          animateFlip(container, () => {
            container.insertBefore(item, prev);
            updateOrderBadges(container);
          });
          registerUndoAction(container, prevOrder, onSave);
          onSave(getContainerOrder(container));
        }
      } else if (downBtn) {
        const item = downBtn.closest('.apple-sort-item');
        const next = item.nextElementSibling;
        if (next && next.classList.contains('apple-sort-item')) {
          const prevOrder = getContainerOrder(container);
          animateFlip(container, () => {
            container.insertBefore(next, item);
            updateOrderBadges(container);
          });
          registerUndoAction(container, prevOrder, onSave);
          onSave(getContainerOrder(container));
        }
      }
    });
  }

  function registerUndoAction(container, previousOrder, onSaveFn) {
    if (!btnUndo) return;
    undoStack = {
      container,
      order: previousOrder,
      onSave: onSaveFn
    };
    btnUndo.style.display = 'inline-flex';
  }

  if (btnUndo) {
    btnUndo.addEventListener('click', () => {
      if (!undoStack) return;
      const { container, order, onSave } = undoStack;
      animateFlip(container, () => {
        order.forEach(id => {
          const item = container.querySelector(`.apple-sort-item[data-id="${id}"]`);
          if (item) container.appendChild(item);
        });
        updateOrderBadges(container);
      });
      onSave(order);
      btnUndo.style.display = 'none';
      undoStack = null;
      showNotification('تغییرات به حالت قبلی بازگردانده شد.');
    });
  }

  // Setup Categories Sortable
  const catContainer = document.getElementById('categorySortableList');
  const catStatus = document.getElementById('catStatus');
  const btnSaveCategories = document.getElementById('btnSaveCategoriesManual');
  const btnSortCatAlpha = document.getElementById('btnSortCatAlpha');

  if (catContainer) {
    setupSortable(catContainer, (order) => {
      saveCategoryOrder(order, catStatus);
    });

    if (btnSaveCategories) {
      btnSaveCategories.addEventListener('click', () => {
        const order = getContainerOrder(catContainer);
        saveCategoryOrder(order, catStatus);
      });
    }

    if (btnSortCatAlpha) {
      btnSortCatAlpha.addEventListener('click', () => {
        const prevOrder = getContainerOrder(catContainer);
        const items = Array.from(catContainer.querySelectorAll('.apple-sort-item'));
        animateFlip(catContainer, () => {
          items.sort((a, b) => {
            const nameA = a.getAttribute('data-name') || '';
            const nameB = b.getAttribute('data-name') || '';
            return nameA.localeCompare(nameB, 'fa');
          });
          items.forEach(el => catContainer.appendChild(el));
          updateOrderBadges(catContainer);
        });
        registerUndoAction(catContainer, prevOrder, (ord) => saveCategoryOrder(ord, catStatus));
        saveCategoryOrder(getContainerOrder(catContainer), catStatus);
      });
    }
  }

  // Setup Products Sortable
  const prodContainer = document.getElementById('productSortableList');
  const prodStatus = document.getElementById('prodStatus');
  const btnSaveProducts = document.getElementById('btnSaveProductsManual');
  const btnSortProdPrice = document.getElementById('btnSortProdPrice');

  if (prodContainer) {
    const categoryId = parseInt(prodContainer.getAttribute('data-category-id'), 10);
    setupSortable(prodContainer, (order) => {
      saveProductOrder(categoryId, order, prodStatus);
    });

    if (btnSaveProducts) {
      btnSaveProducts.addEventListener('click', () => {
        const order = getContainerOrder(prodContainer);
        saveProductOrder(categoryId, order, prodStatus);
      });
    }

    if (btnSortProdPrice) {
      let priceAsc = true;
      btnSortProdPrice.addEventListener('click', () => {
        const prevOrder = getContainerOrder(prodContainer);
        const items = Array.from(prodContainer.querySelectorAll('.apple-sort-item'));
        animateFlip(prodContainer, () => {
          items.sort((a, b) => {
            const pA = parseFloat(a.getAttribute('data-price')) || 0;
            const pB = parseFloat(b.getAttribute('data-price')) || 0;
            return priceAsc ? pA - pB : pB - pA;
          });
          items.forEach(el => prodContainer.appendChild(el));
          updateOrderBadges(prodContainer);
        });
        priceAsc = !priceAsc;
        btnSortProdPrice.textContent = priceAsc ? 'قیمت (کم به زیاد)' : 'قیمت (زیاد به کم)';
        registerUndoAction(prodContainer, prevOrder, (ord) => saveProductOrder(categoryId, ord, prodStatus));
        saveProductOrder(categoryId, getContainerOrder(prodContainer), prodStatus);
      });
    }
  }
})();
</script>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>