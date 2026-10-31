# Test Cases

Tests run against the local XAMPP site on 1 Nov 2026. "Method" says how the test was done:

- **Browser**: by hand in the browser, or with Edge in headless mode clicking through the real page.
- **HTTP**: sending the request straight to the PHP script (with `curl`) and reading the reply, to check the server enforces a rule even when the page's JavaScript is bypassed.
- **Database**: reading the tables in MySQL before and after.

All tests passed. Test accounts and orders were deleted afterwards.

## Accounts and security

| # | Test | Input data | Expected output | Method | Result |
|---|---|---|---|---|---|
| A1 | Register a new account | Username, email and an 8+ character password | Account created, "Account created! Please sign in." | Browser | Pass |
| A2 | Sign in with a wrong password | Correct username, wrong password | "Incorrect username/email or password." | HTTP | Pass |
| A3 | Lock after repeated wrong passwords | 5 wrong passwords in a row | 5th attempt: "account is locked for 5 minutes"; correct password still refused while locked | HTTP + Database | Pass |
| A4 | Unlock after the lock expires | Correct password after the lock time | Signed in; failed count reset to 0 | HTTP + Database | Pass |
| A5 | Admin endpoints refuse other users | Request the order list / delete a product while signed out, or as a customer | "Only admins can do this." | HTTP | Pass |
| A6 | Requests without the CSRF token are refused | Admin action and Place Order with no token, then a wrong token | "Your session has expired. Please refresh the page and try again." | HTTP | Pass |
| A7 | Requests with the CSRF token work | Same admin action with the token from `me.php` | Action succeeds | HTTP + Browser (admin page buttons) | Pass |
| A8 | Password hashes never leave the server | Load the admin Users list | Reply contains no password data | HTTP | Pass |

## Admin: users

| # | Test | Input data | Expected output | Method | Result |
|---|---|---|---|---|---|
| U1 | Promote a customer to admin, then back | Make admin → Make customer | Role changes each time | HTTP + Browser | Pass |
| U2 | Can't change your own role | Admin tries to make themself a customer | "You can't change your own role." | HTTP | Pass |
| U3 | Can't delete your own account | Admin deletes themself | "You can't delete your own account while signed in to it." | HTTP | Pass |
| U4 | Demotion applies immediately | Admin B is demoted while signed in | B's next admin request is refused, without signing out | HTTP | Pass |
| U5 | Delete a user | Delete a customer account | Account removed from the database | HTTP + Database | Pass |
| U6 | Invalid role rejected | role = "owner" | "Role must be customer or admin." | HTTP | Pass |

## Admin: products and prices

