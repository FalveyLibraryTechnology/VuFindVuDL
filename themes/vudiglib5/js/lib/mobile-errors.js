import el from "./el.mjs";

let listEl = null;
window.addEventListener(
	"error",
	(e) => {
		const li = el("li", e.message);

		if (listEl === null) {
			listEl = el("ul");
			const detailsEl = el("details", el("summary", "Errors"), listEl);

			document.body.append(detailsEl);
		}

		listEl.append(li);
	},
	false,
);
