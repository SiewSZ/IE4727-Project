# Quantum Gear

An online shop for PC parts and consumer electronics, built for IE4727 Web Application Design (Theme 3: a web portal for online purchase of consumer electronics / IT products).

Customers browse products by category, search, compare deals, add items to a cart and place orders, then follow each order's status on the **My Orders** page and by email. Admins manage products, prices, sales, orders and user accounts from the **Admin** page.

## Setup

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this folder into `C:\xampp\htdocs\` (for example `C:\xampp\htdocs\ie4727\IE4727 Project`).
3. Open phpMyAdmin (`http://localhost/phpmyadmin`), choose **Import** with no database selected, and import `database/schema.sql`.
   It creates the `quantum_gear` database, all tables and 41 products. It is safe to import again after pulling changes: it only adds what is missing.
4. Open the site at `http://localhost/ie4727/IE4727%20Project/`.
5. **Make yourself an admin:** register on the Login page, then run this in phpMyAdmin:
   ```sql
   UPDATE quantum_gear.users SET role = 'admin' WHERE username = 'your_username';
   ```
   Sign out and in again, and an **Admin** link appears in the header.
6. **Order emails (optional):** start **Mercury** in the XAMPP Control Panel. Emails go to the local mailbox `newuser@localhost` (see `PHP/orders/mailer.php`). Without Mercury, ordering still works and every email is recorded in the `email_log` table.

Database settings are in `PHP/db_connect.php` (default XAMPP: user `root`, no password).

## Pages

| Page | File | What it does |
|---|---|---|
| Home | `index.php` | Carousel, category tiles, live product rows (Today's Deals, Best Sellers, New Arrivals, Top picks per category), buying guides |
| Shop | `shop.php` | Every product with price, category and brand filters, sorting and search |
| Product details | `product.php?id=…` | Key features, price and stock, add to cart / buy now, bestseller rank, "email me when it's back" on sold-out items, specs table, customer reviews, related products |
| PC Builder | `pc-builder.php` | Choose a part for each slot; PHP checks socket, memory type and power, then adds the whole build to the cart |
| Hot Deals | `hotdeals.php` | Daily Shell Shocker with countdown, Almost Gone, Clearance with filters, Bundles, email sign-up |
| Cart | `order.php` | Quantities, totals with discounts, delivery address, Place Order |
| My Orders | `my-orders.php` | A customer's orders with a status tracker, items table and totals |
| Contact Us | `contact.php` | Contact form, store details, Terms & Conditions and Privacy Policy |
| Login / Register | `login.html` | Sign in and create an account |
| Admin | `admin.php` | Inventory, sales, Orders, Users and Insights (admins only) |

That is the home page plus 9 content pages (the guidelines allow 4 to 10). `PHP/cookies/cookiecounter.php` is a separate cookie demo from development and is not linked from the site.

## How the project meets the Base Version requirements

| Requirement | Where |
|---|---|
| Home page + 4 to 10 content pages, each with text, images and a title | See the table above; every page has its own title that includes "Quantum Gear" |
| A table that displays content | Items table on **My Orders**, product details table on the **product page**, admin tables |
| A form with 4+ fields, processed on the server with the database | **Contact Us** (name, email, subject, message) → `PHP/contact/send.php` → `INSERT` into `contact_messages`. Also: the **PC Builder** (8 drop-downs, checked by PHP), register, sign in, product reviews, back-in-stock alerts |
| SQL SELECT, INSERT and UPDATE | SELECT on every page (including `SUM`/`GROUP BY` for best sellers); INSERT for accounts, messages, orders, reviews, stock alerts; UPDATE for stock, order status, prices, roles and alerts |
| A page generated on the server | `index.php`, `hotdeals.php`, `product.php`, `my-orders.php`, `pc-builder.php` are built by PHP from the database |
| HTML5 validation, JavaScript for what HTML5 can't check, PHP for everything | e.g. the contact form: HTML5 (`required`, `type=email`, lengths) → `JS/contact.js` (name pattern, full email domain, message not just spaces) → `PHP/contact/send.php` checks all of it again |
| External style sheet | `CSS/global.css` (shared) plus one file per page |
| Form actions run PHP (no `mailto:`), no frames or iframes, no templates, no external links to social sites | All forms post to PHP scripts; layout and styles are our own |

### No JSON or AJAX

The Base Version uses no jQuery, JSON or AJAX. Every page is built by PHP, and every change is a normal HTML form posted to a PHP script, which redirects back with a message (`setFlash()` / `flashMessage()` in `PHP/page.php`):

- **Header** on every page: who is signed in, the cart count and the search box are drawn by PHP (`PHP/page.php`). Search suggestions come from a `<datalist>` of product names.
- **Add to Cart**, cart quantities and **Buy Now** are forms posted to `PHP/shopping_cart/cart.php`; **Place Order** posts to `PHP/orders/place.php`.
- **Admin dashboard** (`admin.php`): every tab, chart and the sales report is built by PHP; each button is a form posted to a handler in `PHP/products`, `PHP/orders` or `PHP/users`.
- JavaScript only works inside the page: form validation, tabs, table filters, the carousel, the countdown and the **light / dark mode** switch (`JS/theme.js`, `CSS/light.css`, `CSS/dark.css`).

## Security

- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- Every database query uses prepared statements (no SQL built from user input).
- Everything shown from the database is escaped (`htmlspecialchars` in PHP), so no HTML or script can be injected.
- Admin endpoints check the role **in the database** on every request (`requireAdmin()` in `PHP/users/auth.php`), so a demoted or deleted account loses access straight away.
- Every change (admin actions, placing an order, reviews) needs the session's **CSRF token**, so other websites can't trigger them.
- 5 wrong passwords in a row lock an account for 5 minutes.
- Placing an order runs in one **transaction** with locked stock rows, so two customers can't buy the last unit.
- Signed-in users are signed out after 30 minutes without activity.

## Project structure

```
index.php, shop.php, product.php, ...   pages
CSS/        global.css (shared), one file per page, light.css / dark.css (themes)
JS/         one file per page, plus menu.js and theme.js (shared)
PHP/
  db_connect.php       database connection
  page.php             the header on every page (sign-in, cart count, search) and messages after a form
  users/               sign in, register, sign out, session, admin user management
  products/            save, delete, show/hide, sale, reviews, back-in-stock alerts, shared HTML helpers (render.php)
  shopping_cart/       session cart (cart_items.php), cart changes (cart.php), add a whole PC build (add_build.php)
  orders/              place order, order status, emails (order and back-in-stock)
  contact/             contact form and email sign-up
  reports/             sales report for the admin page
database/schema.sql    every table and the starting products
docs/                  test cases and site map for the report
```

## Credits

Product photos and the carousel banners are manufacturers' and retailers' product images (one banner carries a TechPowerUp watermark), used for this non-commercial university project only. Icons are from [Font Awesome](https://fontawesome.com/) (free icons, loaded from cdnjs).
