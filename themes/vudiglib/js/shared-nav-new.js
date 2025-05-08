const LOCAL_JSON = "/themes/vudiglib/js/navigation.json";
const LIVE_JSON =
	"https://library.villanova.edu/application/themes/falvey_2018/js/navigation.json";

const HOVER_DELAY = 200;

document.addEventListener("DOMContentLoaded", () => {
	fetch(LOCAL_JSON)
		.then(async (res) => buildSharedNav(await res.json()))
		.catch(() => {
			fetch(LIVE_JSON).then(async (res) => buildSharedNav(await res.json()));
		});
});

// Load header drop-downs
function buildSharedNav(json) {
	const headerNav = document.getElementById("falvey-nav");
	if (headerNav) {
		headerNav.replaceChildren(...buildHeader(json));

		headerNav.classList.remove("is-uninitialized");
		headerNav.classList.add("is-initialized");

		document.dispatchEvent(
			new CustomEvent("shared-nav:init", { detail: { header: headerNav } }),
		);

		bindHoverAndTap(headerNav);

		syncHeaderHelpWithTab();
	}

	const footerLinks = document.getElementById("footer-links");
	if (footerLinks) {
		footerLinks.replaceChildren(buildFooterLinks(json));
	}
}

function syncHeaderHelpWithTab(tries = 1) {
	const helpHeaderEl = document.querySelector(".header-live-chat");
	const helpTabEl = document.querySelector(".libraryh3lp-tab a");

	if (helpHeaderEl === null || helpTabEl === null) {
		console.log("missing", helpHeaderEl, helpTabEl);
		if (tries < 5) {
			setTimeout(() => syncHeaderHelpWithTab(tries + 1), 300);
		}

		return;
	}

	// wait for chat tab to become visible
	const intersectionObserver = new IntersectionObserver(([helpTab]) => {
		if (helpTab.intersectionRatio > 0) {
			helpHeaderEl.style.display = "inline";
		} else {
			helpHeaderEl.style.display = "none";
		}
	});

	intersectionObserver.observe(helpTabEl);
}

function el(tagName, ...children) {
	const newElement = document.createElement(tagName);

	const attrs =
		String(children[0]) === "[object Object]" ? children.shift() : {};
	for (const [key, value] of Object.entries(attrs)) {
		newElement.setAttribute(key, value);
	}

	newElement.append(...children.flat());

	return newElement;
}
function curryTag(tagName) {
	return (...args) => el(tagName, ...args);
}

function buildHeader(json) {
	const div = curryTag("div");
	const ul = curryTag("ul");
	const li = curryTag("li");
	const a = curryTag("a");
	const button = curryTag("button");

	return [
		...Object.entries(json).map(([top, nav]) =>
			li(
				{ class: "header-nav__top-item" },
				a({ class: "header-nav__top-link", href: nav.root }, top),
				buildMenu(nav, top),
			),
		),
		headerChatItem(),
	];

	function buildMenu(nav, top) {
		return div(
			{ class: "header-nav__menu" },
			div({ class: "header-nav__mobile-heading", "aria-hidden": true }, top),
			button({ class: "nav-close-btn" }, "Close"),
			ul(
				Object.entries(nav.sections ?? {}).map(([heading, section]) =>
					li(
						{ class: "header-nav__section" },
						a({ class: "header-nav__heading", href: section.root }, heading),
						buildList(section),
					),
				),
			),
		);
	}

	function buildList(section) {
		const newSet = new Set(section.new ?? []);

		return ul(
			{ class: "header-nav__list" },
			Object.entries(section.header ?? {}).map(([label, href]) => {
				let itemClass = "header-nav__item";

				if (newSet.has(href)) {
					itemClass += " is-new";
				}

				return li(
					{ class: itemClass },
					a({ class: "header-nav__link", href: href }, label),
				);
			}),
		);
	}

	function headerChatItem() {
		const link = a(
			{
				class: "header-nav__top-link libraryh3lp",
				href: "https://library.villanova.edu/research/ask-librarian",
				jid: "villref@chat.libraryh3lp.com",
				role: "menuitem",
				"aria-haspopup": true,
				"aria-expanded": false,
			},
			"Live Chat",
		);

		link.addEventListener("click", (event) => {
			window.open(
				"https://libraryh3lp.com/chat/villref@chat.libraryh3lp.com?skin=7498&amp;theme=dcraven&amp;title=Ask%20a%20Librarian&amp;identity=Villanova",
				"chat",
				"resizable=1,width=300,height=400",
			);

			event.preventDefault();
			return false;
		});

		return li(
			{ class: "header-nav__top-item header-live-chat", style: "display:none" },
			link,
		);
	}
}

