document.addEventListener("DOMContentLoaded", async () => {
    const grid = document.getElementById("productGrid");
    if (!grid) return;

    // Load the products from the database (loadProducts and the card helpers are in JS/products.js)
    let products, categories;
    try {
        ({ products, categories } = await loadProducts());
    } catch (err) {
        console.error("Could not load products:", err);
        grid.innerHTML = `<p class="no-products">Sorry, products could not be loaded. Please try again later.</p>`;
        return;
    }

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

    // Every category is listed (not only ones with products) so menu links like
    // shop.html?category=Networking always have a box to tick
    buildCheckboxes(categoryBox, categories.map((c) => c.name), "category");
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
    // Category links in the header menu and footer arrive as shop.html?category=...
    const params = new URLSearchParams(window.location.search);
    searchInput.value = params.get("q") || "";
    document.querySelectorAll('input[name="category"]').forEach((input) => {
        input.checked = input.value === params.get("category");
    });

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

    bindProductButtons(grid);

    render();
});
