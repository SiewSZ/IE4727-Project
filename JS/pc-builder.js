// PC Builder: re-check the build as soon as a part is chosen.
// The page works without this too: the "Check compatibility" button sends the same form.
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("builderForm");
    if (!form) return;

    form.querySelectorAll("select").forEach((select) => {
        select.addEventListener("change", () => {
            // Leave empty slots out of the URL, so a shared link stays short
            form.querySelectorAll("select").forEach((s) => { s.disabled = s.value === ""; });
            form.submit();
        });
    });
});
