// Loading Modal

const secondsUntilLoadingMessage = 2;
const secondsUntilRefreshMessage = 6;

const loadingModal = run(function bindLoadingModal() {
	const el = document.getElementById("loading-modal");
	const body = el.querySelector(".uv-modal__body");
	const refreshBtn = el.querySelector("#patience-refresh");

	refreshBtn.addEventListener(
		"click",
		() => {
			window.location.reload();
		},
		false,
	);

	function open(content = null) {
		if (content !== null) {
			body.innerHTML = content;
		}

		el.classList.remove("hidden");
	}

	function close() {
		el.classList.add("hidden");
		refreshBtn.classList.add("hidden");
	}

	document.querySelectorAll(".modal__close").forEach((el) => {
		el.addEventListener("click", () => close(), false);
	});

	return { el, body, open, close };
});

setTimeout(function showLoadingMessage() {
	if (!uvIsLoaded) {
		loadingModal.open();

		console.error(`UV not loaded after ${secondsUntilLoadingMessage} seconds.`);
	} else {
		loadingModal.close();
	}
}, secondsUntilLoadingMessage * 1000);

setTimeout(function showRefreshMessage() {
	if (!uvIsLoaded) {
		window.dispatchEvent(new CustomEvent("uvLoaded", {}));
		loadingModal.open(
			`<p>Sorry this took so long! We're investigating the cause of the problem.</p><p>If the item hasn't loaded, please refresh your browser.</p><p>If the problem persists, clearing your cache should help.</p>`,
		);
		loadingModal.el
			.querySelector("#patience-refresh")
			.classList.remove("hidden");

		console.error(`UV not loaded after ${secondsUntilRefreshMessage} seconds.`);
	} else {
		loadingModal.close();
	}
}, secondsUntilRefreshMessage * 1000);
