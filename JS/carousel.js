document.addEventListener("DOMContentLoaded", () => {
    // Brand marquee: duplicate the logo list so the CSS animation loops seamlessly
    const brandTrack = document.querySelector("#brandMarquee .brand-track");
    if (brandTrack) {
        const copy = brandTrack.querySelector(".brand-list").cloneNode(true);
        copy.setAttribute("aria-hidden", "true");
        copy.querySelectorAll("img").forEach((img) => (img.alt = ""));
        brandTrack.appendChild(copy);
    }

    const carousel = document.getElementById("heroCarousel");
    if (!carousel) return;

    const track = carousel.querySelector(".carousel-track");
    const slides = carousel.querySelectorAll(".carousel-slide");
    const prevBtn = document.getElementById("carouselPrev");
    const nextBtn = document.getElementById("carouselNext");
    const counter = document.getElementById("carouselCounter");

    const AUTOPLAY_DELAY = 5000;
    let current = 0;
    let timer = null;

    const goTo = (index) => {
        current = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${current * 100}%)`;
        counter.textContent = `${current + 1}/${slides.length}`;
    };

    const startAutoplay = () => {
        stopAutoplay();
        timer = setInterval(() => goTo(current + 1), AUTOPLAY_DELAY);
    };

    const stopAutoplay = () => {
        if (timer) clearInterval(timer);
    };

    prevBtn.addEventListener("click", () => {
        goTo(current - 1);
        startAutoplay();
    });

    nextBtn.addEventListener("click", () => {
        goTo(current + 1);
        startAutoplay();
    });

    // Pause while the user is looking at a slide
    carousel.addEventListener("mouseenter", stopAutoplay);
    carousel.addEventListener("mouseleave", startAutoplay);

    goTo(0);
    startAutoplay();
});
