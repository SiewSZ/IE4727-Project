document.addEventListener("DOMContentLoaded", () => {
    // Sort menu: apply as soon as a new order is picked (the Apply button is there for keyboard users too)
    const sortSelect = document.getElementById("dealSort");
    if (sortSelect) {
        sortSelect.addEventListener("change", () => sortSelect.form.submit());
    }

    // Shell Shocker countdown: the deal runs until midnight on the visitor's clock
    const countdown = document.getElementById("countdown");
    if (!countdown) return;

    const pad = (n) => String(n).padStart(2, "0");

    const tick = () => {
        const now = new Date();
        const midnight = new Date(now);
        midnight.setHours(24, 0, 0, 0);
        const secondsLeft = Math.floor((midnight - now) / 1000);

        const hours = Math.floor(secondsLeft / 3600);
        const minutes = Math.floor((secondsLeft % 3600) / 60);
        countdown.textContent = `${pad(hours)}:${pad(minutes)}:${pad(secondsLeft % 60)}`;
    };

    tick();
    setInterval(tick, 1000);
});
