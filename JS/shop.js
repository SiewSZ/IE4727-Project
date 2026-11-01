// Shop page (shop.php). PHP does the searching, filtering and sorting; this file only makes the
// filter form quicker to use: a change is applied straight away, the price sliders stay in step
// with the price boxes, and the Filter button and headings show or hide parts of the sidebar.
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("shopForm");
    if (!form) return;
    const apply = () => form.submit();

    // ---------- Price: two sliders that mirror the two number boxes (only the boxes are sent) ----------
    const minInput = document.getElementById("priceMinInput");
    const maxInput = document.getElementById("priceMaxInput");
    const minRange = document.getElementById("priceMinRange");
    const maxRange = document.getElementById("priceMaxRange");
    const priceFill = document.getElementById("priceFill");
    const PRICE_MAX = Number(maxRange.max);

    const showFill = (min, max) => {
        priceFill.style.left = `${(min / PRICE_MAX) * 100}%`;
        priceFill.style.right = `${100 - (max / PRICE_MAX) * 100}%`;
        // If both handles meet at the far right, keep the min handle on top so it can still be dragged
        minRange.style.zIndex = min >= PRICE_MAX - 10 ? 2 : "";
    };

    const slide = () => {
        minInput.value = minRange.value;
        maxInput.value = maxRange.value;
        showFill(Number(minRange.value), Number(maxRange.value));
    };
    minRange.addEventListener("input", () => {
        if (Number(minRange.value) > Number(maxRange.value)) minRange.value = maxRange.value;
        slide();
    });
    maxRange.addEventListener("input", () => {
        if (Number(maxRange.value) < Number(minRange.value)) maxRange.value = minRange.value;
        slide();
    });
    showFill(Number(minInput.value), Number(maxInput.value));

    // ---------- Apply a change straight away ----------
    // Sliders when they are let go, typed prices when the box loses focus or Enter is pressed
    // (PHP swaps them if the minimum is higher), ticked boxes and the sort menu at once.
    // A search is sent with Enter, which submits the form like the Apply button.
    [minRange, maxRange, minInput, maxInput, document.getElementById("sortSelect")].forEach((el) => {
        el.addEventListener("change", apply);
    });
    document.querySelectorAll('input[name="category[]"], input[name="brand[]"]').forEach((box) => {
        box.addEventListener("change", apply);
    });

    // ---------- Show or hide the sidebar and its sections ----------
    // Every change reloads the page, so remember these for the rest of the visit (sessionStorage)
    const remember = (key, on) => {
        try { sessionStorage.setItem(key, on ? "1" : "0"); } catch { /* storage blocked: just don't remember */ }
    };
    const recall = (key) => {
        try { return sessionStorage.getItem(key) === "1"; } catch { return false; }
    };

    const filterToggle = document.getElementById("filterToggle");
    const shopBody = document.querySelector(".shop-body");
    const setFiltersHidden = (hidden) => {
        shopBody.classList.toggle("filters-hidden", hidden);
        filterToggle.setAttribute("aria-expanded", String(!hidden));
    };
    setFiltersHidden(recall("shopFiltersHidden"));
    filterToggle.addEventListener("click", () => {
        const hidden = !shopBody.classList.contains("filters-hidden");
        setFiltersHidden(hidden);
        remember("shopFiltersHidden", hidden);
    });

    // Each filter heading collapses its own section
    document.querySelectorAll(".filter-group").forEach((group, i) => {
        const heading = group.querySelector(".filter-heading");
        const key = `shopFilterCollapsed${i}`;
        const setCollapsed = (collapsed) => {
            group.classList.toggle("collapsed", collapsed);
            heading.setAttribute("aria-expanded", String(!collapsed));
        };
        setCollapsed(recall(key));
        heading.addEventListener("click", () => {
            const collapsed = !group.classList.contains("collapsed");
            setCollapsed(collapsed);
            remember(key, collapsed);
        });
    });

    // Add to Cart on the product cards (bindProductButtons is in JS/products.js)
    bindProductButtons(document.getElementById("productGrid"));
});
