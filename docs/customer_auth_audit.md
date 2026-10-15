Here is the structured 10-section audit report:

### 1. Current Customer Auth Flow
- The authentication is heavily managed by the `CustomerAuth` and `OtpService` classes.
- Login and registration rely on an SMS OTP flow.
- A user's profile info (Name, Phone) is stored in the `customers` table, while authentication data (Password Hashes, Login Dates) is stored in the `customer_accounts` table.
- Logged-in state is maintained across the server using `$_SESSION['customer_id']` and `$_SESSION['customer_account_id']`.

### 2. Current Order Flow
- Browsing the menu is completely public.
- The `cart-action.php` file enforces `requireCustomerLogin()`, preventing non-authenticated users from adding anything to their cart.
- `cart.php` reads from the `carts` database table, filtering items exclusively by the session's `customer_id`.
- On `checkout.php`, users can review their cart, apply discount coupons, and add order notes (there is currently no field for table number).
- `Order::place()` takes the cart and directly binds the new order to the user's `customer_id` in the `orders` table.

### 3. Current Customer → Order Relationship
- **Tightly Coupled**: The database design strictly forbids carts or orders without a customer.
- The `carts.customer_id` column is a Foreign Key to `customers(id)` with `ON DELETE CASCADE`.
- The `orders.customer_id` column is a Foreign Key to `customers(id)` with `ON DELETE RESTRICT`.

### 4. Current Temporary/Persistent Order Access
- Access to orders is currently **persistent**, not temporary.
- Orders remain permanently attached to the user's account.
- `orders.php` shows complete history using `Order::mine()`.
- Access control in `order.php` and `receipt.php` works by strictly matching `$_SESSION['customer_id']` with the `orders.customer_id`.

### 5. Current Customer Data / Admin Customer Section
- The admin section (`admin/customers.php`) reads directly from the `customers` table.
- The current implementation of the `customers` table already includes a `source` column (used for popup events, etc.). This means the table is already architected to act as a CRM for capturing data *without* requiring an account.
- This perfectly supports your new flow: the `customers` table can stay exactly as-is to act purely as an optional CRM record.

### 6. Important Dependencies
- `ActivityLog.php`: System logs track customer actions (like order placement) utilizing their `customer_id`.
- Printing System (`receipt.php`): Verifies customer ownership for PDF/Receipt generation using the auth middleware.
- Middleware (`includes/customer-auth.php`): Extensively used (`requireCustomerLogin()`) across all public checkout and ordering pages.

### 7. Database Impact
- **Drop**: The `customer_accounts` and `otp_codes` tables can be safely dropped.
- **`carts` Table**: Drop the `customer_id` column and replace it with a `guest_token` (VARCHAR) or rely entirely on PHP `session_id()`. Drop the `fk_carts_customer` constraint.
- **`orders` Table**: 
  - Add a `table_number` (VARCHAR) column.
  - Add a `guest_token` (VARCHAR) column for temporary session matching.
  - Make `customer_id` **NULLABLE** so CRM linking becomes purely optional. Change the Foreign Key rule to `ON DELETE SET NULL`.

### 8. High/Medium/Low Risk Findings
- **High Risk**: If you rely solely on PHP sessions for temporary access, a short PHP GC (Garbage Collection) lifetime or a user refreshing their cache will instantly sever their access to the order status. You will need to carefully configure session timeouts (e.g., 2–4 hours) to match the "limited period" requirement.
- **High Risk**: Existing carts and orders in the database are hard-coded to `customer_id` and have constraints. Migrating the schema without breaking existing data will require carefully updating existing records (e.g., generating dummy guest tokens for old orders).
- **Medium Risk**: Deleting accounts means the `ActivityLog` will receive `null` for `user_id`. The log UI in the Admin panel must be double-checked to ensure it doesn't break when displaying guest actions.
- **Low Risk**: The `Coupon` class (`Coupon.php`) does not depend on `customer_id`. It functions universally and will naturally work with the new Guest flow without any modifications.

### 9. Recommended Migration Architecture
- **Complete Auth Removal**: Delete OTP services, profile pages, and the `customer_accounts` table.
- **Guest Identity**: On their first visit, assign the user a random `guest_token` in `$_SESSION`.
- **Cart/Checkout**: The Cart matches items against the `guest_token`. Checkout becomes public. Add a required `table_number` input, and optional CRM inputs (First Name, Last Name, Phone).
- **Order Placement**: Insert the order with `table_number` and `guest_token`. If CRM fields are filled, search or create a `customers` record, and link `orders.customer_id`. Otherwise, leave `customer_id` NULL.
- **Temporary Access**: `order.php` grants access *only* if the browser's `guest_token` matches the `orders.guest_token`. Once the session dies, the token dies, naturally fulfilling the "expires after a limited period" requirement.

### 10. Files / Modules That Would Likely Need Changes
- **Files to Delete**: `login.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `otp-verify.php`, `otp-resend.php`, `profile.php`, `includes/customer-auth.php`, `classes/CustomerAuth.php`, `classes/OtpService.php`.
- **Database (`database/cafe.sql`)**: Alter `carts` and `orders` schemas; drop legacy tables.
- **Backend (`classes/Order.php`, `classes/ActivityLog.php`)**: Swap `customer_id` arguments for guest session tokens and handle nullable CRM links.
- **Frontend / UI (`cart.php`, `checkout.php`, `order.php`, `orders.php`, `index.php`)**: Remove all auth checks and Login/Profile buttons. Introduce the table number field to the checkout UI. Update the order history page to list current-session orders only.