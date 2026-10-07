// Order Button: plays the delivery truck animation (styles in CSS/order-button.css).
// Based on "Order confirm animation" by Aaron Iker (https://codepen.io/aaroniker/pen/eYOVrNa, MIT licence).
//
// Usage: animateOrderButton(button).then(() => { ...order is placed... });
// The returned promise resolves once the truck has driven off (about 10 seconds),
// and the button is left on "Order Placed" so the order can't be submitted twice.

const ORDER_ANIMATION_MS = 10000;

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

        // The truck's path depends on the button's width
        button.style.setProperty("--w", `${button.offsetWidth}px`);
        button.classList.add("animate");

        setTimeout(() => {
            button.classList.remove("animate");
            button.classList.add("done");
            resolve();
        }, ORDER_ANIMATION_MS);
    });
}
