// Cart page (order.php). PHP draws the cart and does the ordering (PHP/orders/place.php);
// this file shows the "New address" fields when that option is picked, checks them before
// the checkout form is sent, and plays the Place Order animation (JS/order-button.js).
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("checkoutForm");
    if (!form) return;

    const addressForm = document.getElementById("addressForm");
    const newLabel = document.getElementById("newLabel");
    const newAddress = document.getElementById("newAddress");
    const orderError = document.getElementById("orderError");
    const checkoutBtn = document.getElementById("checkoutBtn");

    const newChosen = () => form.elements.address.value === "new";

    // The new address fields are only needed (and only shown) for "New address"
    const showAddressForm = () => {
        addressForm.hidden = !newChosen();
        if (newChosen()) newLabel.focus();
    };
    form.querySelectorAll('input[name="address"]').forEach((radio) => {
        radio.addEventListener("change", showAddressForm);
    });
    addressForm.hidden = !newChosen();

    const showError = (message) => {
        orderError.textContent = message;
        orderError.hidden = message === "";
    };

    // Checks HTML5 can't do here: the fields are only required when "New address" is picked,
    // and spaces alone don't count as an address
    const checkAddress = () => {
        if (!newChosen()) return "";
        if (newLabel.value.trim() === "" || newAddress.value.trim() === "") {
            return "Please fill in both new address fields, or pick a saved address.";
        }
        if (newAddress.value.trim().length < 10) {
            return "Please enter the full delivery address.";
        }
        return "";
    };

    [newLabel, newAddress].forEach((input) => {
        input.addEventListener("input", () => {
            if (!orderError.hidden) showError(checkAddress());
        });
    });

    form.addEventListener("submit", (e) => {
        e.preventDefault();
        const error = checkAddress();
        showError(error);
        if (error) {
            (newLabel.value.trim() === "" ? newLabel : newAddress).focus();
            return;
        }
        // Send the form once the button has finished its "Placing your order..." animation
        animateOrderButton(checkoutBtn).then(() => form.submit());
    });
});
