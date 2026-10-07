document.addEventListener("DOMContentLoaded", async () => {
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

    // Build one slide per image found in Images/carousel/ (listed by PHP/carousel.php)
    try {
        const res = await fetch("PHP/carousel.php");
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const { images } = await res.json();
        images.forEach(({ src, alt }) => {
            // The slide shows the whole image; a blurred copy (--bg) fills any empty space around it
            const slide = document.createElement("div");
            slide.className = "carousel-slide";
            // Full URL: a relative url() inside a CSS variable would resolve from the CSS folder instead
            slide.style.setProperty("--bg", `url("${new URL(src, document.baseURI).href}")`);
            const img = document.createElement("img");
            img.src = src;
            img.alt = alt;
            slide.appendChild(img);
            track.appendChild(slide);
        });
    } catch (err) {
        console.error("Could not load carousel images:", err);
    }

    const slides = track.querySelectorAll(".carousel-slide");
    const controls = carousel.querySelector(".carousel-controls");

    // No images: hide the carousel. One image: nothing to scroll through.
    if (slides.length === 0) {
        carousel.hidden = true;
        return;
    }
    if (slides.length === 1) {
        controls.hidden = true;
        return;
    }

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
