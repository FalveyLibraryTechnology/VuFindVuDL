const run = (fn) => fn();
const disabled = (fn) => null;
const pause = (ms = 1000) => new Promise((done) => setTimeout(done, ms));

let uv = null;
let uvIsLoaded = false;
const uvEl = document.getElementById("uv");

function setupUV4(configUri, phpData, uvOptions) {
	const uvUrlAdapter = new UV.IIIFURLAdapter();

	const uvData = Object.assign({}, phpData, {
		collectionIndex: Number(uvUrlAdapter.get("c", 0)),
		manifestIndex: Number(uvUrlAdapter.get("m", 0)),
		sequenceIndex: Number(uvUrlAdapter.get("s", 0)),
		canvasIndex: Number(uvUrlAdapter.get("cv", phpData.currentIndex)),
		embedded: false,
		rotation: Number(uvUrlAdapter.get("r", 0)),
		xywh: uvUrlAdapter.get("xywh", ""),
	});

	uv = UV.init("uv", uvData);
	uvUrlAdapter.bindTo(uv);

	// DEBUG EVENTS
	// for (const e in UV.Events) {
	//   uv.on(UV.Events[e], () => console.log(e.trim()));
	// }

	uv.on(UV.Events.CREATED, function uvCreatedEvent() {
		// Loading popups

		uvIsLoaded = true;
		loadingModal.close();
		resizeUV();

		// Firefox audio bug

		const host = document.querySelector("#uv .uv-iiif-extension-host");
		if (host.classList.contains("uv-mediaelement-extension")) {
			if (host.classList.contains("browser-Firefox")) {
				showLegacyPopover(
					"Having trouble playing media in Firefox?",
					"Try using the Download button below, or switch to the old viewer.",
				);
			}
		}

		// Fix initial zoom

		const OSD = uv.get().extension.centerPanel; // OpenSeaDragon
		if (OSD && OSD.viewer) {
			let viewportSize = null;
			let fixingZoom = false;
			const targetCoverage = uvOptions.initialZoomMin ?? 1;

			OSD.viewer.addHandler("resize", (event) => {
				viewportSize = event.newContainerSize;
				fixingZoom = false;
			});

			OSD.viewer.addHandler("tile-drawn", (event) => {
				if (fixingZoom) {
					return;
				}

				fixingZoom = true;
				const zoomedSize = event.tiledImage.getSizeInWindowCoordinates();
				const filledRatio = Math.max(
					zoomedSize.x / viewportSize.x,
					zoomedSize.y / viewportSize.y,
				);

				if (filledRatio < targetCoverage) {
					const factor = targetCoverage / filledRatio;
					OSD.viewer.viewport.zoomBy(factor);
				}
			});
		}
	});

	uv.on(UV.Events.LOAD, async function uvLoadEvent(e) {
		const helper = uv._assignedContentHandler.extension.helper;

		// Fix paged layout
		if (helper.manifest.getViewingHint() === "paged") {
			document.querySelector("#uv .leftPanel .thumbs").classList.add("paged");
		}

		// Transcriptions
		const transcriptTypes = {
			"application/pdf": "pdf",
			"application/msword": "doc",
			"text/plain": "txt",
		};
		function getRenderings(json) {
			if (json && typeof json === "object") {
				// Fix for multi-pdf
				if (json["@type"] === "foaf:Document") {
					return [];
				}
				// Match?
				let found = [];
				if (
					"format" in json &&
					"label" in json &&
					json.format in transcriptTypes
				) {
					found.push(json);
				}
				// Children
				for (const [key, value] of Object.entries(json)) {
					if (key === "canvases") {
						continue;
					}
					found = found.concat(getRenderings(value));
				}
				return found;
			} else if (Array.isArray(json)) {
				return getRenderings(json);
			}
			return [];
		}

		let transcripts = [];
		const renderings = getRenderings(helper.manifest.__jsonld);
		// console.log("manifest", helper.manifest.__jsonld);
		for (const render of renderings) {
			transcripts.push({
				label: render.label,
				href: render["@id"],
				hint: transcriptTypes[render.format],
			});
		}

		const TRANSCRIPT_LIMIT = 5;
		if (transcripts.length > 0) {
			const item = document.createElement("div");
			item.classList.add("item", "_transcripts");

			const label = document.createElement("div");
			label.classList.add("label");
			label.innerHTML = "Featured Downloads";

			const values = document.createElement("div");
			values.classList.add("values");

			let transcriptCount = 0;
			for (const { label, href, hint } of transcripts) {
				values.innerHTML += `<div class="value"><a href="${href}" target="_new">${label} (${hint})</a></div>`;
				transcriptCount += 1;

				if (
					transcriptCount >= TRANSCRIPT_LIMIT &&
					transcripts.length >= TRANSCRIPT_LIMIT
				) {
					values.innerHTML += `<div class="value"><a id="featured__more-downloads" href="javascript:;"><em>More Downloads Available</em></a></div>`;
					break;
				}
			}

			item.append(label);
			item.append(values);

			// Function to add the list to the sidebar
			function addTranscriptsToRightPanel() {
				// console.log("addTranscriptsToRightPanel");
				const formatItem = document.querySelector(
					".item._format, .item._language",
				);
				if (formatItem) {
					formatItem.parentNode.insertBefore(item, formatItem);
				} else {
					console.error("No format -- cannot insert transcripts.");
				}

				// Add trigger when we have too many transcripts
				const moreDownloads = document.getElementById(
					"featured__more-downloads",
				);
				if (moreDownloads) {
					moreDownloads.addEventListener("click", (event) => {
						event.preventDefault();
						document.querySelector(".footerPanel #download-btn").click();
						return false;
					});
				}
			}

			RADIO.listen("re-render", (addedNodes) => {
				// Prevent double adding
				if (document.querySelector(".item._transcripts")) {
					return;
				}
				addTranscriptsToRightPanel();
			});
		}
	});

	uv.on(UV.Events.CONFIGURE, async function uvConfigEvent(event) {
		const { cb } = event;

		const configPromise = new Promise(async (resolve) => {
			const res = await fetch(configUri);
			const localConfig = await res.json();

			// Theater Mode for videos
			if ("mediaElementCenterPanel" in event.config.modules) {
				merge(localConfig, {
					modules: {
						footerPanel: {
							content: {
								exitFullScreen: "Exit Theater Mode",
								fullScreen: "Theater Mode",
							},
						},
					},
				});
			}

			resolve(localConfig); // this is merged with the base config
		});

		cb(configPromise);
	});

	// Better Download text
	waitFor("#download-btn").then(function waitForDownloadBtn() {
		document.querySelector("#download-btn .sr-only").innerText = "Downloads";
	});

	// License prettification
	run(function prettifyLicenseLink() {
		const licenseText = {
			"http://creativecommons.org/licenses/by-nc-nd/1.0/":
				"Creative Commons BY-NC-ND 1.0 Generic",
			"http://creativecommons.org/licenses/by-nc-nd/2.0/":
				"Creative Commons BY-NC-ND 2.0 Generic",
			"http://creativecommons.org/licenses/by-nc-nd/3.0/":
				"Creative Commons BY-NC-ND 3.0 Unported",
			"http://creativecommons.org/licenses/by-nc-nd/4.0/":
				"Creative Commons BY-NC-ND 4.0 International",
			"http://creativecommons.org/licenses/by-nc-sa/1.0/":
				"Creative Commons BY-NC-SA 1.0 Generic",
			"http://creativecommons.org/licenses/by-nc-sa/2.0/":
				"Creative Commons BY-NC-SA 2.0 Generic",
			"http://creativecommons.org/licenses/by-nc-sa/3.0/":
				"Creative Commons BY-NC-SA 3.0 Unported",
			"http://creativecommons.org/licenses/by-nc-sa/4.0/":
				"Creative Commons BY-NC-SA 4.0 International",
			"http://creativecommons.org/licenses/by-nc/1.0/":
				"Creative Commons BY-NC 1.0 Generic",
			"http://creativecommons.org/licenses/by-nc/2.0/":
				"Creative Commons BY-NC 2.0 Generic",
			"http://creativecommons.org/licenses/by-nc/3.0/":
				"Creative Commons BY-NC 3.0 Unported",
			"http://creativecommons.org/licenses/by-nc/4.0/":
				"Creative Commons BY-NC 4.0 International",
			"http://creativecommons.org/licenses/by-nd/1.0/":
				"Creative Commons BY-ND 1.0 Generic",
			"http://creativecommons.org/licenses/by-nd/2.0/":
				"Creative Commons BY-ND 2.0 Generic",
			"http://creativecommons.org/licenses/by-nd/3.0/":
				"Creative Commons BY-ND 3.0 Unported",
			"http://creativecommons.org/licenses/by-nd/4.0/":
				"Creative Commons BY-ND 4.0 International",
			"http://creativecommons.org/licenses/by-sa/1.0/":
				"Creative Commons BY-SA 1.0 Generic",
			"http://creativecommons.org/licenses/by-sa/2.0/":
				"Creative Commons BY-SA 2.0 Generic",
			"http://creativecommons.org/licenses/by-sa/3.0/":
				"Creative Commons BY-SA 3.0 Unported",
			"http://creativecommons.org/licenses/by-sa/4.0/":
				"Creative Commons BY-SA 4.0 International",
			"http://creativecommons.org/licenses/by/1.0/":
				"Creative Commons Attribution 1.0 Generic",
			"http://creativecommons.org/licenses/by/2.0/":
				"Creative Commons Attribution 2.0 Generic",
			"http://creativecommons.org/licenses/by/3.0/":
				"Creative Commons Attribution 3.0 Unported",
			"http://creativecommons.org/licenses/by/4.0/":
				"Creative Commons Attribution 4.0 International",
			"http://digital.library.villanova.edu/copyright.html":
				"Villanova University Copyright",
			"https://creativecommons.org/publicdomain/zero/1.0/": "CC0 1.0 Universal",
			"https://creativecommons.org/publicdomain/mark/1.0/":
				"Public Domain Mark",
		};

		RADIO.listen("re-render", (addedNodes) => {
			for (const node of addedNodes) {
				if (
					node instanceof HTMLAnchorElement &&
					node.closest("._license") // is child of .item._license
				) {
					node.innerHTML =
						licenseText[node.getAttribute("href")] ?? "Rights Information";
				}
			}
		});
	});
}

