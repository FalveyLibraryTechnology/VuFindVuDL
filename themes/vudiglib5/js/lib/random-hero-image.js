function displayRandomImage(json) {
    const images = JSON.parse(json);

    let imageEl = document.getElementById("hero__image");
    const citationEl = document.querySelector(".hero__citation");

    const animateCitation = createTextAnimator(".hero__citation", "innerText");

    const cycle = images.map((_, i) => i);
    cycle.sort((a, b) => Math.random() < 0.5 ? -1 : 1);

    let index = 0;
    function renderImage() {
        let { src, href, title } = images[cycle[index]];
        index = (index + 1) % cycle.length;

        let newImage = imageEl.cloneNode(true);
        newImage.src = src;
        imageEl.parentNode.replaceChild(newImage, imageEl);
        imageEl = newImage;

        animateCitation(title);
        citationEl.href = href;

        setTimeout(renderImage, 30000); // wait for next cycle
    }

    renderImage();
}
