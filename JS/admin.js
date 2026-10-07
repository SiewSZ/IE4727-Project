document.addEventListener("DOMContentLoaded", () => {
    // Tabs: show one panel at a time
    const tabs = document.querySelectorAll(".admin-tab");
    tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            tabs.forEach((t) => {
                const active = t === tab;
                t.classList.toggle("active", active);
                t.setAttribute("aria-selected", active);
                document.getElementById(t.dataset.tab).hidden = !active;
            });
        });
    });

    // ---------- Inventory (loaded from the database) ----------
    const LOW_STOCK = 5; // at or below this count the stock pill turns orange

    const tbody = document.getElementById("inventoryBody");
    const searchInput = document.getElementById("inventorySearch");
    const categoryFilter = document.getElementById("inventoryCategory");
    const form = document.getElementById("productForm");
    const formTitle = document.getElementById("productFormTitle");
    const formError = document.getElementById("productFormError");
    const categorySelect = form.elements.category_id;

    let products = [];

    // Product text comes from the database, so escape it before putting it into HTML
    const esc = (text) => String(text).replace(/[&<>"']/g, (ch) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch]);

    const money = (n) => `$${n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    // POST to a product endpoint and return its JSON reply, throwing the server's error message on failure
    const post = async (url, body) => {
        const res = await fetch(url, { method: "POST", body });
        let data;
        try {
            data = await res.json();
        } catch {
            throw new Error("The server sent an unexpected reply. Is MySQL running?");
        }
        if (!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
        return data;
    };

    const productRow = (p) => {
        const stockClass = p.stock === 0 ? "out" : p.stock <= LOW_STOCK ? "low" : "";
        return `
            <tr data-id="${p.id}">
                <td>
                    <div class="admin-product">
                        ${p.image
                            ? `<img src="${esc(p.image)}" alt="">`
                            : `<span class="admin-product-icon"><i class="fa-solid ${esc(p.icon || "fa-box")}"></i></span>`}
                        <div><strong>${esc(p.name)}</strong><small>${esc(p.brand)}</small></div>
                    </div>
                </td>
                <td>${esc(p.category)}</td>
                <td>${money(p.price)}${p.oldPrice ? `<s>${money(p.oldPrice)}</s>` : ""}</td>
                <td><span class="stock-pill ${stockClass}">${p.stock}</span></td>
                <td>${p.isActive
                    ? `<span class="status-pill active">Active</span>`
                    : `<span class="status-pill hidden-pill">Hidden</span>`}</td>
                <td class="admin-actions">
                    <button class="icon-btn" data-action="edit" aria-label="Edit ${esc(p.name)}"><i class="fa-solid fa-pen"></i></button>
                    <button class="icon-btn" data-action="toggle" aria-label="${p.isActive ? "Hide from shop" : "Show in shop"}">
                        <i class="fa-solid ${p.isActive ? "fa-eye-slash" : "fa-eye"}"></i>
                    </button>
                    <button class="icon-btn" data-action="delete" aria-label="Delete ${esc(p.name)}"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>`;
    };

    const renderTable = () => {
        const term = searchInput.value.trim().toLowerCase();
        const category = categoryFilter.value;
        const shown = products.filter((p) =>
            (!category || String(p.categoryId) === category) &&
            (!term || `${p.name} ${p.brand}`.toLowerCase().includes(term)));

        tbody.innerHTML = shown.length
            ? shown.map(productRow).join("")
            : `<tr><td colspan="6">No products found.</td></tr>`;

        document.getElementById("statProducts").textContent = products.length;
        document.getElementById("statLowStock").textContent = products.filter((p) => p.stock <= LOW_STOCK).length;
    };

    const fillCategoryOptions = (categories) => {
        const options = categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join("");
        categoryFilter.innerHTML = `<option value="">All categories</option>${options}`;
        categorySelect.innerHTML = `<option value="">Select a category</option>${options}`;
    };

    const loadProducts = async () => {
        try {
            const res = await fetch("PHP/products/list.php?all=1");
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            products = data.products;
            // Keep the chosen filter when reloading after a save
            const chosen = categoryFilter.value;
            fillCategoryOptions(data.categories);
            categoryFilter.value = chosen;
            renderTable();
        } catch (err) {
            console.error("Could not load products:", err);
            tbody.innerHTML = `<tr><td colspan="6">Products could not be loaded. Check that Apache and MySQL are running.</td></tr>`;
        }
    };

    searchInput.addEventListener("input", renderTable);
    categoryFilter.addEventListener("change", renderTable);

    // Add / edit product form
    const showError = (message) => {
        formError.textContent = message;
        formError.hidden = !message;
    };

    const openForm = (title) => {
        formTitle.textContent = title;
        showError("");
        form.hidden = false;
        form.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    document.getElementById("addProductBtn").addEventListener("click", () => {
        form.reset();
        form.elements.product_id.value = "";
        openForm("Add Product");
    });

    document.getElementById("cancelProductBtn").addEventListener("click", () => {
        form.hidden = true;
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const saveBtn = form.querySelector('button[type="submit"]');
        saveBtn.disabled = true;
        try {
            await post("PHP/products/save.php", new FormData(form));
            form.hidden = true;
            form.reset();
            await loadProducts();
        } catch (err) {
            showError(err.message);
        } finally {
            saveBtn.disabled = false;
        }
    });

    // Edit / show-hide / delete buttons in each row
    tbody.addEventListener("click", async (e) => {
        const btn = e.target.closest(".icon-btn");
        if (!btn) return;
        const id = Number(btn.closest("tr").dataset.id);
        const p = products.find((item) => item.id === id);
        if (!p) return;

        if (btn.dataset.action === "edit") {
            form.reset();
            form.elements.product_id.value = p.id;
            form.elements.name.value = p.name;
            form.elements.brand.value = p.brand;
            form.elements.category_id.value = p.categoryId;
            form.elements.price.value = p.price;
            form.elements.old_price.value = p.oldPrice || "";
            form.elements.stock.value = p.stock;
            form.elements.description.value = p.description;
            form.elements.is_active.checked = p.isActive;
            openForm("Edit Product");
            return;
        }

        const body = new FormData();
        body.append("product_id", p.id);

        try {
            if (btn.dataset.action === "toggle") {
                body.append("is_active", p.isActive ? "0" : "1");
                await post("PHP/products/toggle.php", body);
            } else if (btn.dataset.action === "delete") {
                if (!confirm(`Delete "${p.name}"? This cannot be undone.`)) return;
                await post("PHP/products/delete.php", body);
            }
            await loadProducts();
        } catch (err) {
            alert(err.message);
        }
    });

    loadProducts();
});
