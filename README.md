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
| Home | `index.php` | Carousel, category tiles, live product rows (Today's Deals, New Arrivals, Top picks per category), buying guides |
| Shop | `shop.html` | Every product with price, category and brand filters, sorting and search |
| Product details | `product.php?id=…` | Key features, price and stock, add to cart / buy now, specs table, customer reviews, related products |
| Hot Deals | `hotdeals.php` | Daily Shell Shocker with countdown, Almost Gone, Clearance with filters, Bundles, email sign-up |
| Cart | `order.html` | Quantities, totals with discounts, delivery address, Place Order |
| My Orders | `my-orders.php` | A customer's orders with a status tracker, items table and totals |
| Contact Us | `contact.html` | Contact form, store details, Terms & Conditions and Privacy Policy |
| Login / Register | `login.html` | Sign in and create an account |
| Admin | `admin.html` | Inventory, sales, Orders, Users and Insights (admins only) |

That is the home page plus 8 content pages (the guidelines allow 4 to 10).

## How the project meets the Base Version requirements

| Requirement | Where |
|---|---|
| Home page + 4 to 10 content pages, each with text, images and a title | See the table above; every page has its own title that includes "Quantum Gear" |
| A table that displays content | Items table on **My Orders**, product details table on the **product page**, admin tables |
| A form with 4+ fields, processed on the server with the database | **Contact Us** (name, email, subject, message) → `PHP/contact/send.php` → `INSERT` into `contact_messages`. Also: register, sign in, product reviews |
| SQL SELECT, INSERT and UPDATE | SELECT on every page; INSERT for accounts, messages, orders, reviews; UPDATE for stock, order status, prices and roles |
| A page generated on the server | `index.php`, `hotdeals.php`, `product.php`, `my-orders.php` are built by PHP from the database |
| HTML5 validation, JavaScript for what HTML5 can't check, PHP for everything | e.g. the contact form: HTML5 (`required`, `type=email`, lengths) → `JS/contact.js` (name pattern, full email domain, message not just spaces) → `PHP/contact/send.php` checks all of it again |
| External style sheet | `CSS/global.css` (shared) plus one file per page |
| Form actions run PHP (no `mailto:`), no frames or iframes, no templates, no external links to social sites | All forms post to PHP scripts; layout and styles are our own |

### Additional Version (modern enhancements)

These parts use JavaScript `fetch` with JSON replies, which goes beyond the Base Version stack, and are presented as our enhancements:

- **Shop filtering and search** (`JS/shop.js` → `PHP/products/list.php`): filters update instantly without reloading.
- **Session cart** (`JS/session.js`, `JS/order.js` → `PHP/shopping_cart/cart.php`) and **Place Order** (`PHP/orders/place.php`).
- **Header**: shows who is signed in and the cart count (`PHP/users/me.php`), and **live search suggestions** as you type (`JS/menu.js`).
- **Admin dashboard** (`JS/admin.js`): products, sales, orders, users and insights charts without page reloads.
- **Light / dark mode** switch (`JS/theme.js`, `CSS/light.css`, `CSS/dark.css`), remembered in the browser.

## Security

- Passwords are stored with `password_hash()` and checked with `password_verify()`.
- Every database query uses prepared statements (no SQL built from user input).
- Everything shown from the database is escaped (`htmlspecialchars` in PHP, `esc()` in JS), so no HTML or script can be injected.
- Admin endpoints check the role **in the database** on every request (`requireAdmin()` in `PHP/users/auth.php`), so a demoted or deleted account loses access straight away.
- Every change (admin actions, placing an order, reviews) needs the session's **CSRF token**, so other websites can't trigger them.
- 5 wrong passwords in a row lock an account for 5 minutes.
- Placing an order runs in one **transaction** with locked stock rows, so two customers can't buy the last unit.
- Signed-in users are signed out after 30 minutes without activity.

## Project structure

```
index.php, shop.html, product.php, ...   pages
CSS/        global.css (shared), one file per page, light.css / dark.css (themes)
JS/         one file per page, plus session.js, menu.js, theme.js, products.js (shared)
PHP/
  db_connect.php       database connection
  users/               sign in, register, sign out, session, admin user management
  products/            product list, save, delete, sale, reviews, shared HTML helpers (render.php)
  shopping_cart/       session cart
  orders/              place order, admin order list and status, emails
  contact/             contact form and email sign-up
database/schema.sql    every table and the starting products
docs/                  test cases and site map for the report
```

## Credits

Product photos and the carousel banners are manufacturers' and retailers' product images (one banner carries a TechPowerUp watermark), used for this non-commercial university project only. Icons are from [Font Awesome](https://fontawesome.com/) (free icons, loaded from cdnjs).
