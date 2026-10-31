document.addEventListener("DOMContentLoaded", () => {
    // Add to Cart on the related product cards (bindProductButtons is in JS/products.js)
    const page = document.querySelector(".product-page");
    if (page) bindProductButtons(page.querySelector(".pd-related") || document.createElement("div"));

    // Review form: HTML5 requires a rating and 10+ characters, but spaces count as characters,
    // so check the review has real text before it goes to PHP/products/review.php
    const reviewForm = document.getElementById("reviewForm");
    if (reviewForm) {
        const comment = reviewForm.elements.comment;
        const reviewError = document.getElementById("reviewError");
        reviewForm.addEventListener("submit", (e) => {
            let error = "";
            if (!reviewForm.querySelector('input[name="rating"]:checked')) {
                error = "Please choose a rating from 1 to 5 stars.";
            } else if (comment.value.trim().length < 10) {
                error = "Please write at least 10 characters so other shoppers can learn from your review.";
            }
            reviewError.textContent = error;
            comment.classList.toggle("invalid", error !== "" && comment.value.trim().length < 10);
            if (error) e.preventDefault();
        });
    }

    const form = document.getElementById("cartForm");
    if (!form) return; // out of stock: no form on the page

    const detail = document.querySelector(".product-detail");
    const qtyInput = document.getElementById("qtyInput");
    const message = document.getElementById("cartMessage");
    const maxQty = Number(qtyInput.max);

    const showMessage = (text, ok) => {
        message.textContent = text;
        message.classList.toggle("success", ok);
    };

    // type="number" still lets people type 2.5 or 999, so check for a whole number within the stock
    const checkQty = () => {
        const qty = Number(qtyInput.value);
        if (!Number.isInteger(qty) || qty < 1) return "Please enter a quantity of at least 1.";
        if (qty > maxQty) return `Sorry, only ${maxQty} left in stock.`;
        return "";
    };

    qtyInput.addEventListener("input", () => {
        const error = checkQty();
        qtyInput.classList.toggle("invalid", error !== "");
        showMessage(error, false);
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const error = checkQty();
        if (error) {
            qtyInput.classList.add("invalid");
            showMessage(error, false);
            qtyInput.focus();
            return;
        }

        // e.submitter is the button that was clicked: Add to Cart or Buy Now
        const buyNow = e.submitter && e.submitter.dataset.action === "buy";
        const buttons = form.querySelectorAll("button");
        buttons.forEach((btn) => { btn.disabled = true; });

        try {
            // cartRequest (JS/session.js) also checks the stock again on the server
            await cartRequest({ action: "add", product_id: detail.dataset.id, qty: qtyInput.value });
            if (buyNow) {
                window.location.href = "order.html";
                return;
            }
            const qty = Number(qtyInput.value);
            showMessage(`Added ${qty} to your cart. `, true);
            message.insertAdjacentHTML("beforeend", `<a href="order.html">View cart</a>`);
        } catch (err) {
            showMessage(err.message, false);
        } finally {
            buttons.forEach((btn) => { btn.disabled = false; });
        }
    });
});
