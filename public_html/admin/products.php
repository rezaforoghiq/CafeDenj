<?php
/**
 * admin/products.php
 * -----------------------------------------------------------------------
 * لیست محصولات با امکان جستجو، فیلتر بر اساس دسته، فعال/غیرفعال کردن
 * و حذف. لینک به add-product.php و edit-product.php هم همین‌جاست.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/Category.php';

requireLogin();
requirePermission('products.view');

$activePage = 'products';
$pageTitle  = 'محصولات';

// ---------------------------------------------------------------
// پردازش عملیات (حذف / تغییر وضعیت) — همیشه با POST + بررسی CSRF
// ---------------------------------------------------------------
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        $id = (int) $_POST['id'];
        $action = (string) ($_POST['action'] ?? '');

        // Mutating actions require stronger permission
        if (in_array($action, ['delete', 'toggle'], true)) {
            requirePermission('products.manage');
        }

        try {
            if ($action === 'delete') {
                Product::delete($id);
                $flashSuccess = 'محصول با موفقیت حذف شد.';
            } elseif ($action === 'toggle') {
                Product::toggleStatus($id);
                $flashSuccess = 'وضعیت محصول تغییر کرد.';
            }
        } catch (Throwable $e) {
            $flashError = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
            // var_dump($e->getMessage());
            // exit;
        }
    }
}

// ---------------------------------------------------------------
// فیلتر و جستجو
// ---------------------------------------------------------------
$search     = trim((string) ($_GET['search'] ?? ''));
$categoryId = (int) ($_GET['category_id'] ?? 0);

$products   = Product::all([
    'search'      => $search,
    'category_id' => $categoryId ?: null,
]);
$categories = Category::all();

require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="mb-0" style="color:var(--gold-soft);">مدیریت محصولات</h4>
  <a href="add-product" class="btn btn-gold btn-sm">+ افزودن محصول جدید</a>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-4">
  <div class="col-12 col-md-6">
    <input type="text" name="search" class="form-control" placeholder="جستجو در نام محصول..."
           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
  </div>
  <div class="col-8 col-md-4">
    <select name="category_id" class="form-select">
      <option value="0">همهٔ دسته‌ها</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-4 col-md-2">
    <button type="submit" class="btn btn-outline-light w-100" style="border-color:var(--line); color:var(--ivory);">فیلتر</button>
  </div>
</form>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>تصویر</th>
        <th>نام</th>
        <th>دسته</th>
        <th>قیمت</th>
        <th>تخفیف</th>
        <th>وضعیت</th>
        <th>عملیات</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($products)): ?>
        <tr><td colspan="7" class="text-center py-4" style="color:var(--muted);">محصولی پیدا نشد.</td></tr>
      <?php endif; ?>

      <?php foreach ($products as $product): ?>
        <?php $discountInfo = Product::calculateDiscount($product); ?>
        <tr>
          <td>
            <?php if ($product['image']): ?>
              <img src="<?= UPLOAD_DIR_URL . '/' . htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                   alt="" width="48" height="48" style="object-fit:cover; border-radius:8px;">
            <?php else: ?>
              <div style="width:48px;height:48px;border-radius:8px;background:var(--surface-2);"></div>
            <?php endif; ?>
          </td>
          <td>
            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
            <?php if ($product['badge']): ?>
              <span class="badge" style="background:var(--gold); color:#17130D;"><?= htmlspecialchars($product['badge'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </td>
          <td style="color:var(--muted);"><?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?php if ($discountInfo['has_discount']): ?>
              <div style="font-size:12px; text-decoration:line-through; color:var(--muted);"><?= number_format((float) $product['price']) ?> تومان</div>
              <div style="font-weight:700; color:var(--gold-soft);"><?= number_format((float) $discountInfo['final']) ?> تومان</div>
            <?php else: ?>
              <?= number_format((float) $product['price']) ?> تومان
            <?php endif; ?>
          </td>
          <td>
            <?php if ($discountInfo['has_discount']): ?>
              <span class="badge" style="background:#ef4444; color:#fff; font-size:12px;">
                <?= (int) $discountInfo['percent'] ?>٪ تخفیف
              </span>
            <?php elseif (!empty($product['discount_enabled'])): ?>
              <span class="badge bg-secondary" style="font-size:11px;" title="تخفیف خارج از بازه زمانی تعریف شده است">
                غیرفعال (بازه تاریخ)
              </span>
            <?php else: ?>
              <span style="color:var(--muted);">—</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $product['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $product['status'] === 'active' ? 'btn-success' : 'btn-secondary' ?>">
                <?= $product['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
              </button>
            </form>
          </td>
          <td>
            <a href="edit-product?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">ویرایش</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('از حذف این محصول مطمئن هستید؟');">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $product['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>