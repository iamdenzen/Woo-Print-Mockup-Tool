(function ($) {
	'use strict';

	function normalizePlacementData(raw) {
		if (!raw) {
			return {};
		}

		try {
			var parsed = JSON.parse(raw);

			if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
				return parsed;
			}
		} catch (e) {
			return {};
		}

		return {};
	}

	function saveRectangle($stage, $rect) {
		var stageWidth = $stage.width();
		var stageHeight = $stage.height();

		if (!stageWidth || !stageHeight) {
			return;
		}

		var data = {
			type: 'rectangle',
			left: Number(((parseFloat($rect.css('left')) || 0) / stageWidth).toFixed(6)),
			top: Number(((parseFloat($rect.css('top')) || 0) / stageHeight).toFixed(6)),
			width: Number(($rect.outerWidth() / stageWidth).toFixed(6)),
			height: Number(($rect.outerHeight() / stageHeight).toFixed(6)),
			stage_width: Math.round(stageWidth),
			stage_height: Math.round(stageHeight)
		};

		$('#wpmt_placement_data').val(JSON.stringify(data));
	}

	function initRectangleEditor() {
		var $canvas = $('.wpmt-placement-canvas');
		var imageUrl = $canvas.data('image-url');

		if (!$canvas.length || !imageUrl) {
			return;
		}

		var $empty = $canvas.find('.wpmt-editor-empty');
		var $stage = $canvas.find('.wpmt-editor-stage');
		var $image = $canvas.find('.wpmt-editor-image');
		var $rect = $canvas.find('.wpmt-editor-rect');
		var placement = normalizePlacementData($('#wpmt_placement_data').val());

		$empty.hide();
		$stage.show();
		$image.attr('src', imageUrl);

		$image.on('load', function () {
			if (placement.type === 'rectangle' && placement.width && placement.height) {
				var stageWidth = $stage.width();
				var stageHeight = $stage.height();

				var left = placement.left !== undefined
					? placement.left * stageWidth
					: placement.x || 0;

				var top = placement.top !== undefined
					? placement.top * stageHeight
					: placement.y || 0;

				var width = placement.left !== undefined
					? placement.width * stageWidth
					: placement.width;

				var height = placement.top !== undefined
					? placement.height * stageHeight
					: placement.height;

				$rect.css({
					left: left + 'px',
					top: top + 'px',
					width: width + 'px',
					height: height + 'px',
					display: 'block'
				});
			} else {
				$rect.css({
					left: '40px',
					top: '40px',
					width: '180px',
					height: '90px',
					display: 'block'
				});

				saveRectangle($stage, $rect);
			}
		});

		var dragging = false;
		var resizing = false;
		var startX = 0;
		var startY = 0;
		var startLeft = 0;
		var startTop = 0;
		var startWidth = 0;
		var startHeight = 0;

		$rect.on('mousedown', function (event) {
			event.preventDefault();

			var rectOffset = $rect.offset();
			var rightEdge = rectOffset.left + $rect.outerWidth();
			var bottomEdge = rectOffset.top + $rect.outerHeight();

			startX = event.pageX;
			startY = event.pageY;
			startLeft = parseFloat($rect.css('left')) || 0;
			startTop = parseFloat($rect.css('top')) || 0;
			startWidth = $rect.outerWidth();
			startHeight = $rect.outerHeight();

			resizing = event.pageX > rightEdge - 14 && event.pageY > bottomEdge - 14;
			dragging = !resizing;
		});

		$(document).on('mousemove.wpmtEditor', function (event) {
			if (!dragging && !resizing) {
				return;
			}

			var deltaX = event.pageX - startX;
			var deltaY = event.pageY - startY;

			if (dragging) {
				$rect.css({
					left: Math.max(0, startLeft + deltaX) + 'px',
					top: Math.max(0, startTop + deltaY) + 'px'
				});
			}

			if (resizing) {
				$rect.css({
					width: Math.max(20, startWidth + deltaX) + 'px',
					height: Math.max(20, startHeight + deltaY) + 'px'
				});
			}

			saveRectangle($stage, $rect);
		});

		$(document).on('mouseup.wpmtEditor', function () {
			dragging = false;
			resizing = false;
		});
	}

	$(function () {
		var $placementData = $('#wpmt_placement_data');

		if (!$placementData.length) {
			return;
		}

		var placement = normalizePlacementData($placementData.val());

		if (!Object.keys(placement).length) {
			$placementData.val(JSON.stringify({
				type: $('#wpmt_placement_type').val() || 'rectangle',
				x: 0,
				y: 0,
				width: 0,
				height: 0
			}));
		}

		$('#wpmt_placement_type').on('change', function () {
			var type = $(this).val();

			if (type === 'perspective') {
				$placementData.val(JSON.stringify({
					type: 'perspective',
					points: [
						{ x: 0, y: 0 },
						{ x: 0, y: 0 },
						{ x: 0, y: 0 },
						{ x: 0, y: 0 }
					]
				}));
				return;
			}

			$placementData.val(JSON.stringify({
				type: 'rectangle',
				x: 0,
				y: 0,
				width: 0,
				height: 0
			}));
		});

		var mediaFrame;

		$('.wpmt-select-mockup-image').on('click', function (event) {
			event.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: 'Select Mockup Image',
				button: {
					text: 'Use this image'
				},
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				var imageUrl = attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;

				$('#wpmt_mockup_image_id').val(attachment.id);

				$('.wpmt-mockup-image-preview').html(
					'<img src="' + imageUrl + '" alt="" />'
				);

				$('.wpmt-remove-mockup-image').show();
			});

			mediaFrame.open();
		});

		$('.wpmt-remove-mockup-image').on('click', function (event) {
			event.preventDefault();

			$('#wpmt_mockup_image_id').val('');
			$('.wpmt-mockup-image-preview').empty();
			$(this).hide();
		});

		initRectangleEditor();
	});
})(jQuery);