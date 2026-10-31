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
    const saleCategory = document.getElementById("saleCategory");

    let products = [];
    let categories = [];

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

    // ---------- Insights tab: worked out from the products already loaded ----------
    const renderInsights = () => {
        const stockValue = (p) => p.price * p.stock;
        const totalValue = products.reduce((sum, p) => sum + stockValue(p), 0);
        const totalUnits = products.reduce((sum, p) => sum + p.stock, 0);
        const onSale = products.filter((p) => p.oldPrice > p.price);
        const avgDiscount = onSale.length
            ? onSale.reduce((sum, p) => sum + (1 - p.price / p.oldPrice), 0) / onSale.length * 100
            : 0;
        const needRestock = products.filter((p) => p.stock <= LOW_STOCK).sort((a, b) => a.stock - b.stock);

        const tile = (label, value, note) => `
            <div class="insight-tile">
                <p class="insight-label">${label}</p>
                <p class="insight-value">${value}</p>
                <p class="insight-note">${note}</p>
            </div>`;

        document.getElementById("insightTiles").innerHTML =
            tile("Stock value", money(totalValue), "price × units, every product") +
            tile("Units in stock", totalUnits.toLocaleString("en-US"), `across ${products.length} products`) +
            tile("Average price", money(products.length ? products.reduce((sum, p) => sum + p.price, 0) / products.length : 0), "per product listed") +
            tile("On sale", `${onSale.length} <small>of ${products.length}</small>`, onSale.length ? `${Math.round(avgDiscount)}% off on average` : "no discounts running");

        // Every category is listed, so an empty one shows up as $0 instead of disappearing
        const rows = categories
            .map((c) => {
                const inCategory = products.filter((p) => p.categoryId === c.id);
                return { name: c.name, count: inCategory.length, value: inCategory.reduce((sum, p) => sum + stockValue(p), 0) };
            })
            .sort((a, b) => b.value - a.value);
        const maxValue = Math.max(...rows.map((r) => r.value), 1);

        document.getElementById("valueChart").innerHTML = rows.map((r) => `
            <li title="${esc(r.name)}: ${money(r.value)} in stock across ${r.count} product${r.count === 1 ? "" : "s"}">
                <span class="bar-label">${esc(r.name)} <small>(${r.count} product${r.count === 1 ? "" : "s"})</small></span>
                <span class="bar-track"><span class="bar" style="width: ${r.value / maxValue * 100}%"></span></span>
                <span class="bar-value">${money(r.value)}</span>
            </li>`).join("");

        // ----- Discounts: the deal analysis that used to be on the public Hot Deals page -----
        const percentOff = (p) => (1 - p.price / p.oldPrice) * 100;
        const deals = onSale.slice().sort((a, b) => percentOff(b) - percentOff(a));
        const biggest = deals.reduce((best, p) => (!best || p.oldPrice - p.price > best.oldPrice - best.price ? p : best), null);
        const unitsOnSale = deals.reduce((sum, p) => sum + p.stock, 0);
        const discountGiven = deals.reduce((sum, p) => sum + (p.oldPrice - p.price) * p.stock, 0);

        document.getElementById("dealTiles").innerHTML =
            tile("Products on sale", `${deals.length} <small>of ${products.length}</small>`, `${products.length ? Math.round(deals.length / products.length * 100) : 0}% of the range is discounted`) +
            tile("Average discount", `${Math.round(avgDiscount)}%`, "across every product on sale") +
            tile("Biggest saving", biggest ? money(biggest.oldPrice - biggest.price) : "–", biggest ? `on the ${esc(biggest.name)}` : "no discounts running") +
            tile("Discount on stock", money(discountGiven), `if all ${unitsOnSale} units on sale sell`);

        // Only categories with at least one deal; an average of nothing would be misleading
        const discountRows = categories
            .map((c) => {
                const inCategory = deals.filter((p) => p.categoryId === c.id);
                return { name: c.name, count: inCategory.length, avg: inCategory.reduce((sum, p) => sum + percentOff(p), 0) / (inCategory.length || 1) };
            })
            .filter((r) => r.count > 0)
            .sort((a, b) => b.avg - a.avg);
        const maxAvg = Math.max(...discountRows.map((r) => r.avg), 1);

        document.getElementById("discountChart").innerHTML = discountRows.length
            ? discountRows.map((r) => `
                <li title="${esc(r.name)}: ${r.avg.toFixed(1)}% off on average across ${r.count} deal${r.count === 1 ? "" : "s"}">
                    <span class="bar-label">${esc(r.name)} <small>(${r.count} deal${r.count === 1 ? "" : "s"})</small></span>
                    <span class="bar-track"><span class="bar" style="width: ${r.avg / maxAvg * 100}%"></span></span>
                    <span class="bar-value">${Math.round(r.avg)}%</span>
                </li>`).join("")
            : `<li>No products are on sale.</li>`;

        document.getElementById("dealsBody").innerHTML = deals.length
            ? deals.map((p) => `
                <tr>
                    <th scope="row">${esc(p.name)}<small>${esc(p.category)}${p.isActive ? "" : " · hidden"}</small></th>
                    <td><s>${money(p.oldPrice)}</s></td>
                    <td>${money(p.price)}</td>
                    <td>${money(p.oldPrice - p.price)}</td>
                    <td>${Math.round(percentOff(p))}%</td>
                    <td>${p.stock === 0 ? `<span class="out">Sold out</span>` : p.stock}</td>
                </tr>`).join("")
            : `<tr><td colspan="6">No products are on sale right now.</td></tr>`;

        document.getElementById("restockBody").innerHTML = needRestock.length
            ? needRestock.map((p) => `
                <tr>
                    <th scope="row">${esc(p.name)}<small>${esc(p.category)}${p.isActive ? "" : " · hidden"}</small></th>
                    <td>${money(p.price)}</td>
                    <td>${p.stock === 0 ? `<span class="out">Out of stock</span>` : p.stock}</td>
                    <td>${money(stockValue(p))}</td>
                </tr>`).join("")
            : `<tr><td colspan="4">Every product has more than ${LOW_STOCK} in stock.</td></tr>`;
    };

    const fillCategoryOptions = (categories) => {
        const options = categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join("");
        categoryFilter.innerHTML = `<option value="">All categories</option>${options}`;
        categorySelect.innerHTML = `<option value="">Select a category</option>${options}`;
        saleCategory.innerHTML = `<option value="0">All products</option>${options}`;
    };

    const loadProducts = async () => {
        try {
            const res = await fetch("PHP/products/list.php?all=1");
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            products = data.products;
            categories = data.categories;
            // Keep the chosen filter when reloading after a save
            const chosen = categoryFilter.value;
            fillCategoryOptions(data.categories);
            categoryFilter.value = chosen;
            renderTable();
            renderInsights();
        } catch (err) {
            console.error("Could not load products:", err);
            tbody.innerHTML = `<tr><td colspan="6">Products could not be loaded. Check that Apache and MySQL are running.</td></tr>`;
        }
    };

    searchInput.addEventListener("input", renderTable);
    categoryFilter.addEventListener("change", renderTable);

    // ---------- Prices in the product form ----------
    // Regular price + optional sale price are what get saved. The Discount % box is a helper:
    // typing a % fills in the sale price, and typing a sale price updates the % shown.
    const regularInput = form.elements.regular_price;
    const saleInput = form.elements.sale_price;
    const discountInput = document.getElementById("discountInput");
    const pricePreview = document.getElementById("pricePreview");

    const updatePreview = () => {
        const regular = Number(regularInput.value);
        const sale = saleInput.value === "" ? null : Number(saleInput.value);
        if (!regularInput.value || regular <= 0) {
            pricePreview.textContent = "Enter the regular price to see what customers pay.";
        } else if (sale === null) {
            pricePreview.textContent = `Not on sale: customers pay ${money(regular)}.`;
        } else if (sale >= regular) {
            pricePreview.textContent = "The sale price must be lower than the regular price.";
        } else {
            pricePreview.innerHTML = `Customers pay <strong>${money(sale)}</strong>, saving ${money(regular - sale)} (${Math.round((1 - sale / regular) * 100)}% off).`;
        }
    };

    const syncSaleFromDiscount = () => {
        const regular = Number(regularInput.value);
        const percent = Number(discountInput.value);
        saleInput.value = regular > 0 && percent > 0 && percent <= 90
            ? (Math.round(regular * (100 - percent)) / 100).toFixed(2)
            : "";
        updatePreview();
    };

    function syncDiscountFromSale() {
        const regular = Number(regularInput.value);
        const sale = Number(saleInput.value);
        discountInput.value = saleInput.value !== "" && regular > 0 && sale < regular
            ? Math.round((1 - sale / regular) * 100)
            : 0;
        updatePreview();
    }

    discountInput.addEventListener("input", syncSaleFromDiscount);
    saleInput.addEventListener("input", syncDiscountFromSale);
    // A new regular price keeps the chosen % and recalculates the sale price
    regularInput.addEventListener("input", () => {
        if (Number(discountInput.value) > 0) syncSaleFromDiscount(); else updatePreview();
    });

    // ---------- Run a Sale (bulk discount for a category) ----------
    const saleForm = document.getElementById("saleForm");
    const saleError = document.getElementById("saleFormError");

    document.getElementById("saleBtn").addEventListener("click", () => {
        saleError.hidden = true;
        saleForm.hidden = !saleForm.hidden;
    });
    document.getElementById("cancelSaleBtn").addEventListener("click", () => { saleForm.hidden = true; });

    saleForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const percent = Number(saleForm.elements.percent.value);
        if (!Number.isInteger(percent) || percent < 0 || percent > 90) {
            saleError.textContent = "Discount must be a whole number from 0 to 90.";
            saleError.hidden = false;
            return;
        }
        const where = saleCategory.options[saleCategory.selectedIndex].text;
        const question = percent === 0
            ? `End the sale on ${where} and go back to regular prices?`
            : `Put ${where} on sale at ${percent}% off? This replaces any discount already running.`;
        if (!confirm(question)) return;

        try {
            const result = await post("PHP/products/discount.php", new FormData(saleForm));
            saleForm.hidden = true;
            await loadProducts();
            alert(`Done: ${result.updated} product${result.updated === 1 ? "" : "s"} updated.`);
        } catch (err) {
            saleError.textContent = err.message;
            saleError.hidden = false;
        }
    });

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
        updatePreview();
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
            // On sale: the stored old price is the regular price
            form.elements.regular_price.value = p.oldPrice || p.price;
            form.elements.sale_price.value = p.oldPrice ? p.price : "";
            syncDiscountFromSale();
            form.elements.stock.value = p.stock;
            form.elements.description.value = p.description;
            form.elements.features.value = p.features;
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

    // ---------- Orders tab (PHP/orders/list.php and status.php) ----------
    const ordersBody = document.getElementById("ordersBody");
    const orderSearch = document.getElementById("orderSearch");
    const orderStatusFilter = document.getElementById("orderStatusFilter");
    const ORDER_STATUSES = ["Pending", "Processing", "Shipped", "Delivered", "Cancelled"];
    let orders = [];

    const orderRow = (o) => {
        const itemCount = o.items.reduce((sum, item) => sum + item.qty, 0);
        const placed = new Date(o.placed.replace(" ", "T"));
        // A cancelled order can't be reopened, so it has no status form
        const statusForm = o.status === "Cancelled"
            ? `<span class="order-final">Stock returned</span>`
            : `<form class="status-form" data-id="${o.id}">
                    <select name="status" aria-label="New status for order ${o.id}">
                        ${ORDER_STATUSES.map((s) => `<option${s === o.status ? " selected" : ""}>${s}</option>`).join("")}
                    </select>
                    <button type="submit" class="admin-secondary-btn">Update</button>
                </form>`;
        return `
            <tr>
                <td><strong>#${o.id}</strong></td>
                <td>${esc(o.customer)}<small>${esc(o.email)}</small></td>
                <td>${placed.toLocaleDateString("en-SG", { day: "2-digit", month: "short", year: "numeric" })}<small>${placed.toLocaleTimeString("en-SG", { hour: "2-digit", minute: "2-digit" })}</small></td>
                <td>
                    <details class="order-items">
                        <summary>${itemCount} item${itemCount === 1 ? "" : "s"}</summary>
                        <ul>${o.items.map((item) => `<li>${item.qty} × ${esc(item.name)} <span>${money(item.unitPrice * item.qty)}</span></li>`).join("")}</ul>
                        <p class="order-address"><i class="fa-solid fa-location-dot"></i> ${esc(o.addressLabel)}: ${esc(o.address)}</p>
                    </details>
                </td>
                <td>${money(o.total)}${o.discount > 0 ? `<small>saved ${money(o.discount)}</small>` : ""}</td>
                <td><span class="order-status ${o.status.toLowerCase()}">${o.status}</span></td>
                <td>${statusForm}</td>
            </tr>`;
    };

    const renderOrders = () => {
        const term = orderSearch.value.trim().toLowerCase().replace(/^#/, "");
        const status = orderStatusFilter.value;
        const shown = orders.filter((o) =>
            (!status || o.status === status) &&
            (!term || `${o.id} ${o.customer} ${o.email}`.toLowerCase().includes(term)));
        ordersBody.innerHTML = shown.length
            ? shown.map(orderRow).join("")
            : `<tr><td colspan="7">${orders.length ? "No orders match." : "No orders yet. Orders placed from the cart will appear here."}</td></tr>`;
    };

    const loadOrders = async () => {
        try {
            const res = await fetch("PHP/orders/list.php");
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            orders = data.orders;
            document.getElementById("statPending").textContent = data.pending;
            document.getElementById("statRevenue").textContent = money(data.revenueThisMonth);
            renderOrders();
        } catch (err) {
            console.error("Could not load orders:", err);
            ordersBody.innerHTML = `<tr><td colspan="7">Orders could not be loaded. Check that Apache and MySQL are running.</td></tr>`;
        }
    };

    orderSearch.addEventListener("input", renderOrders);
    orderStatusFilter.addEventListener("change", renderOrders);

    ordersBody.addEventListener("submit", async (e) => {
        const statusForm = e.target.closest(".status-form");
        if (!statusForm) return;
        e.preventDefault();
        const newStatus = statusForm.elements.status.value;
        if (newStatus === "Cancelled" &&
            !confirm(`Cancel order #${statusForm.dataset.id}? Its items go back into stock, and it can't be reopened.`)) return;

        const body = new FormData();
        body.append("order_id", statusForm.dataset.id);
        body.append("status", newStatus);
        try {
            await post("PHP/orders/status.php", body);
            await loadOrders();
            if (newStatus === "Cancelled") await loadProducts();   // stock changed
        } catch (err) {
            alert(err.message);
        }
    });

    // ---------- Users tab ----------
    const usersBody = document.getElementById("usersBody");
    const userSearch = document.getElementById("userSearch");
    const userRoleFilter = document.getElementById("userRoleFilter");
    let users = [];
    let currentUserId = 0;

    // MySQL sends "2026-11-01 14:05:00"; the T makes every browser read it as local time
    const toDate = (value) => (value ? new Date(value.replace(" ", "T")) : null);
    const DAY = 24 * 60 * 60 * 1000;
    const formatDate = (date) => date.toLocaleDateString("en-SG", { day: "2-digit", month: "short", year: "numeric" });
    const timeAgo = (date) => {
        const minutes = Math.round((Date.now() - date) / 60000);
        if (minutes < 1) return "just now";
        if (minutes < 60) return `${minutes} min ago`;
        if (minutes < 24 * 60) return `${Math.round(minutes / 60)} h ago`;
        const days = Math.round(minutes / (24 * 60));
        return days < 30 ? `${days} day${days === 1 ? "" : "s"} ago` : formatDate(date);
    };

    const userTile = (label, value, note) => `
        <div class="insight-tile">
            <p class="insight-label">${label}</p>
            <p class="insight-value">${value}</p>
            <p class="insight-note">${note}</p>
        </div>`;

    const userRow = (u) => {
        const isMe = u.id === currentUserId;
        const lastLogin = toDate(u.lastLogin);
        const otherRole = u.role === "admin" ? "customer" : "admin";
        return `
            <tr data-id="${u.id}">
                <td><strong>${esc(u.username)}</strong>${isMe ? `<span class="you-tag">You</span>` : ""}<small>${esc(u.email)}</small></td>
                <td><span class="role-pill ${u.role}">${u.role === "admin" ? "Admin" : "Customer"}</span></td>
                <td>${formatDate(toDate(u.joined))}</td>
                <td>${lastLogin
                    ? `<span title="${lastLogin.toLocaleString("en-SG")}">${timeAgo(lastLogin)}</span>`
                    : `<span class="last-login-never">Never</span>`}</td>
                <td>${isMe ? "" : `<div class="user-actions">
                    <button class="admin-secondary-btn" data-action="role" data-role="${otherRole}">
                        ${otherRole === "admin" ? "Make admin" : "Make customer"}
                    </button>
                    <button class="icon-btn" data-action="delete" aria-label="Delete ${esc(u.username)}"><i class="fa-solid fa-trash"></i></button>
                </div>`}
                </td>
            </tr>`;
    };

    const renderUsers = () => {
        const term = userSearch.value.trim().toLowerCase();
        const role = userRoleFilter.value;
        const shown = users.filter((u) =>
            (!role || u.role === role) &&
            (!term || `${u.username} ${u.email}`.toLowerCase().includes(term)));

        usersBody.innerHTML = shown.length
            ? shown.map(userRow).join("")
            : `<tr><td colspan="5">No users found.</td></tr>`;

        const since = Date.now() - 7 * DAY;
        const admins = users.filter((u) => u.role === "admin").length;
        const newThisWeek = users.filter((u) => toDate(u.joined) >= since).length;
        const activeThisWeek = users.filter((u) => u.lastLogin && toDate(u.lastLogin) >= since).length;
        document.getElementById("userTiles").innerHTML =
            userTile("Total users", users.length, "registered accounts") +
            userTile("Admins", admins, `${users.length - admins} customer${users.length - admins === 1 ? "" : "s"}`) +
            userTile("New this week", newThisWeek, "joined in the last 7 days") +
            userTile("Active this week", activeThisWeek, "signed in during the last 7 days");
    };

    const loadUsers = async () => {
        try {
            const res = await fetch("PHP/users/list.php");
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            users = data.users;
            currentUserId = data.currentUserId;
            renderUsers();
        } catch (err) {
            console.error("Could not load users:", err);
            usersBody.innerHTML = `<tr><td colspan="5">Users could not be loaded. Check that Apache and MySQL are running.</td></tr>`;
        }
    };

    userSearch.addEventListener("input", renderUsers);
    userRoleFilter.addEventListener("change", renderUsers);

    usersBody.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-action]");
        if (!btn) return;
        const u = users.find((item) => item.id === Number(btn.closest("tr").dataset.id));
        if (!u) return;

        const body = new FormData();
        body.append("user_id", u.id);
        try {
            if (btn.dataset.action === "role") {
                const newRole = btn.dataset.role;
                const question = newRole === "admin"
                    ? `Make ${u.username} an admin? They will be able to change products, prices and users.`
                    : `Make ${u.username} a customer? They will lose access to this page.`;
                if (!confirm(question)) return;
                body.append("role", newRole);
                await post("PHP/users/role.php", body);
            } else {
                if (!confirm(`Delete the account "${u.username}" (${u.email})? This cannot be undone.`)) return;
                await post("PHP/users/delete.php", body);
            }
            await loadUsers();
        } catch (err) {
            alert(err.message);
        }
    });

    // Only admins may use this page, so ask the server who is signed in before loading anything.
    // The PHP endpoints check again (requireAdmin), so this just keeps other visitors off the page.
    const isAdmin = async () => {
        try {
            const res = await fetch("PHP/users/me.php");
            const me = await res.json();
            return me.role === "admin";
        } catch {
            return false;
        }
    };

    isAdmin().then((admin) => {
        if (admin) {
            loadProducts();
            loadOrders();
            loadUsers();
        } else {
            window.location.replace("login.html?login_error=admin");
        }
    });
});
