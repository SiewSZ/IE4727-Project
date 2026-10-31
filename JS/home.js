// Scrolling product rows on the home page (Today's Deals, New Arrivals, Top picks).
// The rows can also be scrolled by touch or trackpad; the arrow buttons move about one screen of cards.
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".row-scroller").forEach((scroller) => {
        const track = scroller.querySelector(".row-track");
        const prev = scroller.querySelector(".row-btn.prev");
        const next = scroller.querySelector(".row-btn.next");

        // Hide an arrow when there is nothing more to scroll to on that side
        const updateButtons = () => {
            const maxScroll = track.scrollWidth - track.clientWidth - 2;
            prev.hidden = track.scrollLeft <= 2;
            next.hidden = track.scrollLeft >= maxScroll;
        };

        const scrollByPage = (direction) => {
            track.scrollBy({ left: direction * track.clientWidth * 0.85, behavior: "smooth" });
        };

        prev.addEventListener("click", () => scrollByPage(-1));
        next.addEventListener("click", () => scrollByPage(1));
        track.addEventListener("scroll", updateButtons, { passive: true });
        window.addEventListener("resize", updateButtons);
        updateButtons();
    });
});
