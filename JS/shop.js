document.addEventListener("DOMContentLoaded", async () => {
    const grid = document.getElementById("productGrid");
    if (!grid) return;

    // Load the products from the database
    let products;
    try {
        const res = await fetch("PHP/products/list.php");
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        products = (await res.json()).products;
    } catch (err) {
        console.error("Could not load products:", err);
        grid.innerHTML = `<p class="no-products">Sorry, products could not be loaded. Please try again later.</p>`;
        return;
    }

    // Product text comes from the database, so escape it before putting it into HTML
    const esc = (text) => String(text).replace(/[&<>"']/g, (ch) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch]);

    const categoryBox = document.getElementById("categoryFilters");
    const brandBox = document.getElementById("brandFilters");

    const unique = (key) => [...new Set(products.map((p) => p[key]))].sort();

    const buildCheckboxes = (box, values, name) => {
        box.innerHTML = values.map((value) => `
            <label class="filter-option">
                <input type="checkbox" name="${name}" value="${esc(value)}">
                <span>${esc(value)}</span>
            </label>`).join("");
    };

    const checkedValues = (name) =>
        [...document.querySelectorAll(`input[name="${name}"]:checked`)].map((input) => input.value);

    const stars = (rating) =>
        Array.from({ length: 5 }, (_, i) =>
            `<i class="fa-${i < rating ? "solid" : "regular"} fa-star"></i>`).join("");

    const productCard = (p) => `
        <article class="product-card" data-id="${p.id}">
            <div class="product-image${p.image ? "" : " no-image"}">
                ${p.image
                    ? `<img src="${esc(p.image)}" alt="${esc(p.name)}">`
                    : `<i class="fa-solid ${esc(p.icon || "fa-box")}"></i>`}
                <button class="wishlist-btn" aria-label="Add to wishlist"><i class="fa-regular fa-heart"></i></button>
            </div>
            <div class="product-info">
                <p class="product-category">${esc(p.category)}</p>
                <h3 class="product-name" title="${esc(p.name)}">${esc(p.name)}</h3>
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
    const PRICE_MAX = Math.ceil(Math.max(0, ...products.map((p) => p.price)) / 100) * 100 || 100;
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
    const searchInput = document.getElementById("shopSearch");

    // Every word typed must appear somewhere in the product's name, brand, category or description
    const matchesSearch = (p, words) => {
        const text = `${p.name} ${p.brand} ${p.category} ${p.description}`.toLowerCase();
        return words.every((word) => text.includes(word));
    };
    const productCount = document.getElementById("productCount");

    const render = () => {
        const categories = checkedValues("category");
        const brands = checkedValues("brand");
        const min = Number(minRange.value);
        const max = Number(maxRange.value);
        const words = searchInput.value.toLowerCase().split(/\s+/).filter(Boolean);

        // An empty selection means "show everything" for that filter
        const filtered = products
            .filter((p) =>
                (categories.length === 0 || categories.includes(p.category)) &&
                (brands.length === 0 || brands.includes(p.brand)) &&
                p.price >= min && p.price <= max &&
                matchesSearch(p, words))
            .sort(sorters[sortSelect.value]);

        productCount.textContent = `${filtered.length} product${filtered.length === 1 ? "" : "s"}`;
        grid.innerHTML = filtered.length
            ? filtered.map(productCard).join("")
            : `<p class="no-products">No products match ${words.length ? "your search and " : ""}the selected filters.</p>`;
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
    searchInput.addEventListener("input", render);

    // Searches typed in the header on any page arrive as shop.html?q=...
    searchInput.value = new URLSearchParams(window.location.search).get("q") || "";

    document.querySelectorAll(".filter-reset").forEach((btn) => {
        btn.addEventListener("click", () => clearFilter(btn.dataset.reset));
    });

    document.getElementById("resetAllFilters").addEventListener("click", () => {
        searchInput.value = "";
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
