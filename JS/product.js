document.addEventListener("DOMContentLoaded", () => {
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

    // A good quantity goes to PHP/shopping_cart/cart.php, which checks the stock again
    form.addEventListener("submit", (e) => {
        const error = checkQty();
        if (error) {
            e.preventDefault();
            qtyInput.classList.add("invalid");
            showMessage(error, false);
            qtyInput.focus();
        }
    });
});