function buildFooterLinks(json) {
	const ul = (...args) => el("ul", ...args);
	const li = (...args) => el("li", ...args);
	const a = (...args) => el("a", ...args);

	const footer = ul(
		{ class: "footer-nav-links shared-nav" },
		Object.entries(json).map(([top, nav]) =>
			li(
				{ class: "footer-nav__top-item" },
				a({ class: "footer-nav__top-link", href: nav.root }, top),
				buildMenu(nav),
			),
		),
	);

	footer.querySelector(".footer-nav__top-item:first-of-type").innerHTML +=
		supportButtons();

	return footer;

	function buildMenu(nav) {
		return ul(
			{ class: "footer-nav__menu" },
			Object.entries(nav.sections ?? {}).map(([heading, section]) =>
				li(
					{ class: "footer-nav__item" },
					a({ class: "footer-nav__link", href: section.root }, heading),
				),
			),
		);
	}
}

function formatLink(url) {
	if (
		url.charAt(0) === "/" &&
		document.location.href.indexOf("//blog.library.villanova.edu") >= 0
	) {
		return `https://library.villanova.edu${url}`;
	}
	return url;
}

function supportButtons() {
	return (
		// '<a class="support-btn" href="https://give.villanova.edu/campaigns/33308/donations/new?a=6895295&designation=falveylibrary">Support Our Library</a>' +
		'<div class="social-media">' +
		'<a class="social-media-icon" href="http://www.facebook.com/FalveyLibrary"><img src="https://library.villanova.edu/application/themes/falvey_2018/images/32x32/facebook.png" alt="Facebook"/></a>' +
		'<a class="social-media-icon" href="http://twitter.com/FalveyLibrary"><img src="https://library.villanova.edu/application/themes/falvey_2018/images/32x32/x-twitter-32.jpg" alt="Twitter"/></a>' +
		'<a class="social-media-icon" href="http://instagram.com/villanovalibrary"><img src="https://library.villanova.edu/application/themes/falvey_2018/images/32x32/instagram.png" alt="Instagram"/></a>' +
		"</div>"
	);
}

