// Sales Report tab on admin.html. The Generate Report button asks PHP/reports/sales_report.php
// for the chosen dates and shows the result. The sums and analytics are worked out in PHP;
// this file only displays them. serverRequest is in JS/session.js.

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("reportForm");
    if (!form) return;

    const output = document.getElementById("reportOutput");
    const errorBox = document.getElementById("reportError");

    // Product names come from the database, so escape them before putting them into HTML
    const esc = (text) => String(text).replace(/[&<>"']/g, (ch) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch]);

    const money = (n) => `$${n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;

    // "2026-10-31" -> "31 Oct 2026" (read as UTC so the day never shifts with the time zone)
    const niceDate = (iso) => new Date(`${iso}T00:00:00Z`).toLocaleDateString("en-GB",
        { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });

    // A date as YYYY-MM-DD in the admin's own time zone, for the date inputs
    const isoDate = (d) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

    // Start with the last 30 days, ending today
    const today = new Date();
    const monthAgo = new Date(today);
    monthAgo.setDate(today.getDate() - 29);
    form.elements.from.value = isoDate(monthAgo);
    form.elements.to.value = isoDate(today);

    const showError = (message) => {
        errorBox.textContent = message;
        errorBox.hidden = !message;
    };

    // Change from the previous period, e.g. "▲ 12.5%". Arrow and text as well as colour.
    const change = (pct) => {
        if (pct === null) return `<span class="report-change">No sales in the previous period</span>`;
        const [dir, arrow] = pct > 0 ? ["up", "fa-arrow-up"] : pct < 0 ? ["down", "fa-arrow-down"] : ["", "fa-minus"];
        return `<span class="report-change ${dir}"><i class="fa-solid ${arrow}"></i> ${Math.abs(pct)}%</span>`;
    };

    const kpi = (icon, label, value, extra = "") => `
        <div class="stat-card">
            <i class="fa-solid ${icon}"></i>
            <div>
                <p class="stat-label">${label}</p>
                <p class="stat-value">${value}</p>
                ${extra}
            </div>
        </div>`;

    // Revenue per day as bars. Hovering a bar shows that day's numbers in the line under the chart.
    const dailyChart = (daily) => {
        const max = Math.max(...daily.map((d) => d.revenue));
        const bars = daily.map((d) => {
            const height = d.revenue > 0 ? `max(2px, ${(d.revenue / max) * 100}%)` : "0";
            const tip = `${niceDate(d.date)}: ${money(d.revenue)} from ${plural(d.orders, "order")}`;
            return `<div class="report-bar" data-tip="${esc(tip)}"><span style="height: ${height}"></span></div>`;
        }).join("");

        return `
            <div class="report-chart" role="img" aria-label="Revenue per day. Highest day: ${money(max)}.">
                <span class="report-chart-max">${money(max)}</span>
                <div class="report-bars${daily.length > 90 ? " dense" : ""}">${bars}</div>
                <div class="report-axis">
                    <span>${niceDate(daily[0].date)}</span>
                    <span>${niceDate(daily[daily.length - 1].date)}</span>
                </div>
            </div>
            <p class="report-readout">Point at a bar to see that day's sales.</p>`;
    };

    const shareCell = (share) => `
        <div class="share-cell">
            <span class="share-track"><span style="width: ${share}%"></span></span>
            ${share}%
        </div>`;

    // Stock left, and how long it lasts at the rate the product sold in this period
    const stockCell = (p, restockDays) => {
        if (p.stock === null) return `<span class="status-pill hidden-pill">Deleted</span>`;
        if (p.stock === 0) return `<span class="stock-pill out">Sold out</span>`;
        const lasts = p.daysOfStock < 1 ? "less than a day" : `about ${plural(p.daysOfStock, "day")}`;
        return `<span class="stock-pill ${p.restockSoon ? "low" : ""}">${p.stock} left</span>
                <small>${p.restockSoon ? `Restock soon (under ${restockDays} days): ` : ""}${lasts}</small>`;
    };

    const productRow = (p, restockDays) => `
        <tr>
            <td>${p.rank}</td>
            <td><strong>${esc(p.name)}</strong></td>
            <td>${esc(p.category)}</td>
            <td>${p.units}</td>
            <td>${p.orders}</td>
            <td>${money(p.revenue)}</td>
            <td>${shareCell(p.share)}</td>
            <td>${stockCell(p, restockDays)}</td>
        </tr>`;

    const categoryRow = (c) => `
        <tr>
            <td><strong>${esc(c.name)}</strong></td>
            <td>${c.units}</td>
            <td>${c.orders}</td>
            <td>${money(c.revenue)}</td>
            <td>${shareCell(c.share)}</td>
        </tr>`;

    const highlights = (r) => {
        const best = r.products[0];
        const restock = r.products.filter((p) => p.restockSoon);
        const items = [
            ["fa-trophy", "Best seller (revenue)",
                `${esc(best.name)}: ${money(best.revenue)}, ${best.share}% of all revenue`],
            ["fa-boxes-stacked", "Most units sold",
                `${esc(r.mostUnits.name)}: ${plural(r.mostUnits.units, "unit")}`],
            ["fa-calendar-check", "Best day", r.bestDay   // null only if every item sold was free
                ? `${niceDate(r.bestDay.date)}: ${money(r.bestDay.revenue)} from ${plural(r.bestDay.orders, "order")}`
                : "No revenue in this period"],
            ["fa-triangle-exclamation", "Restock soon",
                restock.length ? restock.map((p) => esc(p.name)).join(", ") : "Nothing is about to run out"],
            ["fa-circle-minus", "No sales in this period",
                r.unsold.length ? r.unsold.map((p) => esc(p.name)).join(", ") : "Every product sold at least once"]
        ];
        return items.map(([icon, label, text]) => `
            <li><i class="fa-solid ${icon}"></i><div><strong>${label}</strong><span>${text}</span></div></li>`).join("");
    };

    const renderReport = (r) => {
        const s = r.summary;
        const top = `
            <h2 class="report-heading">${niceDate(r.from)} – ${niceDate(r.to)}
                <small>${plural(r.days, "day")}. Arrows compare with the previous ${plural(r.days, "day")}
                (${niceDate(r.previousFrom)} – ${niceDate(r.previousTo)}).</small>
            </h2>
            <section class="admin-stats report-kpis" aria-label="Report summary">
                ${kpi("fa-sack-dollar", "Revenue", money(s.revenue), change(s.revenueChange))}
                ${kpi("fa-receipt", "Orders", s.orders, change(s.ordersChange))}
                ${kpi("fa-cubes", "Units Sold", s.units, change(s.unitsChange))}
                ${kpi("fa-cart-shopping", "Average Order", money(s.avgOrderValue),
                    `<span class="report-change">${plural(s.itemsPerOrder, "item")} per order</span>`)}
                ${kpi("fa-calendar-day", "Average per Day", money(s.avgDailyRevenue))}
            </section>`;

        if (s.orders === 0) {
            output.innerHTML = `${top}<p class="report-empty">No orders were placed in this period.</p>`;
            return;
        }

        output.innerHTML = `${top}
            <div class="report-grid">
                <section class="report-section">
                    <h3>Highlights</h3>
                    <ul class="report-highlights">${highlights(r)}</ul>
                </section>
                <section class="report-section">
                    <h3>Revenue per Day</h3>
                    ${dailyChart(r.daily)}
                </section>
            </div>

            <section class="report-section">
                <h3>Sales by Product</h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>#</th><th>Product</th><th>Category</th><th>Units</th><th>Orders</th>
                                <th>Revenue</th><th>Share of Revenue</th><th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>${r.products.map((p) => productRow(p, r.restockDays)).join("")}</tbody>
                    </table>
                </div>
            </section>

            <section class="report-section">
                <h3>Sales by Category</h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Category</th><th>Units</th><th>Orders</th><th>Revenue</th><th>Share of Revenue</th></tr>
                        </thead>
                        <tbody>${r.categories.map(categoryRow).join("")}</tbody>
                    </table>
                </div>
            </section>`;
    };

    output.addEventListener("mouseover", (e) => {
        const bar = e.target.closest(".report-bar");
        if (bar) output.querySelector(".report-readout").textContent = bar.dataset.tip;
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        showError("");
        output.innerHTML = `<p class="report-empty">Generating report...</p>`;
        try {
            const params = new URLSearchParams(new FormData(form));
            renderReport(await serverRequest(`PHP/reports/sales_report.php?${params}`));
        } catch (err) {
            output.innerHTML = "";
            showError(err.message);
        } finally {
            btn.disabled = false;
        }
    });
});
