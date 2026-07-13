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

		function renderPreview(preview) {
			if (!preview || !preview.success || !preview.image_url) {
				return;
			}

			result.innerHTML =
				'<img src="' +
				preview.image_url +
				'" alt="Product preview" />';

			status.textContent = 'Preview generated.';
		}

		function requestPreview(file, restoring) {
			var formData = new FormData();

			formData.append('product_id', productId);

			if (file) {
				formData.append('artwork_file', file);
			}

			if (!restoring) {
				button.disabled = true;
				status.textContent = 'Generating preview...';
				result.innerHTML = '';
			}

			return fetch(wpmtFrontend.restUrl + 'preview', {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': wpmtFrontend.restNonce
				}
			})
				.then(function (response) {
					return response.json().then(function (data) {
						return {
							ok: response.ok,
							status: response.status,
							data: data
						};
					});
				})
				.then(function (response) {
					var data = response.data;

					if (!response.ok) {
						throw new Error(
							data.error ||
							'Preview could not be generated.'
						);
					}

					if (data.status === 'no_artwork') {
						if (restoring) {
							status.textContent = '';
						}

						return;
					}

					var preview = data.results && data.results[0];

					if (!preview || !preview.success) {
						throw new Error(
							preview && preview.error
								? preview.error
								: 'Preview could not be generated.'
						);
					}

					renderPreview(preview);
				})
				.catch(function (error) {
					status.textContent = error.message ||
						'Something went wrong while generating the preview.';
				})
				.finally(function () {
					button.disabled = false;
				});
		}

		button.addEventListener('click', function () {
			var file = fileInput.files[0];

			if (!file) {
				status.textContent =
					'Please select an artwork file first.';
				return;
			}

			requestPreview(file, false);
		});

		/*
		 * Restore an existing artwork/preview on page load.
		 *
		 * If this product has no cached result yet but the
		 * session has artwork, the server renders it now.
		 */
		requestPreview(null, true);
	});
})();