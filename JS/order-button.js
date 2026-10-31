// Order Button: "Place Order" -> spinner and filling progress bar -> "Order Placed" (styles in CSS/order-button.css).
//
// Usage: animateOrderButton(button).then(() => { ...order is placed... });
//        resetOrderButton(button) puts it back to "Place Order" (e.g. when the server refuses the order)
// The returned promise resolves once the progress bar is full (about 2 seconds),
// and the button is left on "Order Placed" so the order can't be submitted twice.

const ORDER_ANIMATION_MS = 2000;

function animateOrderButton(button) {
    return new Promise((resolve) => {
        if (button.classList.contains("animate") || button.classList.contains("done")) return;

        button.disabled = true;

        // Visitors who turn off animations in their system settings skip straight to the result
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            button.classList.add("done");
            resolve();
            return;
        }

        button.classList.add("animate");
        button.orderTimer = setTimeout(() => {
            button.classList.remove("animate");
            button.classList.add("done");
            resolve();
        }, ORDER_ANIMATION_MS);
    });
}

function resetOrderButton(button) {
    clearTimeout(button.orderTimer);
    button.classList.remove("animate", "done");
    button.disabled = false;
}
