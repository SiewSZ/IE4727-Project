document.addEventListener("DOMContentLoaded", () => {
    const itemsBox = document.getElementById("cartItems");
    if (!itemsBox) return;

    // The cart lives in the PHP session (PHP/shopping_cart/cart.php); cartRequest is in JS/session.js
    let cart = [];

    const addresses = [
        { label: "Home", address: "50 Nanyang Ave, Singapore 639798" },
        { label: "Office", address: "1 Fusionopolis Way, Singapore 138632" }
    ];
    let selectedAddress = 0;

    const money = (n) => `$${n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    // Product text comes from the database, so escape it before putting it into HTML
    const esc = (text) => String(text).replace(/[&<>"']/g, (ch) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch]);

    const subtotalEl = document.getElementById("summarySubtotal");
    const discountEl = document.getElementById("summaryDiscount");
    const totalEl = document.getElementById("summaryTotal");
    const checkoutBtn = document.getElementById("checkoutBtn");

    const cartItem = (item) => `
        <article class="cart-item" data-id="${item.id}">
            <div class="cart-item-image">
                ${item.image
                    ? `<img src="${esc(item.image)}" alt="${esc(item.name)}">`
                    : `<i class="fa-solid ${esc(item.icon || "fa-box")}"></i>`}
            </div>
            <div class="cart-item-details">
                <h3 class="cart-item-name">${esc(item.name)}</h3>
                <p class="cart-item-meta">Category: <strong>${esc(item.category)}</strong></p>
                ${item.oldPrice ? `<span class="status-badge sale">Sale</span>` : ""}
            </div>
            <div class="cart-item-unit">
                ${money(item.price)}
                ${item.oldPrice ? `<s>${money(item.oldPrice)}</s>` : ""}
            </div>
            <div class="qty-control">
                <button data-action="decrease" aria-label="Decrease quantity" ${item.qty <= 1 ? "disabled" : ""}><i class="fa-solid fa-minus"></i></button>
                <span>${item.qty}</span>
                <button data-action="increase" aria-label="Increase quantity" ${item.qty >= item.stock ? "disabled" : ""}><i class="fa-solid fa-plus"></i></button>
            </div>
            <p class="cart-item-price">${money(item.price * item.qty)}</p>
            <div class="cart-item-actions">
                <button class="icon-btn" data-action="remove" aria-label="Remove item"><i class="fa-regular fa-trash-can"></i></button>
            </div>
        </article>`;

    const cartHead = `
        <div class="cart-head">
            <span>Product</span>
            <span>Price</span>
            <span>Quantity</span>
            <span>Total</span>
            <span></span>
        </div>`;

    const renderCart = () => {
        itemsBox.innerHTML = cart.length
            ? cartHead + cart.map(cartItem).join("")
            : `<p class="cart-empty">Your cart is empty. <a href="shop.html">Continue shopping</a></p>`;

        // SubTotal uses the original price, Discount is the amount saved
        const subtotal = cart.reduce((sum, item) => sum + (item.oldPrice || item.price) * item.qty, 0);
        const total = cart.reduce((sum, item) => sum + item.price * item.qty, 0);

        subtotalEl.textContent = money(subtotal);
        discountEl.textContent = money(subtotal - total);
        totalEl.textContent = money(total);
        // Once the order is placed the button stays disabled on "Order Placed"
        if (!checkoutBtn.classList.contains("done")) {
            checkoutBtn.disabled = cart.length === 0;
        }
    };

    // Every change goes to the session cart; the page then shows the cart the server sends back
    itemsBox.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;

        const id = Number(btn.closest(".cart-item").dataset.id);
        const item = cart.find((i) => i.id === id);

        const changes = {
            increase: { action: "update", product_id: id, qty: item.qty + 1 },
            decrease: { action: "update", product_id: id, qty: item.qty - 1 },
            remove: { action: "remove", product_id: id }
        };

        btn.disabled = true;
        try {
            cart = (await cartRequest(changes[btn.dataset.action])).items;
            renderCart();
        } catch (err) {
            alert(err.message);
            btn.disabled = false;
        }
    });

    const loadCart = async () => {
        itemsBox.innerHTML = `<p class="cart-empty">Loading your cart...</p>`;
        try {
            cart = (await cartRequest()).items;
            renderCart();
        } catch (err) {
            console.error("Could not load the cart:", err);
            itemsBox.innerHTML = `<p class="cart-empty">Your cart could not be loaded. Check that Apache and MySQL are running.</p>`;
            checkoutBtn.disabled = true;
        }
    };

    // Delivery addresses
    const addressList = document.getElementById("addressList");
    const addressForm = document.getElementById("addressForm");
    const addAddressBtn = document.getElementById("addAddressBtn");
    const cancelAddressBtn = document.getElementById("cancelAddressBtn");

    const renderAddresses = () => {
        addressList.innerHTML = addresses.map((a, i) => `
            <label class="address-option">
                <input type="radio" name="address" value="${i}" ${i === selectedAddress ? "checked" : ""}>
                <span>
                    <strong>${a.label}</strong>
                    <small>${a.address}</small>
                </span>
            </label>`).join("");
    };

    addressList.addEventListener("change", (e) => {
        selectedAddress = Number(e.target.value);
    });

    const toggleAddressForm = (show) => {
        addressForm.hidden = !show;
        addAddressBtn.hidden = show;
        if (!show) addressForm.reset();
    };

    addAddressBtn.addEventListener("click", () => toggleAddressForm(true));
    cancelAddressBtn.addEventListener("click", () => toggleAddressForm(false));

    addressForm.addEventListener("submit", (e) => {
        e.preventDefault();
        const data = new FormData(addressForm);
        addresses.push({ label: data.get("label").trim(), address: data.get("address").trim() });
        selectedAddress = addresses.length - 1;
        renderAddresses();
        toggleAddressForm(false);
    });

    // Place Order: the server saves the order and empties the session cart (PHP/orders/place.php)
    // while the button animates (JS/order-button.js). The result is announced for screen readers too.
    const orderStatus = document.getElementById("orderStatus");
    const orderError = document.getElementById("orderError");

    const showOrderError = (message) => {
        orderError.innerHTML = message;
        orderError.hidden = false;
    };

    const showConfirmation = (result, address) => {
        itemsBox.innerHTML = `
            <div class="order-confirm">
                <i class="fa-solid fa-circle-check"></i>
                <h3>Thank you! Order #${result.orderId} is confirmed.</h3>
                <p>${result.itemCount} item${result.itemCount === 1 ? "" : "s"}, ${money(result.total)} in total, will be delivered to <strong>${esc(address.label)}</strong> (${esc(address.address)}).</p>
                <div class="order-confirm-actions">
                    <a href="my-orders.php#order-${result.orderId}" class="order-confirm-btn">Track this order</a>
                    <a href="shop.html" class="order-confirm-link">Continue shopping</a>
                </div>
                <p class="order-confirm-note">A confirmation email is on its way.</p>
            </div>`;
    };

    checkoutBtn.addEventListener("click", async () => {
        if (!cart.length) return;
        orderError.hidden = true;
        orderStatus.textContent = "Placing your order...";

        const address = addresses[selectedAddress];
        const body = new FormData();
        body.append("address_label", address.label);
        body.append("address", address.address);
        body.append("csrf_token", await getCsrfToken());   // JS/session.js

        const animation = animateOrderButton(checkoutBtn);
        try {
            const res = await fetch("PHP/orders/place.php", { method: "POST", body });
            let data;
            try {
                data = await res.json();
            } catch {
                throw new Error("The server sent an unexpected reply. Is MySQL running?");
            }
            if (res.status === 401) {
                throw new Error(`${esc(data.error)} <a href="login.html">Sign in</a>`);
            }
            if (!res.ok) throw new Error(esc(data.error || `Request failed (${res.status})`));

            await animation;
            cart = [];
            renderCart();               // totals back to $0.00
            setCartCount(0);            // header cart badge (JS/session.js)
            showConfirmation(data, address);
            orderStatus.textContent = `Order ${data.orderId} placed. Delivering to ${address.label}.`;
        } catch (err) {
            resetOrderButton(checkoutBtn);
            showOrderError(err.message);
            orderStatus.textContent = "Your order was not placed.";
            loadCart();                 // stock may have changed: show the cart as it is now
        }
    });

    loadCart();
    renderAddresses();
});
