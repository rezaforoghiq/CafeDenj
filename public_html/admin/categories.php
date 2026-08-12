<?php
/**
 * admin/categories.php
 * -----------------------------------------------------------------------
 * مدیریت دسته‌بندی‌ها: افزودن، ویرایش (inline از طریق ?edit=ID) و حذف.
 * این صفحه در ساختار اولیهٔ درخواست‌شده نبود، اما چون «مدیریت دسته‌بندی»
 * جزو امکانات خواسته‌شده بود، اضافه شد.
 * -----------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Category.php';

requireLogin();
requirePermission('products.manage');

$activePage = 'categories';
$pageTitle  = 'دسته‌بندی‌ها';

$errors       = [];
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = null;
unset($_SESSION['flash_success']);

$editId = (int) ($_GET['edit'] ?? 0);
$editingCategory = $editId > 0 ? Category::find($editId) : null;

$old = $editingCategory
    ? ['name' => $editingCategory['name'], 'slug' => $editingCategory['slug'], 'sort_order' => $editingCategory['sort_order']]
    : ['name' => '', 'slug' => '', 'sort_order' => 0];

// ---------------------------------------------------------------
// ثبت / ویرایش (POST با action=save)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['general'] = 'نشست شما منقضی شده است. صفحه را رفرش کرده و دوباره تلاش کنید.';
    } else {
        $saveId = (int) ($_POST['id'] ?? 0);

        $old = [
            'name'       => trim($_POST['name'] ?? ''),
            'slug'       => trim(strtolower($_POST['slug'] ?? '')),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];

        $errors = Category::validate($old, $saveId ?: null);

        if (empty($errors)) {
            if ($saveId > 0) {
                Category::update($saveId, $old);
                $_SESSION['flash_success'] = 'دسته‌بندی با موفقیت ویرایش شد.';
            } else {
                Category::create($old);
                $_SESSION['flash_success'] = 'دسته‌بندی جدید اضافه شد.';
            }

            header('Location: categories');
            exit;
        }

        // در صورت خطا، فرم را در حالت ویرایش/افزودن با همون مقادیر نگه دار
        $editId = $saveId;
        $editingCategory = $saveId > 0 ? ['id' => $saveId] : null;
    }
}

// ---------------------------------------------------------------
// حذف (POST با action=delete)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashError = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    } else {
        try {
            Category::delete((int) $_POST['id']);
            $flashSuccess = 'دسته‌بندی حذف شد.';
        } catch (RuntimeException $e) {
            $flashError = $e->getMessage();
        }
    }
}

$categories = Category::all();

require __DIR__ . '/../../includes/admin-header.php';
?>

<h4 class="mb-4" style="color:var(--gold-soft);">مدیریت دسته‌بندی‌ها</h4>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-danger py-2 px-3" style="font-size:13.5px;"><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- فرم افزودن/ویرایش -->
  <div class="col-12 col-lg-4">
    <div class="card p-3">
      <h6 class="mb-3" style="color:var(--ivory);"><?= $editingCategory ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید' ?></h6>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="save">
        <?php if ($editingCategory): ?>
          <input type="hidden" name="id" value="<?= (int) $editingCategory['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" style="font-size:13px;">نام دسته (فارسی)</label>
          <input type="text" name="name" class="form-control form-control-sm <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                 value="<?= htmlspecialchars((string) $old['name'], ENT_QUOTES, 'UTF-8') ?>" required>
          <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label" style="font-size:13px;">Slug (انگلیسی، برای فیلتر منو)</label>
          <input type="text" name="slug" class="form-control form-control-sm <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
                 placeholder="مثل: cold-drinks" value="<?= htmlspecialchars((string) $old['slug'], ENT_QUOTES, 'UTF-8') ?>" required>
          <?php if (isset($errors['slug'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['slug'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label" style="font-size:13px;">ترتیب نمایش</label>
          <input type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int) $old['sort_order'] ?>">
        </div>

        <button type="submit" class="btn btn-gold btn-sm w-100"><?= $editingCategory ? 'ذخیرهٔ تغییرات' : 'افزودن دسته' ?></button>
        <?php if ($editingCategory): ?>
          <a href="categories" class="btn btn-sm btn-outline-light w-100 mt-2" style="border-color:var(--line); color:var(--ivory);">انصراف از ویرایش</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- لیست دسته‌بندی‌ها -->
  <div class="col-12 col-lg-8">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>نام</th>
            <th>Slug</th>
            <th>ترتیب</th>
            <th>تعداد محصولات</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($categories)): ?>
            <tr><td colspan="5" class="text-center py-4" style="color:var(--muted);">هنوز دسته‌بندی‌ای ثبت نشده.</td></tr>
          <?php endif; ?>

          <?php foreach ($categories as $cat): ?>
            <tr>
              <td><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td style="color:var(--muted); direction:ltr; text-align:right;"><?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) $cat['sort_order'] ?></td>
              <td><?= (int) $cat['product_count'] ?></td>
              <td>
                <a href="categories?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-light" style="border-color:var(--line); color:var(--ivory);">ویرایش</a>
                <form method="POST" class="d-inline" onsubmit="return confirm('از حذف این دسته‌بندی مطمئن هستید؟');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" <?= $cat['product_count'] > 0 ? 'title="ابتدا محصولات این دسته را جابه‌جا یا حذف کنید"' : '' ?>>حذف</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
