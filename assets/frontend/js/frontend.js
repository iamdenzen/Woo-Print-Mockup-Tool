(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var uploader = document.querySelector('.wpmt-artwork-uploader');

		if (!uploader) {
			return;
		}

		var productId = uploader.getAttribute('data-product-id');
		var fileInput = uploader.querySelector('.wpmt-artwork-file');
		var button = uploader.querySelector('.wpmt-generate-preview');
		var status = uploader.querySelector('.wpmt-preview-status');
		var result = uploader.querySelector('.wpmt-preview-result');

		button.addEventListener('click', function () {
			var file = fileInput.files[0];

			if (!file) {
				status.textContent = 'Please select an artwork file first.';
				return;
			}

			var formData = new FormData();
			formData.append('product_id', productId);
			formData.append('artwork_file', file);

			button.disabled = true;
			status.textContent = 'Generating preview...';
			result.innerHTML = '';

			fetch(WPMTFrontend.restUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': WPMTFrontend.nonce
				}
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					var preview = data.results && data.results[0];

					if (!preview || !preview.success) {
						status.textContent = preview && preview.error
							? preview.error
							: 'Preview could not be generated.';
						return;
					}

					status.textContent = 'Preview generated.';

					result.innerHTML =
						'<img src="' + preview.image_url + '" alt="Product preview" />';
				})
				.catch(function () {
					status.textContent = 'Something went wrong while generating the preview.';
				})
				.finally(function () {
					button.disabled = false;
				});
		});
	});
})();