// Event controls

const RADIO = run(() => {
	const listeners = {};
	const flags = {};

	function unlisten(event, fn) {
		if (typeof listeners[event] === "undefined") {
			return;
		}

		listeners[event] = listeners[event].filter((listener) => listener !== fn);
	}

	function listen(event, fn, { once = false } = {}) {
		if (typeof listeners[event] === "undefined") {
			listeners[event] = [];
		}

		listeners[event].push(fn);

		if (event in flags) {
			fn(...flags[event]);
		}

		return () => unlisten(event, fn);
	}

	function emit(event, ...args) {
		if (typeof listeners[event] === "undefined") {
			return;
		}

		for (const fn of Array.from(listeners[event])) {
			fn(...args);
		}
	}

	function flag(event, ...args) {
		flags[event] = args;
		emit(event, ...args);
	}

	return { listen, emit, flag };
});

/** @DEBUG * /
RADIO.listen("re-render", (addedNodes) => console.log("re-render", addedNodes));
//*/

// DOM events

function waitFor(selector, container = uvEl) {
	return new Promise((resolve) => {
		// Use a MutationObserver to check if the element has been added
		const waitForCallback = (mutationsList, observer) => {
			for (const mutation of mutationsList) {
				for (const added of mutation.addedNodes) {
					if (added instanceof Element) {
						if (added.matches(selector)) {
							observer.disconnect();
							resolve(added);
						}

						const el = added.querySelector(selector);
						if (el) {
							observer.disconnect();
							resolve(el);
						}
					}
				}
			}
		};
		// Bind observer
		const observer = new MutationObserver(waitForCallback);
		observer.observe(container, { childList: true, subtree: true });
	});
}

