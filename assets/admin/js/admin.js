(function ($) {
	'use strict';

	var fabricCanvas = null;
	var backgroundImage = null;
	var rectangle = null;
	var perspectivePolygon = null;
	var perspectivePoints = [];
	var editorInitialized = false;
	var editorImageUrl = '';

	function parsePlacementData(raw) {
		if (!raw) {
			return {};
		}

		try {
			var parsed = JSON.parse(raw);

			if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
				return parsed;
			}
		} catch (error) {
			return {};
		}

		return {};
	}

	function clamp(value, min, max) {
		return Math.min(Math.max(value, min), max);
	}

	function getCanvasDimensions(image) {
		var maxWidth = 760;
		var maxHeight = 620;
		var width = image.width || 1;
		var height = image.height || 1;
		var scale = Math.min(maxWidth / width, maxHeight / height, 1);

		return {
			width: Math.round(width * scale),
			height: Math.round(height * scale),
			scale: scale
		};
	}

	function clearPlacementObjects() {
		if (!fabricCanvas) {
			return;
		}

		if (rectangle) {
			fabricCanvas.remove(rectangle);
			rectangle = null;
		}

		perspectivePoints.forEach(function (point) {
			fabricCanvas.remove(point);
		});

		perspectivePoints = [];

		if (perspectivePolygon) {
			fabricCanvas.remove(perspectivePolygon);
			perspectivePolygon = null;
		}

		fabricCanvas.discardActiveObject();
		fabricCanvas.requestRenderAll();
	}

	function saveRectangle() {
		if (!fabricCanvas || !rectangle) {
			return;
		}

		var canvasWidth = fabricCanvas.getWidth();
		var canvasHeight = fabricCanvas.getHeight();

		var left = rectangle.left || 0;
		var top = rectangle.top || 0;
		var width = rectangle.getScaledWidth();
		var height = rectangle.getScaledHeight();

		var data = {
			type: 'rectangle',
			left: Number((left / canvasWidth).toFixed(6)),
			top: Number((top / canvasHeight).toFixed(6)),
			width: Number((width / canvasWidth).toFixed(6)),
			height: Number((height / canvasHeight).toFixed(6))
		};

		$('#wpmt_placement_type').val('rectangle');
		$('#wpmt_placement_data').val(JSON.stringify(data));
	}

	function getPerspectiveCoordinates() {
		var canvasWidth = fabricCanvas.getWidth();
		var canvasHeight = fabricCanvas.getHeight();

		return perspectivePoints.map(function (point) {
			return {
				x: Number(((point.left || 0) / canvasWidth).toFixed(6)),
				y: Number(((point.top || 0) / canvasHeight).toFixed(6))
			};
		});
	}

	function savePerspective() {
		if (!fabricCanvas || perspectivePoints.length !== 4) {
			return;
		}

		var data = {
			type: 'perspective',
			points: getPerspectiveCoordinates()
		};

		$('#wpmt_placement_type').val('perspective');
		$('#wpmt_placement_data').val(JSON.stringify(data));
	}

	function updatePerspectivePolygon() {
		if (
			!fabricCanvas ||
			!perspectivePolygon ||
			perspectivePoints.length !== 4
		) {
			return;
		}

		var points = perspectivePoints.map(function (point) {
			return {
				x: point.left || 0,
				y: point.top || 0
			};
		});

		perspectivePolygon.set({
			points: points,
			left: 0,
			top: 0,
			width: fabricCanvas.getWidth(),
			height: fabricCanvas.getHeight(),
			pathOffset: {
				x: 0,
				y: 0
			}
		});

		perspectivePolygon.setCoords();
		perspectivePolygon.sendToBack();

		if (backgroundImage) {
			backgroundImage.sendToBack();
			perspectivePolygon.bringForward();
		}

		perspectivePoints.forEach(function (point) {
			point.bringToFront();
		});

		fabricCanvas.requestRenderAll();
		savePerspective();
	}

	function createRectangle(placement) {
		clearPlacementObjects();

		var canvasWidth = fabricCanvas.getWidth();
		var canvasHeight = fabricCanvas.getHeight();

		var left = 0.2 * canvasWidth;
		var top = 0.3 * canvasHeight;
		var width = 0.35 * canvasWidth;
		var height = 0.2 * canvasHeight;

		if (
			placement &&
			placement.type === 'rectangle' &&
			typeof placement.left !== 'undefined'
		) {
			left = placement.left * canvasWidth;
			top = placement.top * canvasHeight;
			width = placement.width * canvasWidth;
			height = placement.height * canvasHeight;
		}

		rectangle = new fabric.Rect({
			left: left,
			top: top,
			width: width,
			height: height,
			fill: 'rgba(34, 113, 177, 0.15)',
			stroke: '#2271b1',
			strokeWidth: 2,
			transparentCorners: false,
			cornerColor: '#2271b1',
			cornerStrokeColor: '#ffffff',
			borderColor: '#2271b1',
			cornerSize: 12,
			lockRotation: true,
			hasRotatingPoint: false
		});

		rectangle.setControlsVisibility({
			mtr: false
		});

		rectangle.on('moving', constrainRectangle);
		rectangle.on('scaling', constrainRectangle);
		rectangle.on('modified', saveRectangle);

		fabricCanvas.add(rectangle);
		fabricCanvas.setActiveObject(rectangle);
		fabricCanvas.requestRenderAll();

		saveRectangle();
	}

	function constrainRectangle() {
		if (!rectangle || !fabricCanvas) {
			return;
		}

		var canvasWidth = fabricCanvas.getWidth();
		var canvasHeight = fabricCanvas.getHeight();
		var width = rectangle.getScaledWidth();
		var height = rectangle.getScaledHeight();

		if (width > canvasWidth) {
			rectangle.scaleX = canvasWidth / rectangle.width;
			width = canvasWidth;
		}

		if (height > canvasHeight) {
			rectangle.scaleY = canvasHeight / rectangle.height;
			height = canvasHeight;
		}

		rectangle.left = clamp(rectangle.left || 0, 0, canvasWidth - width);
		rectangle.top = clamp(rectangle.top || 0, 0, canvasHeight - height);

		rectangle.setCoords();
		saveRectangle();
	}

	function createPerspective(placement) {
		clearPlacementObjects();

		var canvasWidth = fabricCanvas.getWidth();
		var canvasHeight = fabricCanvas.getHeight();

		var defaultPoints = [
			{ x: 0.25, y: 0.30 },
			{ x: 0.65, y: 0.30 },
			{ x: 0.65, y: 0.55 },
			{ x: 0.25, y: 0.55 }
		];

		var savedPoints =
			placement &&
			placement.type === 'perspective' &&
			Array.isArray(placement.points) &&
			placement.points.length === 4
				? placement.points
				: defaultPoints;

		var polygonPoints = savedPoints.map(function (point) {
			return {
				x: point.x * canvasWidth,
				y: point.y * canvasHeight
			};
		});

		perspectivePolygon = new fabric.Polygon(polygonPoints, {
			left: 0,
			top: 0,
			fill: 'rgba(214, 54, 56, 0.12)',
			stroke: '#d63638',
			strokeWidth: 2,
			selectable: false,
			evented: false,
			objectCaching: false
		});

		fabricCanvas.add(perspectivePolygon);

		perspectivePoints = polygonPoints.map(function (point, index) {
			var handle = new fabric.Circle({
				left: point.x,
				top: point.y,
				radius: 7,
				fill: '#d63638',
				stroke: '#ffffff',
				strokeWidth: 2,
				originX: 'center',
				originY: 'center',
				hasControls: false,
				hasBorders: false,
				lockScalingX: true,
				lockScalingY: true,
				lockRotation: true,
				hoverCursor: 'move',
				data: {
					pointIndex: index
				}
			});

			handle.on('moving', function () {
				handle.left = clamp(handle.left || 0, 0, canvasWidth);
				handle.top = clamp(handle.top || 0, 0, canvasHeight);
				handle.setCoords();

				updatePerspectivePolygon();
			});

			handle.on('modified', updatePerspectivePolygon);

			fabricCanvas.add(handle);

			return handle;
		});

		updatePerspectivePolygon();
	}

	function restorePlacement() {
		var placement = parsePlacementData(
			$('#wpmt_placement_data').val()
		);

		var selectedType = $('#wpmt_placement_type').val();

		if (placement.type === 'perspective' || selectedType === 'perspective') {
			createPerspective(placement);
			return;
		}

		createRectangle(placement);
	}

	function loadEditorImage(imageUrl) {
		if (!fabricCanvas || !imageUrl) {
			return;
		}

		editorImageUrl = imageUrl;

		fabric.FabricImage.fromURL(imageUrl, {
			crossOrigin: 'anonymous'
		}).then(function (image) {
			var dimensions = getCanvasDimensions(image);

			fabricCanvas.setDimensions({
				width: dimensions.width,
				height: dimensions.height
			});

			image.scale(dimensions.scale);
			image.set({
				left: 0,
				top: 0,
				selectable: false,
				evented: false,
				originX: 'left',
				originY: 'top'
			});

			if (backgroundImage) {
				fabricCanvas.remove(backgroundImage);
			}

			backgroundImage = image;

			fabricCanvas.add(backgroundImage);
			backgroundImage.sendToBack();

			$('.wpmt-editor-empty').hide();
			$('.wpmt-editor-canvas-wrap').prop('hidden', false);

			restorePlacement();
			fabricCanvas.requestRenderAll();
		}).catch(function () {
			$('.wpmt-editor-empty')
				.text('The selected mockup image could not be loaded.')
				.show();
		});
	}

	function initializeEditor() {
		if (editorInitialized) {
			fabricCanvas.calcOffset();
			fabricCanvas.requestRenderAll();
			return;
		}

		var editor = document.getElementById('wpmt-placement-editor');
		var canvasElement = document.getElementById('wpmt-placement-canvas');

		if (!editor || !canvasElement || typeof fabric === 'undefined') {
			return;
		}

		editorImageUrl = editor.getAttribute('data-image-url') || '';

		fabricCanvas = new fabric.Canvas(canvasElement, {
			preserveObjectStacking: true,
			selection: false
		});

		editorInitialized = true;

		if (editorImageUrl) {
			loadEditorImage(editorImageUrl);
		}
	}

	function initializeWhenTabVisible() {
		var panel = $('#wpmt_print_mockup_panel');

		if (!panel.length || !panel.is(':visible')) {
			return;
		}

		initializeEditor();
	}

	function bindEditorButtons() {
		$('.wpmt-set-rectangle').on('click', function () {
			initializeEditor();

			if (!fabricCanvas || !backgroundImage) {
				return;
			}

			createRectangle({});
		});

		$('.wpmt-set-perspective').on('click', function () {
			initializeEditor();

			if (!fabricCanvas || !backgroundImage) {
				return;
			}

			createPerspective({});
		});

		$('.wpmt-reset-placement').on('click', function () {
			if (!fabricCanvas || !backgroundImage) {
				return;
			}

			$('#wpmt_placement_data').val('{}');

			if ($('#wpmt_placement_type').val() === 'perspective') {
				createPerspective({});
				return;
			}

			createRectangle({});
		});

		$('#wpmt_placement_type').on('change', function () {
			if (!fabricCanvas || !backgroundImage) {
				return;
			}

			if ($(this).val() === 'perspective') {
				createPerspective({});
				return;
			}

			createRectangle({});
		});
	}

	function bindWooCommerceTab() {
		$(document.body).on(
			'click',
			'.product_data_tabs .wpmt_print_mockup_options a, .product_data_tabs a[href="#wpmt_print_mockup_panel"]',
			function () {
				window.setTimeout(initializeWhenTabVisible, 50);
			}
		);

		$(document.body).on('woocommerce_variations_loaded', function () {
			window.setTimeout(initializeWhenTabVisible, 50);
		});
	}

	function bindMediaPicker() {
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
				multiple: false,
				library: {
					type: 'image'
				}
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame
					.state()
					.get('selection')
					.first()
					.toJSON();

				var previewUrl =
					attachment.sizes && attachment.sizes.medium
						? attachment.sizes.medium.url
						: attachment.url;

				var editorUrl =
					attachment.sizes && attachment.sizes.large
						? attachment.sizes.large.url
						: attachment.url;

				$('#wpmt_mockup_image_id').val(attachment.id);

				$('.wpmt-mockup-image-preview').html(
					$('<img>', {
						src: previewUrl,
						alt: ''
					})
				);

				$('.wpmt-remove-mockup-image').show();

				$('#wpmt-placement-editor')
					.attr('data-image-url', editorUrl);

				initializeEditor();
				loadEditorImage(editorUrl);
			});

			mediaFrame.open();
		});

		$('.wpmt-remove-mockup-image').on('click', function (event) {
			event.preventDefault();

			$('#wpmt_mockup_image_id').val('');
			$('#wpmt_placement_data').val('{}');

			$('.wpmt-mockup-image-preview').empty();
			$('.wpmt-editor-canvas-wrap').prop('hidden', true);
			$('.wpmt-editor-empty').show();

			$(this).hide();

			if (fabricCanvas) {
				fabricCanvas.clear();
				backgroundImage = null;
				rectangle = null;
				perspectivePolygon = null;
				perspectivePoints = [];
			}
		});
	}

	$(function () {
		bindWooCommerceTab();
		bindEditorButtons();
		bindMediaPicker();

		initializeWhenTabVisible();

		$(window).on('resize.wpmtEditor', function () {
			if (!fabricCanvas) {
				return;
			}

			fabricCanvas.calcOffset();
		});
	});
})(jQuery);