| # | Test | Input data | Expected output | Method | Result |
|---|---|---|---|---|---|
| P1 | Add a product with an image and features | Name, brand, category, price, stock, JPG image, 2 feature lines | Saved; image stored in `Images/uploads/`; product page opens | HTTP + Browser | Pass |
| P2 | Delete a product | Delete the product from P1 | Row removed and its uploaded image deleted | HTTP + Database | Pass |
| P3 | Sale price is saved correctly | Regular 100, sale 75 | price 75.00, old_price 100.00 | HTTP + Database | Pass |
| P4 | Sale price must be lower than the regular price | Regular 50, sale 60 | "Sale price must be lower than the regular price" | HTTP | Pass |
| P5 | Run a Sale on a category | 10% off a category with one 75/100 item and one 200 item | 90.00 (replaces the old 75, doesn't stack) and 180.00 | HTTP + Database | Pass |
| P6 | End a sale | 0% on the same category | Regular prices restored, old_price cleared | HTTP + Database | Pass |
| P7 | Sale percentage limit | 95% | "Discount must be a whole number from 0 to 90." | HTTP | Pass |
| P8 | Hide and show a product | Click the eye button twice on the admin page | Hidden → Active; hidden products disappear from the shop | Browser | Pass |

## Shopping and orders

| # | Test | Input data | Expected output | Method | Result |
|---|---|---|---|---|---|
| O1 | Guest can't order | Cart with an item, not signed in, Place Order | "Please sign in to place your order." with a Sign in link | HTTP | Pass |
| O2 | Place an order | Signed in; 1 × RX 9060 XT + 2 × B550 motherboard | Order saved; total $887 after $40 of deals; cart emptied; stock 18→17 and 14→12 | HTTP + Database | Pass |
| O3 | Empty cart can't be ordered | Place Order again | "Your cart is empty." | HTTP | Pass |
| O4 | Confirmation shown in the cart | Place Order on the cart page | Button animates, cart empties, "Order #N is confirmed", cart badge 0 | Browser | Pass |
| O5 | Admin sees the order | Open the Orders tab | Order with items, address, total; Pending and Revenue cards updated | HTTP + Browser | Pass |
| O6 | Status update | Pending → Processing → Shipped | Status saved; customer's tracker moves to "On the way" | HTTP + Browser | Pass |
| O7 | Cancel returns stock | Cancel the order | Stock back to 18 and 14; status Cancelled | HTTP + Database | Pass |
| O8 | Cancelled order can't be reopened | Set a cancelled order back to Pending | "A cancelled order can't be reopened." | HTTP | Pass |
| O9 | Order emails | Place an order, then change its status (and set the same status again) | Confirmation email + one status email, all to `newuser@localhost`, logged in `email_log`; repeating a status sends nothing | HTTP + Database | Pass |
| O10 | My Orders shows only your own orders | Open My Orders as the buyer, as another account, and signed out | Buyer sees both orders; other account sees none; signed out sees "Sign in to see your orders" | HTTP + Browser | Pass |

## Reviews

| # | Test | Input data | Expected output | Method | Result |
|---|---|---|---|---|---|
| R1 | Review text must be real text | 4 stars, review "   short  " | "Please write at least 10 characters…" (form not sent) | Browser | Pass |
| R2 | Post a review | 4 stars and a full sentence, after buying the product | "Thank you! Your review has been saved."; average 4.0 (1 review); Verified purchase | Browser + Database | Pass |
| R3 | Product card stars follow the reviews | After R2 | products.rating = 4, products.reviews = 1 | Database | Pass |

## Contact and email sign-up

| # | Test | Input data | Expected output | Method | Result |
|---|---|---|---|---|---|
| C1 | Send a message | Name "Tan Ah Kow", valid email, subject Trade-in, a 30-character message | Redirect with "Thank you!"; row in `contact_messages` | HTTP + Database | Pass |
| C2 | Invalid name rejected | Name `<script>` | "Name can only use letters, spaces, apostrophes, dots and hyphens." | HTTP | Pass |
| C3 | Message too short | Message "hi" | "Your message must be between 10 and 2000 characters." | HTTP | Pass |
| C4 | Email sign-up, including a repeat | Same valid email twice, then "nope" | Thanks message twice (no duplicate row); "Please enter a valid email address." | HTTP + Database | Pass |

## Pages, search and themes

| # | Test | Input data | Expected output | Method | Result |
|---|---|---|---|---|---|
| G1 | Search suggestions | Type "AMD" in the header search | 4 AMD products with pictures and prices, "See all 4 results" | Browser | Pass |
| G2 | Menu category links | Open `shop.html?category=Gaming & VR` | That category ticked; 3 products shown | Browser | Pass |
| G3 | Missing product | `product.php?id=9999` | "Product not found" page with HTTP 404 | HTTP | Pass |
| G4 | Hot Deals filters | `hotdeals.php?category=Components & Storage&sort=price-asc` | Only that category, cheapest first | HTTP | Pass |
| G5 | Light / dark switch | Click the switch twice | Theme flips each time and is remembered on the next page | Browser | Pass |
| G6 | Text contrast | Main text and button colours in both themes | All text at least 4.5:1, icons at least 3:1 (WCAG AA) | Contrast calculation | Pass |
| G7 | Small screens | Pages at 520px wide | Header wraps onto two rows, menu becomes one scrolling column, nothing cut off | Browser | Pass |
