const applyDateGates = (function dateGatesModule() {
	function isTimely(now, hideBefore = null, hideAfter = null) {
		// Nothing set
		if (!hideBefore && !hideAfter) {
			return true;
		}

		// One-Directional

		// Before a hide-after
		// (timely) after-->
		if (!hideBefore) {
			return now <= new Date(hideAfter);
		}

		// After a hide-before
		// <--before (timely)
		if (!hideAfter) {
			return now >= new Date(hideBefore);
		}

		// Two-Directional

		const hideBeforeDate = new Date(hideBefore);
		const hideAfterDate = new Date(hideAfter);

		// Outside two crossing before/afters
		// (timely) after--> <--before (timely)
		if (hideBeforeDate > hideAfterDate) {
			return now >= hideBeforeDate || now <= hideAfterDate;
		}

		// Inside of bookends
		// <--before (timely) after-->
		return now >= hideBeforeDate && now <= hideAfterDate;
	}

	function applyDateGates(selector = document, date = Date.now()) {
		const els = (
			typeof selector === "string" ? document.querySelector(selector) : selector
		).querySelectorAll("[data-hide-before],[data-hide-after]");

		const now = new Date(date);
		for (let el of els) {
			const hideBefore = el.getAttribute("data-hide-before");
			const hideAfter = el.getAttribute("data-hide-after");

			if (isTimely(now, hideBefore, hideAfter)) {
				el.classList.remove("hidden");
				el.classList.add("is-timely");
			} else {
				el.classList.add("hidden");
			}
		}
	}

	return applyDateGates;
})();
