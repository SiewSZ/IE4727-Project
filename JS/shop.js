document.addEventListener("DOMContentLoaded", () => {
    const grid = document.getElementById("productGrid");
    if (!grid) return;

    // Sample products until the database is connected
    const products = [
        { name: "ASUS ROG Astral GeForce RTX 5090", category: "Components & Storage", brand: "ASUS", price: 3899.00, oldPrice: 4199.00, stock: 5, rating: 5, reviews: 12, image: "/Images/carousel/rtx5090.jpg" },
        { name: "MSI GeForce RTX 5080 Gaming Trio", category: "Components & Storage", brand: "MSI", price: 1899.00, oldPrice: 2049.00, stock: 8, rating: 4, reviews: 9, image: "" },
        { name: "Samsung 990 PRO 2TB NVMe SSD", category: "Components & Storage", brand: "Samsung", price: 289.00, oldPrice: 339.00, stock: 25, rating: 5, reviews: 31, image: "" },
        { name: "ASUS ROG Strix G16 Gaming Laptop", category: "Computer Systems", brand: "ASUS", price: 2799.00, oldPrice: 2999.00, stock: 4, rating: 4, reviews: 7, image: "" },
        { name: "MSI MAG Infinite Gaming Desktop", category: "Computer Systems", brand: "MSI", price: 2399.00, oldPrice: 0, stock: 3, rating: 4, reviews: 5, image: "" },
        { name: "Logitech G Pro X Superlight 2", category: "Computer Peripherals", brand: "Logitech", price: 219.00, oldPrice: 249.00, stock: 40, rating: 5, reviews: 54, image: "" },
        { name: "Razer BlackWidow V4 Pro Keyboard", category: "Computer Peripherals", brand: "Razer", price: 329.00, oldPrice: 0, stock: 15, rating: 4, reviews: 18, image: "" },
        { name: "Samsung Odyssey G9 49\" Monitor", category: "Computer Peripherals", brand: "Samsung", price: 1599.00, oldPrice: 1899.00, stock: 6, rating: 5, reviews: 22, image: "" },
        { name: "Razer Kraken V4 Gaming Headset", category: "Gaming & VR", brand: "Razer", price: 199.00, oldPrice: 239.00, stock: 20, rating: 4, reviews: 14, image: "" },
        { name: "Meta Quest 3 128GB", category: "Gaming & VR", brand: "Meta", price: 749.00, oldPrice: 0, stock: 0, rating: 5, reviews: 40, image: "" },
        { name: "TP-Link Archer BE800 Wi-Fi 7 Router", category: "Networking", brand: "TP-Link", price: 899.00, oldPrice: 999.00, stock: 10, rating: 4, reviews: 8, image: "" },
        { name: "ASUS RT-AX88U Pro Router", category: "Networking", brand: "ASUS", price: 459.00, oldPrice: 0, stock: 12, rating: 4, reviews: 11, image: "" }
    ];

    const categoryIcons = {
        "Components & Storage": "fa-memory",
        "Computer Systems": "fa-desktop",
        "Computer Peripherals": "fa-computer-mouse",
        "Gaming & VR": "fa-gamepad",
        "Networking": "fa-wifi"
    };

    const categoryBox = document.getElementById("categoryFilters");
    const brandBox = document.getElementById("brandFilters");

    const unique = (key) => [...new Set(products.map((p) => p[key]))].sort();

    const buildCheckboxes = (box, values, name) => {
        box.innerHTML = values.map((value) => `
            <label class="filter-option">
                <input type="checkbox" name="${name}" value="${value}">
                <span>${value}</span>
            </label>`).join("");
    };

    const checkedValues = (name) =>
        [...document.querySelectorAll(`input[name="${name}"]:checked`)].map((input) => input.value);

    const stars = (rating) =>
        Array.from({ length: 5 }, (_, i) =>
            `<i class="fa-${i < rating ? "solid" : "regular"} fa-star"></i>`).join("");

    const productCard = (p) => `
        <article class="product-card">
            <div class="product-image">
                ${p.image
                    ? `<img src="${p.image}" alt="${p.name}">`
                    : `<i class="fa-solid ${categoryIcons[p.category] || "fa-box"}"></i>`}
                <button class="wishlist-btn" aria-label="Add to wishlist"><i class="fa-regular fa-heart"></i></button>
            </div>
            <div class="product-info">
                <p class="product-category">${p.category}</p>
                <h3 class="product-name" title="${p.name}">${p.name}</h3>
                <div class="product-rating">${stars(p.rating)}<span>${p.reviews} Reviews</span></div>
                <p class="product-stock">${p.stock > 0 ? `In Stock <span>${p.stock}</span>` : `<span class="out">Out of Stock</span>`}</p>
                <p class="product-price">
                    $${p.price.toFixed(2)}
                    ${p.oldPrice ? `<s>$${p.oldPrice.toFixed(2)}</s>` : ""}
                </p>
                <button class="add-cart-btn" ${p.stock > 0 ? "" : "disabled"}>
                    <i class="fa-solid fa-cart-shopping"></i> Add to Cart
                </button>
            </div>
        </article>`;

    // Price range: slider max is the most expensive product, rounded up to the next 100
    const PRICE_MAX = Math.ceil(Math.max(...products.map((p) => p.price)) / 100) * 100;
    const minInput = document.getElementById("priceMinInput");
    const maxInput = document.getElementById("priceMaxInput");
    const minRange = document.getElementById("priceMinRange");
    const maxRange = document.getElementById("priceMaxRange");
    const priceFill = document.getElementById("priceFill");

    [minInput, maxInput, minRange, maxRange].forEach((el) => { el.max = PRICE_MAX; });
    minRange.step = maxRange.step = 10;

    const setPrice = (min, max) => {
        min = Math.max(0, Math.min(Number(min) || 0, PRICE_MAX));
        max = Math.max(0, Math.min(Number(max) || 0, PRICE_MAX));
        if (min > max) [min, max] = [max, min];

        minInput.value = minRange.value = min;
        maxInput.value = maxRange.value = max;
        priceFill.style.left = `${(min / PRICE_MAX) * 100}%`;
        priceFill.style.right = `${100 - (max / PRICE_MAX) * 100}%`;
        // If both handles meet at the far right, keep the min handle on top so it can still be dragged
        minRange.style.zIndex = min >= PRICE_MAX - 10 ? 2 : "";
    };

    const sorters = {
        "featured": () => 0,
        "name-asc": (a, b) => a.name.localeCompare(b.name),
        "name-desc": (a, b) => b.name.localeCompare(a.name),
        "price-asc": (a, b) => a.price - b.price,
        "price-desc": (a, b) => b.price - a.price
    };
    const sortSelect = document.getElementById("sortSelect");
    const productCount = document.getElementById("productCount");

    const render = () => {
        const categories = checkedValues("category");
        const brands = checkedValues("brand");
        const min = Number(minRange.value);
        const max = Number(maxRange.value);

        // An empty selection means "show everything" for that filter
        const filtered = products
            .filter((p) =>
                (categories.length === 0 || categories.includes(p.category)) &&
                (brands.length === 0 || brands.includes(p.brand)) &&
                p.price >= min && p.price <= max)
            .sort(sorters[sortSelect.value]);

        productCount.textContent = `${filtered.length} product${filtered.length === 1 ? "" : "s"}`;
        grid.innerHTML = filtered.length
            ? filtered.map(productCard).join("")
            : `<p class="no-products">No products match the selected filters.</p>`;
    };

    const clearFilter = (name) => {
        if (name === "price") {
            setPrice(0, PRICE_MAX);
        } else {
            document.querySelectorAll(`input[name="${name}"]`).forEach((input) => { input.checked = false; });
        }
        render();
    };

    buildCheckboxes(categoryBox, unique("category"), "category");
    buildCheckboxes(brandBox, unique("brand"), "brand");
    setPrice(0, PRICE_MAX);

    // Sliders update live; typed numbers apply when the box loses focus or Enter is pressed
    minRange.addEventListener("input", () => {
        if (Number(minRange.value) > Number(maxRange.value)) minRange.value = maxRange.value;
        setPrice(minRange.value, maxRange.value);
        render();
    });
    maxRange.addEventListener("input", () => {
        if (Number(maxRange.value) < Number(minRange.value)) maxRange.value = minRange.value;
        setPrice(minRange.value, maxRange.value);
        render();
    });
    [minInput, maxInput].forEach((input) => {
        input.addEventListener("change", () => {
            setPrice(minInput.value, maxInput.value);
            render();
        });
    });

    document.querySelectorAll('input[name="category"], input[name="brand"]').forEach((input) => {
        input.addEventListener("change", render);
    });
    sortSelect.addEventListener("change", render);

    document.querySelectorAll(".filter-reset").forEach((btn) => {
        btn.addEventListener("click", () => clearFilter(btn.dataset.reset));
    });

    document.getElementById("resetAllFilters").addEventListener("click", () => {
        clearFilter("price");
        clearFilter("category");
        clearFilter("brand");
    });

    // Filter button shows/hides the whole sidebar
    const filterToggle = document.getElementById("filterToggle");
    const shopBody = document.querySelector(".shop-body");
    filterToggle.addEventListener("click", () => {
        const hidden = shopBody.classList.toggle("filters-hidden");
        filterToggle.setAttribute("aria-expanded", String(!hidden));
    });

    // Each filter heading collapses its own section
    document.querySelectorAll(".filter-heading").forEach((heading) => {
        heading.addEventListener("click", () => {
            const collapsed = heading.closest(".filter-group").classList.toggle("collapsed");
            heading.setAttribute("aria-expanded", String(!collapsed));
        });
    });

    // Cart count and wishlist are visual only for now
    const cartCount = document.querySelector(".cart-count");
    grid.addEventListener("click", (e) => {
        const cartBtn = e.target.closest(".add-cart-btn");
        const heartBtn = e.target.closest(".wishlist-btn");

        if (cartBtn && cartCount) {
            cartCount.textContent = Number(cartCount.textContent) + 1;
        }

        if (heartBtn) {
            heartBtn.classList.toggle("active");
            heartBtn.querySelector("i").classList.toggle("fa-solid");
            heartBtn.querySelector("i").classList.toggle("fa-regular");
        }
    });

    render();
});
