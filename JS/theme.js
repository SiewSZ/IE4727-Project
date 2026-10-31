// Light / dark mode switch. <html> gets either "dark-mode" (CSS/dark.css, the default)
// or "light-mode" (CSS/light.css).
// Loaded in <head> without "defer" so the saved theme is applied before the page is drawn,
// which stops a flash of the dark header when a light-mode visitor opens a page.

const THEME_KEY = "theme";

// localStorage can be blocked (e.g. some private windows), so never let it break the page
function savedTheme() {
    try {
        return localStorage.getItem(THEME_KEY);
    } catch {
        return null;
    }
}

const setTheme = (light) => {
    document.documentElement.classList.toggle("light-mode", light);
    document.documentElement.classList.toggle("dark-mode", !light);
};

setTheme(savedTheme() === "light");

document.addEventListener("DOMContentLoaded", () => {
    const toggle = document.getElementById("themeToggle");
    if (!toggle) return;

    // A switch: "on" means light mode. The sliding knob itself is pure CSS.
    const showState = () => {
        const light = document.documentElement.classList.contains("light-mode");
        toggle.setAttribute("aria-checked", String(light));
        toggle.title = light ? "Switch to dark mode" : "Switch to light mode";
    };

    toggle.addEventListener("click", () => {
        const light = !document.documentElement.classList.contains("light-mode");
        setTheme(light);
        try {
            localStorage.setItem(THEME_KEY, light ? "light" : "dark");
        } catch {
            // Not saved, but the switch still works on this page
        }
        showState();
    });

    showState();
});
