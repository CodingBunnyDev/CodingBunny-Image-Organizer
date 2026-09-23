(function($){
	'use strict';

	function enableListViewDragDrop() {
		$('table.wp-list-table tbody tr').draggable({
			helper: function() {
				var checked = $('tbody th.check-column input[type="checkbox"]:checked');
				if (checked.length > 1 && $(this).find('th.check-column input[type="checkbox"]').is(':checked')) {
					return $('<div class="cborg-drag-helper">' + checked.length + ' images</div>');
				}
				var $row = $(this);
				var $thumb = $row.find('.column-title img, .column-icon img').first().clone().css({
					width: '40px', height: '40px', 'margin-right': '10px', 'vertical-align':'middle'
				});
				var title = $row.find('.column-title strong, .column-title a').first().text() || '';
				var $helper = $('<div class="cborg-drag-helper"></div>');
				if($thumb.length) $helper.append($thumb);
				$helper.append($('<span></span>').text(title));
				return $helper;
			},
			revert: "invalid",
			appendTo: "body",
			zIndex: 10000,
			cursor: "move",
			containment: "window",
			start: function() {
				$('#cborg-sidebar .cborg-cat-link').addClass('cborg-drop-ready');
			},
			stop: function() {
				$('#cborg-sidebar .cborg-cat-link').removeClass('cborg-drop-ready');
			}
		});
	}

	function enableGridViewDragDrop() {
		function initDraggable() {
			$('.attachments .attachment').draggable({
				helper: function() {
					var selection = window.wp && wp.media && wp.media.frame && wp.media.frame.state().get('selection');
					var selectedIds = [];
					if (selection) {
						selection.each(function(model){
							selectedIds.push(model.get('id'));
						});
					}

					if (selectedIds.length > 1 && $(this).hasClass('selected')) {
						return $('<div class="cborg-drag-helper">' + selectedIds.length + ' images</div>');
					}

					if (selectedIds.length > 1) {
						return $('<div class="cborg-drag-helper">' + selectedIds.length + ' images</div>');
					}

					var $img = $(this).find('img').first().clone().css({
						width: '40px', height: '40px', 'margin-right': '10px', 'vertical-align':'middle'
					});
					var title = $(this).attr('data-title') ||
					$(this).attr('data-alt') ||
					$(this).find('img').attr('alt') || '';
					var $helper = $('<div class="cborg-drag-helper"></div>');
					if($img.length) $helper.append($img);
					$helper.append($('<span></span>').text(title));
					return $helper;
				},
				revert: "invalid",
				appendTo: "body",
				zIndex: 10000,
				cursor: "move",
				containment: "window",
				start: function() {
					$('#cborg-sidebar .cborg-cat-link').addClass('cborg-drop-ready');
				},
				stop: function() {
					$('#cborg-sidebar .cborg-cat-link').removeClass('cborg-drop-ready');
				}
			});
		}
		var gridInit = false;
		function observerCallback() {
			if (!gridInit && $('.attachments .attachment').length) {
				initDraggable();
				gridInit = true;
			}
		}
		observerCallback();
		var observer = new MutationObserver(function() {
			gridInit = false;
			setTimeout(observerCallback, 100);
		});
		var attWrap = document.querySelector('.attachments');
		if (attWrap) {
			observer.observe(attWrap, { childList: true, subtree: true });
		}
	}

	function enableSidebarDropForBothViews() {
		$('#cborg-sidebar .cborg-cat-link').each(function() {
			var $catLink = $(this);
			$catLink.droppable({
				accept: function(element) {
					return $(element).is('table.wp-list-table tbody tr') || $(element).is('.attachment');
				},
				hoverClass: 'cborg-drop-hover',
				tolerance: 'pointer',
				drop: function(event, ui) {
					var selectedIds = [];
					if (ui.draggable.is('tr')) {
						selectedIds = $('tbody th.check-column input[type="checkbox"]:checked')
						.map(function() { return $(this).val(); }).get();
						if (selectedIds.length === 0) {
							selectedIds = [ui.draggable.find('th.check-column input[type="checkbox"]').val()];
						}
					} else if (ui.draggable.hasClass('attachment')) {
						if (window.wp && wp.media && wp.media.frame && wp.media.frame.state) {
							var selection = wp.media.frame.state().get('selection');
							selection.each(function(model){
								selectedIds.push(model.get('id'));
							});
						}
						if (!selectedIds.length) {
							selectedIds = [ui.draggable.data('id') || ui.draggable.attr('data-id')];
						}
					}
					var href = $(this).attr('href');
					var categorySlug = '';
					if (href && href.indexOf('image_category=') !== -1) {
						categorySlug = decodeURIComponent(href.split('image_category=')[1].split('&')[0]);
					}
					if (categorySlug === (window.CBORG_VARS?.protectedSlug || 'protected')) {
						alert('This is a virtual filter and cannot accept images.');
						return;
					}
					if (!categorySlug) return;
					var categoryId = null;
					if (typeof CBORG_VARS !== "undefined" && CBORG_VARS.categories) {
						CBORG_VARS.categories.forEach(function(cat) {
							if (cat.slug === categorySlug) categoryId = cat.id;
						});
					}
					if (categoryId === null || typeof categoryId === "undefined") {
						alert("Category not found");
						return;
					}
					if (!selectedIds.length || selectedIds[0] === undefined) {
						alert(CBORG_VARS?.i18n?.noImages || 'No images selected!');
						return;
					}
					$.ajax({
						url: CBORG_AJAX.ajax_url,
						method: 'POST',
						data: {
							action: 'cborg_set_terms',
							nonce: CBORG_AJAX.nonce,
							ids: selectedIds,
							category: categoryId,
							tags: []
						}
					})
					.done(function(resp){
						var msg = (resp && resp.data && resp.data.message) ? resp.data.message : (CBORG_VARS?.i18n?.assignmentOk || 'Assignment completed!');
						alert(msg);
						location.reload();
					})
					.fail(function(){
						alert(CBORG_VARS?.i18n?.ajaxError || 'AJAX error!');
					});
				}
			});
		});
	}

	function makeCategoriesSortable() {
		var $catUl = $('#cborg-sidebar > ul');
		if (!$catUl.length) return;
		$catUl.sortable({
			items: '> li:not(.cborg-divider):not([data-fixed])',
			handle: '.cborg-cat-link',
			axis: 'y',
			cursor: 'move',
			update: function(event, ui) {
				var orderedSlugs = [];
				$catUl.find('> li:not([data-fixed]) > .cborg-cat-link').each(function(){
					orderedSlugs.push($(this).data('cat-slug') || '');
				});
				orderedSlugs = orderedSlugs.filter(Boolean);
				$.post(CBORG_AJAX.ajax_url, {
					action: 'cborg_sort_cats',
					nonce: CBORG_AJAX.nonce,
					order: orderedSlugs
				});
			}
		});
	}

	$(document).ready(function () {
		enableListViewDragDrop();
		enableSidebarDropForBothViews();
		enableGridViewDragDrop();
		makeCategoriesSortable();
		$('#cborg-sidebar .cborg-subcats').hide();
		$('#cborg-sidebar li.cborg-open > .cborg-subcats').show();
		window.CBORG.updateAllFolderIcons();
	});

})(jQuery);