function bindHoverAndTap(header) {
	let isMenuPass = false;

	function open(event, menu, button, submenu, index) {
		event.preventDefault();

		menu.classList.add("is-open");
		submenu.classList.add("is-open");
		button.setAttribute("aria-expanded", true);
		isMenuPass = true;
	}
	function openAndFocus(event, menu, button, submenu, index) {
		open(event, menu, button, submenu, index);

		submenu.focusedItem = 0;
		submenu.querySelector('[role="menuitem"]').focus();
	}

	function close(event, menu, button, submenu, index) {
		event.preventDefault();

		menu.classList.remove("is-open");
		submenu.classList.remove("is-open");
		button.setAttribute("aria-expanded", false);
	}
	function closeAndFocus(event, menu, button, submenu, index) {
		close(event, menu, button, submenu, index);
		button.focus();
	}

	function toggleAndFocus(event, menu, button, submenu, index) {
		const expanded = button.getAttribute("aria-expanded", false);

		const isExpanded = expanded === true || expanded === "true";

		if (isExpanded) {
			closeAndFocus(event, menu, button, submenu, index);
		} else {
			openAndFocus(event, menu, button, submenu, index);
		}

		return !isExpanded;
	}

	const menuList = header.querySelectorAll(".header-nav__top-item");

	menuList.forEach((menu, index) => {
		const SEL_MENU_BTN = ".header-nav__top-link";
		const SEL_SUBMENU = ".header-nav__menu";
		const SEL_CLOSE_BTN = ".nav-mobile-close-btn,.nav-close-btn";

		if (menu.isKeyBound) {
			return;
		}

		menu.setAttribute("role", "menu");

		for (const li of menu.querySelectorAll("li")) {
			li.setAttribute("role", "none");
		}

		const button = menu.querySelector(SEL_MENU_BTN);

		button.setAttribute("role", "menuitem");
		button.setAttribute("aria-haspopup", true);
		button.setAttribute("aria-expanded", false);

		// Keyboard control
		button.addEventListener("keydown", (e) => {
			switch (e.key) {
				case " ":
				case "Enter":
					toggleAndFocus(e, menu, button, submenu, index);
					break;

				case "Left":
				case "ArrowLeft":
					e.preventDefault();
					focusNextMenu(index - 1);
					break;

				case "Right":
				case "ArrowRight":
					e.preventDefault();
					focusNextMenu(index + 1);
					break;

				case "Up":
				case "ArrowUp":
					if (toggleAndFocus(e, menu, button, submenu, index)) {
						focusPrevMenuitem(e);
					}
					break;

				case "Down":
				case "ArrowDown":
					openAndFocus(e, menu, button, submenu, index);
					break;
			}
		});

		const submenu = menu.querySelector(SEL_SUBMENU);

		if (!submenu) {
			return;
		}

		const menuitems = submenu.querySelectorAll("a");

		for (const a of menuitems) {
			a.setAttribute("role", "menuitem");
		}

		// Hover delay
		let hoverTimeout = null;
		button.addEventListener("mouseenter", (e) => {
			if (isMenuPass) {
				clearTimeout(hoverTimeout);
				open(e, menu, button, submenu, index);
				return;
			}

			hoverTimeout = setTimeout(
				() => open(e, menu, button, submenu, index),
				HOVER_DELAY,
			);
		});
		menu.addEventListener("mouseleave", (e) => {
			if (e.relatedTarget?.closest(".libraryh3lp")) {
				hoverTimeout = setTimeout(
					() => close(e, menu, button, submenu, index),
					HOVER_DELAY,
				);
				isMenuPass = true;
				return;
			}

			clearTimeout(hoverTimeout);
			close(e, menu, button, submenu, index);
			isMenuPass = isMenuPass && header.contains(e.target);
		});
		submenu.addEventListener("mouseenter", (e) => {
			clearTimeout(hoverTimeout);
			open(e, menu, button, submenu, index);
		});

		header.addEventListener("mouseleave", (e) => {
			clearTimeout(hoverTimeout);
			close(e, menu, button, submenu, index);
			isMenuPass = false;
		});

		submenu.addEventListener("keydown", (e) => {
			switch (e.key) {
				case "Esc":
				case "Escape":
					closeAndFocus(e, menu, button, submenu, index);
					button.focus();
					break;

				case "Up":
				case "ArrowUp":
					focusPrevMenuitem(e);

					break;

				case "Down":
				case "ArrowDown":
					focusNextMenuitem(e);

					break;

				case "Tab":
					if (e.shiftKey) {
						focusPrevMenuitem(e);
					} else {
						focusNextMenuitem(e);
					}
			}
		});

		// Close button
		for (const btn of menu.querySelectorAll(SEL_CLOSE_BTN) ?? []) {
			btn.addEventListener(
				"click",
				(e) => close(e, menu, button, submenu, index),
				false,
			);
		}

		// Phone interactions
		button.addEventListener(
			"click",
			(e) => {
				if (window.innerWidth < 768) {
					toggleAndFocus(e, menu, button, submenu, index);
				}
			},
			false,
		);

		// Swipe
		const touchStartPoints = {};
		menu.addEventListener("touchstart", (event) => {
			for (const touch of event.changedTouches) {
				touchStartPoints[touch.identifier] = {
					x: touch.screenX,
					y: touch.screenY,
				};
			}
		});
		document.addEventListener("touchcancel", (event) => {
			for (const touch of event.changedTouches) {
				delete touchStartPoints[touch.identifier];
			}
		});
		menu.addEventListener("touchend", (event) => {
			for (const touch of event.changedTouches) {
				if (!(touch.identifier in touchStartPoints)) {
					continue;
				}

				const startPoint = touchStartPoints[touch.identifier];
				const distX = Math.abs(touch.screenX - startPoint.x);
				const distY = Math.abs(touch.screenY - startPoint.y);

				if (distX >= 60 && distY < distX / 2) {
					let nextIndex = index + (touch.screenX < startPoint.x ? 1 : -1);

					// wrap while avoiding live chat
					if (nextIndex < 0) nextIndex = 3;
					if (nextIndex > 3) nextIndex = 0;

					close(event, menu, button, submenu, index);
					menuList[nextIndex].querySelector(SEL_MENU_BTN).click();
				}

				delete touchStartPoints[touch.identifier];
			}
		});

		function focusNextMenu(nextIndex) {
			menuList[(nextIndex + menuList.length) % menuList.length]
				.querySelector(SEL_MENU_BTN)
				.focus();
		}

		function focusNextMenuitem(e) {
			e.preventDefault();

			submenu.focusedItem = (submenu.focusedItem + 1) % menuitems.length;

			menuitems[submenu.focusedItem].focus();
		}

		function focusPrevMenuitem(e) {
			e.preventDefault();

			submenu.focusedItem =
				(submenu.focusedItem + menuitems.length - 1) % menuitems.length;

			menuitems[submenu.focusedItem].focus();
		}

		menu.isKeyBound = true;
	});
}