// Sidebar re-render event
waitFor(".rightPanel").then((sidebar) => {
	const observer = new MutationObserver((mutationList, observer) => {
		let addedNodes = new Set();
		for (const mutation of mutationList) {
			for (const node of mutation.addedNodes) {
				addedNodes.add(node);
			}
		}
		if (addedNodes) {
			RADIO.flag("re-render", addedNodes);
		}
	});

	observer.observe(sidebar, { childList: true, subtree: true });
});

document.addEventListener("click", (event) => {
	// Log download usage
	if (_paq && event.target.matches(".download .content button")) {
		const urlParts = window.location.pathname.split("/");
		const recordID = urlParts.at(-1);
		_paq.push(["trackEvent", "UV Download", event.target.innerText, recordID]);
	}

	// Copy usage rights to download menu
	if (event.target.closest("#download-btn")) {
		waitFor(".overlay.download").then((overlay) => {
			if (overlay.querySelector(".attribution-text") === null) {
				overlay
					.querySelector(".footer")
					.append(document.querySelector(".attribution-text").cloneNode(true));
			}
		});
	}
});

// Resize functions

const $UV = $("#uv");
function resizeUV() {
	const height = window.innerWidth < 640
		? window.innerHeight - 40 // full size on mobile
		: window.innerHeight - $UV.offset().top;
	$UV.height(height);
	uv.resize();
}
window.addEventListener("resize", resizeUV);

// Utils

function merge(target, ...sources) {
	if (sources.length > 1) {
		return sources.reduce((merged, source) => merge(merged, source), target);
	}

	// https://github.com/lukeed/dset/blob/master/src/merge.js#L1C33-L16C2
	function deepSet(target, source) {
		if (typeof target !== 'object' || typeof source !== 'object')  {
			return source;
		}

		if (Array.isArray(target) && Array.isArray(source)) {
			for (let i = 0; i < source.length; i++) {
				target[i] = deepSet(target[i], source[i]);
			}
			return target;
		}

		for (const key in source) {
			if (!source.hasOwnProperty(key)) break;
			target[key] = deepSet(target[key], source[key]);
		}

		return target;
	}

	return deepSet(structuredClone(target), sources[0]);
}
