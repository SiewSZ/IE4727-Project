// Shared by every page with the site header. Shows what is in the PHP session:
// "Hi, <name>" with a Logout button (and an Admin link for admins) instead of Login/Register,
// and the number of items in the session cart on the cart icon.

document.addEventListener("DOMContentLoaded", async () => {
    let me;
    try {
        const res = await fetch("PHP/users/me.php");
        me = await res.json();
    } catch {
        return; // PHP not reachable (e.g. the page was opened as a file): keep the default header
    }

    setCartCount(me.cartCount);

    const loginBtn = document.getElementById("openLoginBtn");
    if (!me.loggedIn || !loginBtn) return;

    const userMenu = document.createElement("div");
    userMenu.className = "user-menu";
    userMenu.innerHTML = `
        <span class="user-greeting"><i class="fa-solid fa-user"></i> Hi, <strong></strong></span>
        ${me.role === "admin" ? `<a href="admin.html" class="user-link"><i class="fa-solid fa-gear"></i> Admin</a>` : ""}
        <form method="post" action="PHP/users/logout.php">
            <button type="submit" class="btn">Logout</button>
        </form>`;
    userMenu.querySelector("strong").textContent = me.username; // textContent, so a username can't inject HTML
    loginBtn.replaceWith(userMenu);
});

// Shows the total quantity in the cart on every cart icon
function setCartCount(count) {
    document.querySelectorAll(".cart-count").forEach((el) => { el.textContent = count; });
}

// Reads the session cart (no fields) or changes it (fields = { action, product_id, qty }),
// using PHP/shopping_cart/cart.php. Returns the updated cart: { items, count }.
// Throws the server's error message, e.g. when there isn't enough stock.
async function cartRequest(fields) {
    const options = {};
    if (fields) {
        options.method = "POST";
        options.body = new FormData();
        Object.entries(fields).forEach(([key, value]) => options.body.append(key, value));
    }

    const res = await fetch("PHP/shopping_cart/cart.php", options);
    let data;
    try {
        data = await res.json();
    } catch {
        throw new Error("The server sent an unexpected reply. Is MySQL running?");
    }
    if (!res.ok) throw new Error(data.error || `Request failed (${res.status})`);

    setCartCount(data.count);
    return data;
}
