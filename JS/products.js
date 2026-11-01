// Add to Cart on the product cards that PHP/products/render.php draws (shop.php, hotdeals.php,
// related products on product.php). Needs JS/session.js for cartRequest().

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

// One click listener on the container handles every card inside it
function bindProductButtons(container) {
    container.addEventListener("click", (e) => {
        const cartBtn = e.target.closest(".add-cart-btn");
        if (cartBtn) {
            addToCart(cartBtn);
        }
    });
}
