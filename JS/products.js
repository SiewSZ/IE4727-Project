// Product cards for shop.html (styles are in CSS/shop.css). The image and name link to product.php;
// PHP/products/render.php draws the same card for pages built on the server.
// Needs JS/session.js for cartRequest().

// Product text comes from the database, so escape it before putting it into HTML
const esc = (text) => String(text).replace(/[&<>"']/g, (ch) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch]);

const stars = (rating) =>
    Array.from({ length: 5 }, (_, i) =>
        `<i class="fa-${i < rating ? "solid" : "regular"} fa-star"></i>`).join("");

// Percentage off the old price, or 0 when the product is not on sale
const discountPercent = (p) =>
    p.oldPrice > p.price ? Math.round((1 - p.price / p.oldPrice) * 100) : 0;

const productCard = (p) => `
    <article class="product-card" data-id="${p.id}">
        <a class="product-image${p.image ? "" : " no-image"}" href="product.php?id=${p.id}">
            ${p.image
                ? `<img src="${esc(p.image)}" alt="${esc(p.name)}">`
                : `<i class="fa-solid ${esc(p.icon || "fa-box")}"></i>`}
            ${discountPercent(p) ? `<span class="sale-badge">-${discountPercent(p)}%</span>` : ""}
        </a>
        <div class="product-info">
            <p class="product-category">${esc(p.category)}</p>
            <h3 class="product-name" title="${esc(p.name)}"><a href="product.php?id=${p.id}">${esc(p.name)}</a></h3>
            <div class="product-rating">${stars(p.rating)}<span>${p.reviews} Reviews</span></div>
            <p class="product-stock">${p.stock > 0 ? `In Stock <span>${p.stock}</span>` : `<span class="out">Out of Stock</span>`}</p>
            <p class="product-price">
                ${p.price.toFixed(2)}
                ${p.oldPrice ? `<s>${p.oldPrice.toFixed(2)}</s>` : ""}
            </p>
            ${discountPercent(p) ? `<p class="deal-saving"><i class="fa-solid fa-tag"></i> You save ${(p.oldPrice - p.price).toFixed(2)}</p>` : ""}
            <button class="add-cart-btn" ${p.stock > 0 ? "" : "disabled"}>
                <i class="fa-solid fa-cart-shopping"></i> Add to Cart
            </button>
        </div>
    </article>`;

// Add to Cart saves the product in the session cart
const cartBtnLabel = `<i class="fa-solid fa-cart-shopping"></i> Add to Cart`;
const addToCart = async (btn) => {
    btn.disabled = true;
    try {
        await cartRequest({ action: "add", product_id: btn.closest("[data-id]").dataset.id });
        btn.innerHTML = `<i class="fa-solid fa-check"></i> Added`;
        setTimeout(() => {
            btn.innerHTML = cartBtnLabel;
            btn.disabled = false;
        }, 1200);
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
};

// One click listener on the container handles every card inside it, even cards added later
function bindProductButtons(container) {
    container.addEventListener("click", (e) => {
        const cartBtn = e.target.closest(".add-cart-btn");
        if (cartBtn) {
            addToCart(cartBtn);
        }
    });
}

// Loads the shop's products and categories from the database: { products, categories }
async function loadProducts() {
    const res = await fetch("PHP/products/list.php");
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}
