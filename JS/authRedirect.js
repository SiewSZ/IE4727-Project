// The PHP login/register handlers send their result back to index.html in the query string.
// Messages are shown on login.html, so forward anything except a successful login there.
(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.has("login_error") || params.has("register_error") || params.has("register")) {
        window.location.replace("login.html" + window.location.search);
    }
})();