// Triggered after navigation load to allow page to render before finding elements
(function loadPlaceholders() {
	const selectEl = document.getElementById("searchForm_type");
	if (!selectEl) {
		return;
	}
	const search = document.getElementById("searchForm_lookfor");
	const srLabel = document.getElementById("sr-search-label");
	const placeholders = {
		"VuFind:Combined|":
			"books, articles, guides, library site, almost anything",
		"VuFind:Solr|AllFields": "books, dvds, CDs, other media",
		"VuFind:Solr|Title": "titles of books, dvds, CDs, other media",
		"VuFind:Solr|JournalTitle": "titles of journals in the library",
		"VuFind:Solr|Author": "authors of books, dvds, CDs, other media",
		"VuFind:Solr|Subject": "subjects of books, dvds, CDs, other media",
		"VuFind:Solr|CallNumber": "call numbers of books, dvds, CDs, other media",
		"VuFind:Solr|ISN": "ISBNs of books, dvds, CDs, other media",
		"VuFind:Solr|tag": "community tags of books, dvds, CDs, other media",
		"VuFind:Summon|AllFields": "electronic articles, newspapers, and more",
		"VuFind:Summon|Title":
			"titles of electronic articles, newspapers, and more",
		"VuFind:Summon|Author":
			"authors of electronic articles, newspapers, and more",
		"VuFind:Summon|SubjectTerms":
			"subjects of electronic articles, newspapers, and more",
		"VuFind:WorldCat|srw.kw": "books and media from other libraries",
		"VuFind:WorldCat|srw.ti:srw.se":
			"titles of books and media from other libraries",
		"VuFind:WorldCat|srw.au": "authors of books and media from other libraries",
		"VuFind:WorldCat|srw.su":
			"subjects of books and media from other libraries",
		"VuFind:WorldCat|srw.dd:srw.lc":
			"call numbers of books and media from other libraries",
		"VuFind:WorldCat|srw.bn:srw.in":
			"ISBNs of books and media from other libraries",
		"VuFind:SolrWeb|AllFields": "all pages on the library website",
		"VuFind:SolrWeb|Guides": "course and subjects guides from our librarians",
		"External:http://digital.library.villanova.edu/Search/Results?lookfor=":
			"digitally archived books, artifacts, university papers",
	};

	function typePlaceholder(text, index = 0) {
		search.setAttribute("placeholder", text.substr(0, index));
		if (index < text.length) {
			requestAnimationFrame(function nextItemType() {
				typePlaceholder(text, index + 2);
			});
		}
	}
	changePlaceholder = function changePlaceholder(text) {
		const defaultText = "Search the library";

		// Initial or event
		let newText = text;
		if (typeof text === "undefined" || typeof text !== "string") {
			newText =
				typeof placeholders[selectEl.value] !== "undefined"
					? placeholders[selectEl.value]
					: defaultText;
		}

		// Set placeholder
		if (srLabel) {
			srLabel.innerHTML = newText;
		}

		typePlaceholder(newText);
	};
	selectEl.addEventListener("change", changePlaceholder, false);
	changePlaceholder(search.dataset?.placeholder);
})();
