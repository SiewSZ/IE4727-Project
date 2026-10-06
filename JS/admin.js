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

    // Add / edit product form
    const form = document.getElementById("productForm");
    const formTitle = document.getElementById("productFormTitle");

    const openForm = (title) => {
        formTitle.textContent = title;
        form.hidden = false;
        form.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    document.getElementById("addProductBtn").addEventListener("click", () => {
        form.reset();
        openForm("Add Product");
    });

    document.getElementById("cancelProductBtn").addEventListener("click", () => {
        form.hidden = true;
    });

    // Fill the form from the clicked row
    const toNumber = (text) => text.replace(/[$,]/g, "").trim();

    document.querySelectorAll('.icon-btn[data-action="edit"]').forEach((btn) => {
        btn.addEventListener("click", () => {
            const row = btn.closest("tr");
            const cells = row.querySelectorAll("td");
            const oldPrice = cells[2].querySelector("s");

            form.reset();
            form.elements.name.value = row.querySelector(".admin-product strong").textContent;
            form.elements.brand.value = row.querySelector(".admin-product small").textContent;
            form.elements.category.value = cells[1].textContent;
            form.elements.price.value = toNumber(cells[2].firstChild.textContent);
            form.elements.old_price.value = oldPrice ? toNumber(oldPrice.textContent) : "";
            form.elements.stock.value = cells[3].textContent.trim();
            form.elements.is_active.checked = !row.querySelector(".hidden-pill");
            openForm("Edit Product");
        });
    });
});
