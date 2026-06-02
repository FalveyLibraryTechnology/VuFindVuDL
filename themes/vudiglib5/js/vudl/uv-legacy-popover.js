// Move legacy link into breadcrumbs container

$("#legacy").appendTo(".breadcrumbs .container").removeClass("hidden");

// Show popover on mobile

if (
	navigator.platform.indexOf("iPad") !== -1 || // Detect iPad
	navigator.platform.indexOf("iPhone") !== -1 || // Detect iPhone
	navigator.platform.indexOf("iPod") !== -1 || // Detect iPhone
	navigator.platform.toLowerCase().indexOf("android") !== -1 || // Detect Android
	navigator.platform.toLowerCase().indexOf("mobile") !== -1 // Detect other mobile
) {
	showLegacyPopover();
}

function showLegacyPopover(
	question = "Having trouble?",
	recommendation = "Try the old viewer.",
) {
	const $popover = $(`
	  <div class="popover bottom">
		<div class="popover-content">
		  ${question} ${recommendation}
		</div>
	  </div>
	`);

	const legacyLink = document.getElementById("legacy");
	const box = legacyLink.getBoundingClientRect();

	$popover.css({
		display: "block",
		left: "auto",
		top: window.scrollY + box.bottom,
		right: window.innerWidth - box.right,
	});

	$popover.off("click").on("click", function (e) {
		$popover.hide();
		e.preventDefault();
		return false;
	});

	$(legacyLink).after($popover);
}
