'use strict';

window.addEventListener('load', function () {
	var btns = document.querySelectorAll('.cborg-toolbar-btn');
	btns.forEach(function(btn) {
		var tooltipText = btn.getAttribute('title');
		if (tooltipText !== null) {
			btn.setAttribute('data-tooltip', tooltipText);
			btn.removeAttribute('title');
		}
		btn.addEventListener('mouseenter', function () {
			var text = btn.getAttribute('data-tooltip');
			if (!text) return;

			var tip = document.createElement('div');
			tip.className = 'cborg-tooltip';
			tip.textContent = text;

			var rect = btn.getBoundingClientRect();
			var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
			tip.style.position = 'absolute';
			tip.style.top = (rect.top + scrollTop + btn.offsetHeight + 4) + 'px';
			tip.style.left = (rect.left + scrollLeft) + 'px';

			document.body.appendChild(tip);

			btn._cborgTooltipEl = tip;
		});
		btn.addEventListener('mouseleave', function () {
			var tip = btn._cborgTooltipEl;
			if (tip) {
				tip.remove();
				btn._cborgTooltipEl = null;
			}
		});
	});
});