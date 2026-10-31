document.addEventListener("DOMContentLoaded", () => {
    // Header search (every page): matching products drop down while typing (like Amazon);
    // Enter or the magnifier opens the shop filtered by the search
    const headerSearch = document.querySelector(".search-box input");
    if (headerSearch) {
        const searchBox = headerSearch.closest(".search-box");
        const navTop = searchBox.closest(".nav-top");
        const MAX_SUGGESTIONS = 6;

        const goSearch = () => {
            const q = headerSearch.value.trim();
            if (q) window.location.href = `shop.html?q=${encodeURIComponent(q)}`;
        };

        // Product text comes from the database, so escape it before putting it into HTML
        const esc = (text) => String(text).replace(/[&<>"']/g, (ch) =>
            ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch]);

        // The product list is loaded once, the first time the visitor types
        let productsPromise = null;
        const getProducts = () => {
            productsPromise = productsPromise || fetch("PHP/products/list.php")
                .then((res) => (res.ok ? res.json() : { products: [] }))
                .then((data) => data.products)
                .catch(() => []);
            return productsPromise;
        };

        // The search box hides its overflow (for the circle animation), so the list sits beside it
        const list = document.createElement("div");
        list.className = "search-suggest";
        list.id = "searchSuggest";
        list.setAttribute("role", "listbox");
        list.hidden = true;
        navTop.appendChild(list);
        headerSearch.setAttribute("aria-controls", "searchSuggest");
        headerSearch.setAttribute("aria-autocomplete", "list");
        headerSearch.setAttribute("autocomplete", "off");

        let active = -1;   // highlighted suggestion for arrow-key use

        const hideList = () => {
            list.hidden = true;
            active = -1;
        };

        const placeList = () => {
            const box = searchBox.getBoundingClientRect();
            const bar = navTop.getBoundingClientRect();
            list.style.top = `${box.bottom - bar.top + 8}px`;
            list.style.right = `${bar.right - box.right}px`;
        };

        // Same rule as the shop search: every word must appear in the name, brand, category or description.
        // Matches in the name come first.
        const findMatches = (products, q) => {
            const words = q.toLowerCase().split(/\s+/).filter(Boolean);
            return products
                .filter((p) => {
                    const text = `${p.name} ${p.brand} ${p.category} ${p.description}`.toLowerCase();
                    return words.every((word) => text.includes(word));
                })
                .sort((a, b) => {
                    const inName = (p) => words.every((word) => p.name.toLowerCase().includes(word)) ? 0 : 1;
                    return inName(a) - inName(b) || (b.stock > 0) - (a.stock > 0);
                });
        };

        const showSuggestions = async () => {
            const q = headerSearch.value.trim();
            if (q.length < 2) {
                hideList();
                return;
            }
            const matches = findMatches(await getProducts(), q);
            if (headerSearch.value.trim() !== q) return;   // the visitor kept typing while products loaded

            const shopUrl = `shop.html?q=${encodeURIComponent(q)}`;
            list.innerHTML = matches.length
                ? matches.slice(0, MAX_SUGGESTIONS).map((p) => `
                    <a class="suggest-item" role="option" href="product.php?id=${p.id}">
                        <span class="suggest-image">${p.image
                            ? `<img src="${esc(p.image)}" alt="">`
                            : `<i class="fa-solid ${esc(p.icon || "fa-box")}"></i>`}</span>
                        <span class="suggest-text">
                            <span class="suggest-name">${esc(p.name)}</span>
                            <span class="suggest-meta">${esc(p.category)}${p.stock > 0 ? "" : " · Out of stock"}</span>
                        </span>
                        <span class="suggest-price">$${p.price.toFixed(2)}</span>
                    </a>`).join("") + `
                    <a class="suggest-all" role="option" href="${shopUrl}">
                        See all ${matches.length} result${matches.length === 1 ? "" : "s"} for "${esc(q)}" <i class="fa-solid fa-arrow-right"></i>
                    </a>`
                : `<p class="suggest-empty">No products match "${esc(q)}". <a href="shop.html">Browse the shop</a></p>`;
            active = -1;
            placeList();
            list.hidden = false;
        };

        const highlight = (index) => {
            const items = [...list.querySelectorAll("a[role=option]")];
            if (!items.length) return;
            active = (index + items.length) % items.length;
            items.forEach((item, i) => item.classList.toggle("active", i === active));
            items[active].scrollIntoView({ block: "nearest" });
        };

        headerSearch.addEventListener("input", showSuggestions);
        headerSearch.addEventListener("focus", showSuggestions);

        headerSearch.addEventListener("keydown", (e) => {
            if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                if (list.hidden) return;
                e.preventDefault();
                highlight(active + (e.key === "ArrowDown" ? 1 : -1));
            } else if (e.key === "Enter") {
                const chosen = list.querySelectorAll("a[role=option]")[active];
                if (!list.hidden && chosen) {
                    window.location.href = chosen.href;
                } else {
                    goSearch();
                }
            } else if (e.key === "Escape") {
                hideList();
            }
        });

        searchBox.querySelector("i").addEventListener("click", goSearch);

        // Clicking a suggestion must not blur the input first, or the list would close before the click lands
        list.addEventListener("mousedown", (e) => e.preventDefault());
        headerSearch.addEventListener("blur", hideList);
        window.addEventListener("resize", () => { if (!list.hidden) placeList(); });
    }

    // Highlight the nav link for the page being viewed (index.php when the URL ends in "/")
    const currentPage = window.location.pathname.split("/").pop() || "index.php";
    document.querySelectorAll(".nav-links > li > a").forEach((link) => {
        if (link.getAttribute("href") === currentPage) link.setAttribute("aria-current", "page");
    });

    const menuToggle = document.getElementById("menuToggle");
    const menuItem = menuToggle ? menuToggle.closest(".menu-item") : null;

    if (!menuToggle || !menuItem) return;

    const closeMenu = () => {
        menuItem.classList.remove("active");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    menuToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        const isActive = menuItem.classList.toggle("active");
        menuToggle.setAttribute("aria-expanded", String(isActive));
    });

    document.addEventListener("click", (e) => {
        if (menuItem.classList.contains("active") && !menuItem.contains(e.target)) {
            closeMenu();
        }
    });

    // Links like hotdeals.php#clearance may only scroll the current page, so close the menu ourselves
    menuItem.querySelectorAll(".mega-menu a").forEach((link) => {
        link.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && menuItem.classList.contains("active")) {
            closeMenu();
        }
    });
});
