(function photoRowAnimate() {
    let springs = [];
    let isRunning = false;

    let lastFrame = null;
    function animate() {
        if (springs.length === 0) {
            isRunning = false;
            return;
        }
        requestAnimationFrame(animate);
        let now = Date.now();
        let dt = now - lastFrame;
        for (let i = 0; i < springs.length; i++) {
            let spring = springs[i];
            spring.x += ((spring.tx - spring.x) / 320) * dt;
            spring.el.style.left = Math.floor(spring.x) + "px";
        }
        for (let i = springs.length; i--; ) {
            if (springs[i].x - springs[i].tx < 1) {
                springs[i].el.style.left = springs[i].tx + "px";
                springs.splice(i, 1);
            }
        }
        lastFrame = now;
    }
    function startAnimation() {
        requestAnimationFrame(animate);
        lastFrame = Date.now();
        isRunning = true;
    }

    document.querySelectorAll(".photorow").forEach((row) => {
        let slot = 4;
        let items = row.querySelectorAll(".photorow__item");
        for (let i in items) {
            if (items.hasOwnProperty(i)) {
                let item = items[i];
                item.querySelector(".photorow__img").addEventListener(
                    "load",
                    () => {
                        // Skip offscreen things
                        if (slot > window.outerWidth) {
                            return;
                        }
                        // Show offscreen right
                        item.classList.add("js-photorow__loaded");
                        // Setup animation
                        springs.push({
                            x: slot * 100 + window.outerWidth,
                            tx: slot,
                            el: item,
                        });
                        slot += Math.round(item.scrollWidth + 4);
                        if (!isRunning) {
                            startAnimation();
                        }
                    },
                    false
                );
            }
        }
    });
})();
