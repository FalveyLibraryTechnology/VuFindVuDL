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
$("#legacy")
	.off("show.bs.popover")
	.on("show.bs.popover", function bounce(e) {
		let $link = $(this);
		let el;
		let y = 32;
		let dy = 0;
		let frames = 60;
		function bounceFrame() {
			if (!el) {
				el = $link.find(".popover");
			}
			if (el) {
				el.css("transform", "translateY(" + y + "px)");
				dy -= 0.5; // gravity
				dy *= 0.95; // friction
				y += dy;
				if (y < 0) {
					dy = -dy; // bounce
					y = 0;
				} else {
				}
			}
			if (frames--) {
				requestAnimationFrame(bounceFrame);
			} else {
				el.css("transform", "translateY(0)");
			}
		}
		requestAnimationFrame(bounceFrame);
	});

$("#legacy").appendTo(".breadcrumb").removeClass("hidden");
$(".breadcrumb").addClass("clearfix");
if (
	navigator.platform.indexOf("iPad") !== -1 || // Detect iPad
	navigator.platform.indexOf("iPhone") !== -1 || // Detect iPhone
	navigator.platform.indexOf("iPod") !== -1 || // Detect iPhone
	navigator.platform.toLowerCase().indexOf("android") !== -1 || // Detect Android
	navigator.platform.toLowerCase().indexOf("mobile") !== -1 // Detect other mobile
) {
	showLegacyPopover();
}
