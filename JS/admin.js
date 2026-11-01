// Admin dashboard (admin.php). PHP draws every tab and does every change through normal forms;
// this file only makes the page quicker to use: switching tabs without reloading, filtering the
// tables already on the page, "are you sure?" questions, and the sale price helper.
document.addEventListener("DOMContentLoaded", () => {
    // ---------- Tabs: show one panel at a time ----------
    // Each tab is also a link to admin.php?tab=..., so the address is updated to match
    const tabs = document.querySelectorAll(".admin-tab");
    tabs.forEach((tab) => {
        tab.addEventListener("click", (e) => {
            e.preventDefault();
            tabs.forEach((t) => {
                const active = t === tab;
                t.classList.toggle("active", active);
                t.setAttribute("aria-selected", active);
                document.getElementById(`${t.dataset.tab}Panel`).hidden = !active;
            });
            history.replaceState(null, "", tab.href);
        });
    });

    // ---------- Filters above the Inventory, Orders and Users tables ----------
    // A row is shown when its data-search text contains the typed words and its data-filter
    // matches the chosen option ("" = everything)
    document.querySelectorAll("tbody[id]").forEach((tbody) => {
        const controls = document.querySelectorAll(`[data-filter-table="${tbody.id}"]`);
        if (!controls.length) return;
        const search = [...controls].find((el) => el.tagName === "INPUT");
        const select = [...controls].find((el) => el.tagName === "SELECT");
        const rows = tbody.querySelectorAll("tr[data-search]");
        const noMatch = tbody.querySelector(".no-match");

        const filter = () => {
            const term = search.value.trim().toLowerCase().replace(/^#/, "");
            let shown = 0;
            rows.forEach((row) => {
                const match = row.dataset.search.includes(term) && (!select.value || row.dataset.filter === select.value);
                row.hidden = !match;
                if (match) shown++;
            });
            noMatch.hidden = shown > 0;
        };
        search.addEventListener("input", filter);
        select.addEventListener("change", filter);
    });

    // ---------- "Are you sure?" before deleting, changing a role or cancelling an order ----------
    document.querySelectorAll("form[data-confirm]").forEach((form) => {
        form.addEventListener("submit", (e) => {
            if (!confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    document.querySelectorAll(".status-form").forEach((form) => {
        form.addEventListener("submit", (e) => {
            if (form.elements.status.value === "Cancelled" &&
                !confirm(`Cancel order #${form.dataset.order}? Its items go back into stock, and it can't be reopened.`)) {
                e.preventDefault();
            }
        });
    });

    // ---------- Run a Sale (bulk discount for a category) ----------
    const saleForm = document.getElementById("saleForm");
    const saleError = document.getElementById("saleFormError");
    const saleCategory = document.getElementById("saleCategory");

    document.getElementById("saleBtn").addEventListener("click", () => {
        saleError.hidden = true;
        saleForm.hidden = !saleForm.hidden;
    });
    document.getElementById("cancelSaleBtn").addEventListener("click", () => { saleForm.hidden = true; });

    saleForm.addEventListener("submit", (e) => {
        const percent = Number(saleForm.elements.percent.value);
        if (!Number.isInteger(percent) || percent < 0 || percent > 90) {
            e.preventDefault();
            saleError.textContent = "Discount must be a whole number from 0 to 90.";
            saleError.hidden = false;
            return;
        }
        const where = saleCategory.options[saleCategory.selectedIndex].text;
        const question = percent === 0
            ? `End the sale on ${where} and go back to regular prices?`
            : `Put ${where} on sale at ${percent}% off? This replaces any discount already running.`;
        if (!confirm(question)) e.preventDefault();
    });

    // ---------- Prices in the product form ----------
    // Regular price + optional sale price are what get saved. The Discount % box is a helper:
    // typing a % fills in the sale price, and typing a sale price updates the % shown.
    const form = document.getElementById("productForm");
    if (!form) return;   // the form is only on the page while adding or editing a product

    const money = (n) => `$${n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
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

    const syncDiscountFromSale = () => {
        const regular = Number(regularInput.value);
        const sale = Number(saleInput.value);
        discountInput.value = saleInput.value !== "" && regular > 0 && sale < regular
            ? Math.round((1 - sale / regular) * 100)
            : 0;
        updatePreview();
    };

    discountInput.addEventListener("input", syncSaleFromDiscount);
    saleInput.addEventListener("input", syncDiscountFromSale);
    // A new regular price keeps the chosen % and recalculates the sale price
    regularInput.addEventListener("input", () => {
        if (Number(discountInput.value) > 0) syncSaleFromDiscount(); else updatePreview();
    });

    syncDiscountFromSale();   // show the % and preview for the product being edited
    form.scrollIntoView({ block: "start" });
});

// ---------- Sales report: pointing at a day's bar shows its numbers under the chart ----------
document.addEventListener("mouseover", (e) => {
    const bar = e.target.closest(".report-bar");
    if (bar) bar.closest(".report-section").querySelector(".report-readout").textContent = bar.dataset.tip;
});
