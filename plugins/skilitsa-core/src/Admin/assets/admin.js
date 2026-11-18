/**
 * Keeps the colour picker and its hex box showing the same value.
 *
 * The text box is the one that submits: a picker alone cannot be typed into,
 * pasted into, or read out, and a brand colour is something people arrive with
 * already written down.
 */
(function () {
	'use strict';

	var pickers = document.querySelectorAll('[data-skilitsa-color-for]');

	Array.prototype.forEach.call(pickers, function (picker) {
		var hex = document.getElementById(picker.getAttribute('data-skilitsa-color-for'));

		if (!hex) {
			return;
		}

		picker.addEventListener('input', function () {
			hex.value = picker.value;
		});

		hex.addEventListener('input', function () {
			if (/^#[0-9a-fA-F]{6}$/.test(hex.value.trim())) {
				picker.value = hex.value.trim();
			}
		});
	});
})();
