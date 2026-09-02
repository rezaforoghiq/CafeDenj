# Print API: Product-Level Discount Data Integration Guide

This document describes the API and data model changes introduced to expose product-level discount information for the Windows Print Bridge thermal invoice printing application.

---

## 1. What Changed

The API endpoint:
```http
GET /api/print_bridge.php?action=next
```
now includes three additive fields inside each item object in `payload.items[]`:
1. `original_price` (float / integer, Tomans)
2. `discount_percent` (integer, 0–100)
3. `discount_amount` (float / integer, Tomans) — **Per-unit product discount**

These fields are populated reliably from persisted order records for both `preparation` jobs and `customer_invoice` jobs.

---

## 2. Payload Structure Comparison

### 2.1 Before
Each item in `payload.items[]` previously contained only:
```json
{
  "product_id": 4,
  "product_name": "اسپرسو دبل",
  "quantity": 2,
  "price": 42500,
  "line_total": 85000
}
```

### 2.2 After
Each item in `payload.items[]` now contains:
```json
{
  "product_id": 4,
  "product_name": "اسپرسو دبل",
  "quantity": 2,
  "original_price": 50000,
  "discount_percent": 15,
  "discount_amount": 7500,
  "price": 42500,
  "line_total": 85000
}
```

---

## 3. Field-by-Field Contract

| Field Name | Type | Unit | Description | Zero-Discount Behavior |
| :--- | :--- | :--- | :--- | :--- |
| `product_id` | `int` | ID | Unique product identifier in the catalog. | Unchanged |
| `product_name` | `string` | Text | Name of the product in Persian. | Unchanged |
| `quantity` | `int` | Count | Number of units purchased. | Minimum 1 |
| `original_price` | `float` | Tomans | Base catalog unit price before product discount. | Equal to `price` |
| `discount_percent`| `int` | % (0–100) | Product discount percentage applied to this item. | `0` |
| `discount_amount` | `float` | Tomans | Product discount amount for **ONE UNIT**. | `0` |
| `price` | `float` | Tomans | Final locked unit price after product discount. | Base unit price |
| `line_total` | `float` | Tomans | Total line price: `price * quantity` (in `customer_invoice`). | `price * quantity` |

---

## 4. Discount Semantics

### 4.1 Product-Level vs. Order-Level Discount
* **`payload.items[].discount_amount`**: The discount on a **single unit** of that product item.
  - Formula: `original_price - price = discount_amount` (per unit).
  - Total line discount for that product: `discount_amount * quantity`.
* **`payload.discount_amount`**: The **order-level coupon/discount** (from `orders.discount_amount`) applied to the order subtotal.

### 4.2 Mathematical Relationship
$$\text{line\_total} = \text{price} \times \text{quantity} = (\text{original\_price} - \text{discount\_amount}) \times \text{quantity}$$
$$\text{total\_amount} = \sum(\text{line\_total}) - \text{payload.discount\_amount}$$

---

## 5. Windows PrintBridge Integration Guide

When rendering invoices in the Windows C# / .NET application:

1. **Detect Product Discount**:
   - Check if `item.discount_percent > 0` or `item.discount_amount > 0`.
2. **Display Original Price and Strike-through / Note (if supported)**:
   - If discounted:
     - Show original unit price: `item.original_price.ToString("N0") + " تومان"`
     - Show discount percentage: `item.discount_percent + "%"`
     - Show final unit price: `item.price.ToString("N0") + " تومان"`
     - Line total: `item.line_total.ToString("N0") + " تومان"`
   - If not discounted (`discount_percent == 0`):
     - Show standard unit price: `item.price.ToString("N0") + " تومان"`
     - Line total: `item.line_total.ToString("N0") + " تومان"`
3. **Total Item Discounts Calculation**:
   - Total item discount across line: `item.discount_amount * item.quantity`
   - Total item discounts across entire invoice: `sum(item.discount_amount * item.quantity)`

---

## 6. Backward Compatibility

* The changes are strictly **additive**.
* All previous fields (`product_id`, `product_name`, `quantity`, `price`, `line_total`) retain their exact types, keys, and values.
* Existing clients or legacy Windows PrintBridge binaries that do not parse the new discount fields will continue to function without errors.

---

## 7. Database Changes & Migrations

### 7.1 Schema Changes
To ensure historical accuracy and prevent changes in current catalog pricing from altering past printed receipts, `order_items` stores the locked discount state per purchased item:
* `original_price` (`DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0`)
* `discount_percent` (`INT UNSIGNED NOT NULL DEFAULT 0`)
* `discount_amount` (`DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0`)

### 7.2 Files Updated
1. **Canonical Schema Dump**:
   - `database/cafe.sql`: Updated table definition for `order_items`.
2. **Incremental Migration File**:
   - `database/migration-10-order-items-discount.sql`:
   ```sql
   ALTER TABLE `order_items`
     ADD COLUMN `original_price` DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0 AFTER `quantity`,
     ADD COLUMN `discount_percent` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `original_price`,
     ADD COLUMN `discount_amount` DECIMAL(12,0) UNSIGNED NOT NULL DEFAULT 0 AFTER `discount_percent`;

   UPDATE `order_items`
     SET `original_price` = `price`
     WHERE `original_price` = 0;
   ```

### 7.3 How to Apply
* For **fresh installations**: Import `database/cafe.sql`.
* For **existing production instances**: Run `database/migration-10-order-items-discount.sql`.

---

## 8. Historical Limitations

* Orders placed prior to this migration did not record historical `original_price` and `discount_amount` in `order_items`.
* For those historical rows, `original_price` is safely set to `price`, `discount_percent` is `0`, and `discount_amount` is `0`.
* The system does not fabricate or guess retroactive discounts for past legacy orders, ensuring data integrity. All new orders placed after this update will record exact original prices and discount amounts at time of checkout.