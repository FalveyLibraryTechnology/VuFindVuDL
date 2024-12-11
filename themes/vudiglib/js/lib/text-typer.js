function createTextAnimator(selector, attr = "innerHTML") {
    const el = document.querySelector(selector);

    if (!el) {
        throw Error(`Element not found: ${selector}`);
    }

    let currentText = null, nextText = null;

    function deleteText(index = null) {
        if (index === null) {
            index = currentText.length - 2;
        }

        if (index > 0) {
            requestAnimationFrame(function nextType() {
                deleteText(Math.max(0, index - 2));
            });

            if (attr.startsWith("inner")) {
                el[attr] = currentText.substr(0, index);
            } else {
                el.setAttribute(attr, currentText.substr(0, index));
            }
        } else {
            typeText();
        }
    }

    function typeText(index = 0) {
        if (index < nextText.length) {
            requestAnimationFrame(function nextType() {
                typeText(index + 1);
            });
        } else {
            currentText = nextText;
        }
        el[attr] = nextText.substr(0, index);
    }

    return function replace(text) {
        nextText = text;
        if (currentText !== null) {
            if (nextText != currentText) {
                deleteText();
            }
        } else {
            typeText();
        }
    }
};
