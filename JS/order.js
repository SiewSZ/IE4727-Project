document.addEventListener("DOMContentLoaded", () => {
    const itemsBox = document.getElementById("cartItems");
    if (!itemsBox) return;

    // Sample cart until the database is connected
    const cart = [
        { name: "ASUS ROG Astral GeForce RTX 5090", category: "Components & Storage", status: "New", price: 3899.00, oldPrice: 4199.00, qty: 1, image: "/Images/carousel/rtx5090.jpg" },
        { name: "Logitech G Pro X Superlight 2", category: "Computer Peripherals", status: "Hot", price: 219.00, oldPrice: 249.00, qty: 2, image: "" },
        { name: "Samsung Odyssey G9 49\" Monitor", category: "Computer Peripherals", status: "Sale", price: 1599.00, oldPrice: 1899.00, qty: 1, image: "" },
        { name: "TP-Link Archer BE800 Wi-Fi 7 Router", category: "Networking", status: "New", price: 899.00, oldPrice: 0, qty: 1, image: "" }
    ];

    const addresses = [
        { label: "Home", address: "50 Nanyang Ave, Singapore 639798" },
        { label: "Office", address: "1 Fusionopolis Way, Singapore 138632" }
    ];
    let selectedAddress = 0;

    const categoryIcons = {
        "Components & Storage": "fa-memory",
        "Computer Systems": "fa-desktop",
        "Computer Peripherals": "fa-computer-mouse",
        "Gaming & VR": "fa-gamepad",
        "Networking": "fa-wifi"
    };

    const money = (n) => `$${n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const cartCount = document.querySelector(".cart-count");
    const subtotalEl = document.getElementById("summarySubtotal");
    const discountEl = document.getElementById("summaryDiscount");
    const totalEl = document.getElementById("summaryTotal");
    const checkoutBtn = document.getElementById("checkoutBtn");

    const cartItem = (item, index) => `
        <article class="cart-item" data-index="${index}">
            <div class="cart-item-image">
                ${item.image
                    ? `<img src="${item.image}" alt="${item.name}">`
                    : `<i class="fa-solid ${categoryIcons[item.category] || "fa-box"}"></i>`}
            </div>
            <div class="cart-item-details">
                <h3 class="cart-item-name">${item.name}</h3>
                <p class="cart-item-meta">Variant: <strong>${item.category}</strong></p>
                <span class="status-badge ${item.status.toLowerCase()}">${item.status}</span>
            </div>
            <div class="cart-item-unit">
                ${money(item.price)}
                ${item.oldPrice ? `<s>${money(item.oldPrice)}</s>` : ""}
            </div>
            <div class="qty-control">
                <button data-action="decrease" aria-label="Decrease quantity"><i class="fa-solid fa-minus"></i></button>
                <span>${item.qty}</span>
                <button data-action="increase" aria-label="Increase quantity"><i class="fa-solid fa-plus"></i></button>
            </div>
            <p class="cart-item-price">${money(item.price * item.qty)}</p>
            <div class="cart-item-actions">
                <button class="icon-btn" data-action="wishlist" aria-label="Move to wishlist"><i class="fa-regular fa-heart"></i></button>
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
            : `<p class="cart-empty">Your cart is empty. <a href="/shop.html">Continue shopping</a></p>`;

        // SubTotal uses the original price, Discount is the amount saved
        const subtotal = cart.reduce((sum, item) => sum + (item.oldPrice || item.price) * item.qty, 0);
        const total = cart.reduce((sum, item) => sum + item.price * item.qty, 0);

        subtotalEl.textContent = money(subtotal);
        discountEl.textContent = money(subtotal - total);
        totalEl.textContent = money(total);
        checkoutBtn.disabled = cart.length === 0;

        if (cartCount) {
            cartCount.textContent = cart.reduce((sum, item) => sum + item.qty, 0);
        }
    };

    itemsBox.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;

        const index = Number(btn.closest(".cart-item").dataset.index);
        const item = cart[index];

        switch (btn.dataset.action) {
            case "increase":
                item.qty++;
                break;
            case "decrease":
                if (item.qty > 1) item.qty--;
                break;
            case "remove":
                cart.splice(index, 1);
                break;
            case "wishlist":
                btn.classList.toggle("active");
                btn.querySelector("i").classList.toggle("fa-solid");
                btn.querySelector("i").classList.toggle("fa-regular");
                return;
        }
        renderCart();
    });

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

    checkoutBtn.addEventListener("click", () => {
        alert(`Order placed! Delivering to ${addresses[selectedAddress].label}.`);
    });

    renderCart();
    renderAddresses();
});
