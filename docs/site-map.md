# Site Map

How the pages link together. The header (logo, search, Menu, Home, Shop, Contact Us, Hot Deals, cart, account links) and the footer (quick links, categories, Terms, Privacy) are on every page, so every page can reach Home, Shop, Contact Us, Hot Deals and the Cart directly.

```mermaid
flowchart TD
    Home["Home<br/>index.php"]
    Shop["Shop<br/>shop.html"]
    Product["Product details<br/>product.php?id="]
    Deals["Hot Deals<br/>hotdeals.php"]
    Cart["Cart<br/>order.html"]
    Orders["My Orders<br/>my-orders.php"]
    Contact["Contact Us + Terms & Privacy<br/>contact.html"]
    Login["Login / Register<br/>login.html"]
    Admin["Admin<br/>admin.html"]

    Home -->|category tiles, product rows, search| Shop
    Home -->|product rows| Product
    Home -->|deals tile, Today's Deals| Deals
    Home -->|buying guides, services| Contact
    Shop -->|product card| Product
    Deals -->|deal card, bundle item| Product
    Product -->|related products| Product
    Product -->|Add to Cart / Buy Now| Cart
    Shop -->|Add to Cart| Cart
    Deals -->|Add to Cart, Add bundle| Cart
    Cart -->|not signed in| Login
    Cart -->|order placed: Track this order| Orders
    Login -->|signed in| Home
    Orders -->|item link| Product
    Orders -->|cancelled: contact us| Contact
    Home -.->|signed-in admins only| Admin
    Admin -->|order status change emails customer| Orders
```

## Menu shortcuts (header "Menu" on every page)

| Menu item | Goes to |
|---|---|
| Shell Shocker, Clearance Deals, Email Deals | Hot Deals sections (`#shell-shocker`, `#clearance`, `#email-deals`) |
| Trade In, Server / NAS | Contact Us with the subject chosen |
| Desktop PC, Workstation, Home Network, Game Room | Shop filtered to the matching category |
| Each product category, Shop All | Shop filtered to that category / everything |
