document.addEventListener("DOMContentLoaded", () => {
    // Add to Cart buttons on every deal (bindProductButtons is in JS/products.js)
    const page = document.querySelector(".deals-page");
    if (page) bindProductButtons(page);

    // Sort menu: apply as soon as a new order is picked (the Apply button is there for keyboard users too)
    const sortSelect = document.getElementById("dealSort");
    if (sortSelect) {
        sortSelect.addEventListener("change", () => sortSelect.form.submit());
    }

    // "Add bundle to cart": adds each product in the bundle to the session cart, one after another
    document.querySelectorAll(".bundle-card").forEach((card) => {
        const btn = card.querySelector(".add-bundle-btn");
        const message = card.querySelector(".bundle-message");
        const ids = card.dataset.ids.split(",");

        btn.addEventListener("click", async () => {
            btn.disabled = true;
            message.classList.remove("error");
            let added = 0;
            try {
                for (const id of ids) {
                    await cartRequest({ action: "add", product_id: id });   // JS/session.js
                    added++;
                }
                message.innerHTML = `Added all ${added} items to your cart. <a href="order.html">View cart</a>`;
            } catch (err) {
                message.classList.add("error");
                message.textContent = added
                    ? `Added ${added} of ${ids.length} items. ${err.message}`
                    : err.message;
            } finally {
                btn.disabled = false;
            }
        });
    });

    // Shell Shocker countdown: the deal runs until midnight on the visitor's clock
    const countdown = document.getElementById("countdown");
    if (!countdown) return;

    const pad = (n) => String(n).padStart(2, "0");

    const tick = () => {
        const now = new Date();
        const midnight = new Date(now);
        midnight.setHours(24, 0, 0, 0);
        const secondsLeft = Math.floor((midnight - now) / 1000);

        const hours = Math.floor(secondsLeft / 3600);
        const minutes = Math.floor((secondsLeft % 3600) / 60);
        countdown.textContent = `${pad(hours)}:${pad(minutes)}:${pad(secondsLeft % 60)}`;
    };

    tick();
    setInterval(tick, 1000);
});
