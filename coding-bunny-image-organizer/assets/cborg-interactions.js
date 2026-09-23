(function(){
	'use strict';

	function updateSidebarButtonsState() {
		var active = document.querySelector('#cborg-sidebar .cborg-cat-link.active');
		var catSlug = active ? active.getAttribute('data-cat-slug') || '' : '';
		var isAllFiles = (catSlug === '');
		var isUncategorized = (catSlug === 'uncategorized');
		var disable = isAllFiles || isUncategorized;
		[
			document.querySelector('#cborg-sidebar .cborg-edit-cat'),
			document.getElementById('cborg-copy-category'),
			document.getElementById('cborg-delete-category')
		].forEach(function(btn){
			if (btn) {
				btn.disabled = disable;
				btn.classList.toggle('cborg-btn-disabled', disable);
			}
		});
	}

	const STORAGE_KEY = 'cborg_open_categories';
	function getOpenCategories() {
		try {
			return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
		} catch (e) {
			return [];
		}
	}
	function setOpenCategories(slugs) {
		localStorage.setItem(STORAGE_KEY, JSON.stringify(slugs));
	}
	function addOpenCategory(slug) {
		var slugs = getOpenCategories();
		if (slugs.indexOf(slug) === -1) {
			slugs.push(slug);
			setOpenCategories(slugs);
		}
	}
	function removeOpenCategory(slug) {
		var slugs = getOpenCategories();
		var idx = slugs.indexOf(slug);
		if (idx !== -1) {
			slugs.splice(idx, 1);
			setOpenCategories(slugs);
		}
	}

	function slideDown(el, duration){
		el.style.display = '';
		var height = el.offsetHeight;
		el.style.overflow = 'hidden';
		el.style.height = '0';
		el.offsetHeight; // force reflow
		el.style.transition = 'height ' + duration + 'ms';
		el.style.height = height + 'px';
		setTimeout(function(){
			el.style.height = '';
			el.style.overflow = '';
			el.style.transition = '';
		}, duration);
	}
	function slideUp(el, duration){
		el.style.overflow = 'hidden';
		var height = el.offsetHeight;
		el.style.height = height + 'px';
		el.offsetHeight; // force reflow
		el.style.transition = 'height ' + duration + 'ms';
		el.style.height = '0';
		setTimeout(function(){
			el.style.display = 'none';
			el.style.height = '';
			el.style.overflow = '';
			el.style.transition = '';
		}, duration);
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (window.CBORG && typeof window.CBORG.placeSidebarAndWrap === "function") {
			window.CBORG.placeSidebarAndWrap();
		}
		if (window.CBORG && typeof window.CBORG.enableSidebarResize === "function") {
			window.CBORG.enableSidebarResize();
		}

		document.addEventListener('click', function(e){
			var toggle = e.target.closest('#cborg-sidebar .cborg-cat-link .cborg-folder-toggle');
			if (!toggle) return;
			e.preventDefault(); e.stopPropagation();
			var li = toggle.closest('li');
			var subcats = li.querySelector('.cborg-subcats');
			var isOpen = li.classList.contains('cborg-open');
			var catLink = li.querySelector('.cborg-cat-link');
			var catSlug = catLink.getAttribute('data-cat-slug');
			if (isOpen) {
				li.classList.remove('cborg-open');
				if (subcats) slideUp(subcats, 150);
				if (catSlug) removeOpenCategory(catSlug);
			} else {
				li.classList.add('cborg-open');
				if (subcats) slideDown(subcats, 150);
				if (catSlug) addOpenCategory(catSlug);
			}
			if (window.CBORG && window.CBORG.updateAllFolderIcons) window.CBORG.updateAllFolderIcons();
		});

		document.addEventListener('click', function(e){
			var link = e.target.closest('#cborg-sidebar .cborg-cat-link');
			if (!link || (e.target.classList.contains('cborg-folder-toggle'))) return;
			var catSlug = link.getAttribute('data-cat-slug') || '';
			var isGrid = !!document.querySelector('.attachments-browser');
			var currentCatId = null;
			if (typeof window.CBORG_VARS !== "undefined" && window.CBORG_VARS.categories) {
				window.CBORG_VARS.categories.forEach(function(cat) {
					if (cat.slug === catSlug) currentCatId = cat.id;
				});
			}
			var editUrl = currentCatId ?
				'term.php?taxonomy=image_category&tag_ID=' + currentCatId + '&post_type=attachment' :
				'edit-tags.php?taxonomy=image_category&post_type=attachment';
			var editCatBtn = document.querySelector('.cborg-edit-cat');
			if (editCatBtn) editCatBtn.setAttribute('href', editUrl);

			document.querySelectorAll('#cborg-sidebar .cborg-cat-link').forEach(function(a){
				a.classList.remove('active');
			});
			link.classList.add('active');

			var li = link.closest('li');
			var parentLi = li.parentElement.closest('li');
			if (parentLi && parentLi.classList.contains('cborg-has-children')) {
				parentLi.classList.add('cborg-open');
				var parentSubcats = parentLi.querySelector('.cborg-subcats');
				if (parentSubcats) slideDown(parentSubcats, 150);
				var parentCatSlug = parentLi.querySelector('.cborg-cat-link').getAttribute('data-cat-slug');
				if (parentCatSlug) addOpenCategory(parentCatSlug);
			}
			if (window.CBORG && window.CBORG.updateAllFolderIcons) window.CBORG.updateAllFolderIcons();

			if (li.classList.contains('cborg-has-children')) {
				li.classList.add('cborg-open');
				var subcats = li.querySelector('.cborg-subcats');
				if (subcats) slideDown(subcats, 150);
				if (catSlug) addOpenCategory(catSlug);
			}

			updateSidebarButtonsState();

			if (window.wp && wp.media && wp.media.frame && isGrid) {
				e.preventDefault();
				var library = wp.media.frame.content.get().collection.props;
				library.set('image_category', catSlug);
				library.unset('paged');
				wp.media.frame.content.get().collection.fetch({reset: true});
				if (history.replaceState) {
					var params = new URLSearchParams(window.location.search);
					if (catSlug) params.set('image_category', catSlug);
					else params.delete('image_category');
					history.replaceState({}, '', window.location.pathname + '?' + params.toString());
				}
				return;
			}

			if (document.getElementById('the-list')) {
				e.preventDefault();
				var params = new URLSearchParams(window.location.search);
				if (catSlug) params.set('image_category', catSlug);
				else params.delete('image_category');
				params.set('mode', 'list');
				var newUrl = window.location.pathname + '?' + params.toString();
				var tbody = document.getElementById('the-list');
				var table = tbody.closest('table');
				var colCount = table.querySelectorAll('thead th').length;
				tbody.innerHTML = '<tr><td colspan="' + colCount + '" style="text-align:center">Loading...</td></tr>';
				fetch(newUrl + '&cborg-ajax=1').then(function(resp){return resp.text();}).then(function(data){
					var tmp = document.createElement('div');
					tmp.innerHTML = data;
					var newRows = tmp.querySelector('#the-list');
					tbody.innerHTML = newRows ? newRows.innerHTML : '';
					if (history.replaceState) {
						history.replaceState({}, '', newUrl);
					}
				});
				return;
			}
		});

		(function(){
			var params = new URLSearchParams(window.location.search);
			var activeCat = params.get('image_category');
			document.querySelectorAll('#cborg-sidebar .cborg-cat-link').forEach(function(a){
				a.classList.remove('active');
			});
			if (activeCat) {
				var sel = '#cborg-sidebar .cborg-cat-link[data-cat-slug="' + activeCat + '"]';
				var found = document.querySelector(sel);
				if (found) found.classList.add('active');
			} else {
				var first = document.querySelector('#cborg-sidebar .cborg-cat-link');
				if (first) first.classList.add('active');
			}
			document.querySelectorAll('#cborg-sidebar .cborg-subcats').forEach(function(ul){
				ul.style.display = 'none';
			});
			document.querySelectorAll('#cborg-sidebar li.cborg-open > .cborg-subcats').forEach(function(ul){
				ul.style.display = '';
			});
			var openCategories = getOpenCategories();
			if (openCategories && openCategories.length) {
				openCategories.forEach(function(slug) {
					var li = document.querySelector('#cborg-sidebar .cborg-cat-link[data-cat-slug="' + slug + '"]');
					if (li) {
						var parentLi = li.closest('li');
						parentLi.classList.add('cborg-open');
						var subcats = parentLi.querySelector('.cborg-subcats');
						if (subcats) subcats.style.display = '';
					}
				});
			}
			if (window.CBORG && window.CBORG.updateAllFolderIcons) window.CBORG.updateAllFolderIcons();
			updateSidebarButtonsState();
		})();

		document.addEventListener('click', function(e){
			var btn = e.target.closest('#cborg-export-zip');
			if (!btn) return;
			e.preventDefault();
			var currentCat = document.querySelector('#cborg-sidebar .cborg-cat-link.active').getAttribute('data-cat-slug') || '';
			if (!currentCat) {
				alert('Select the category to export.');
				return;
			}
			var form = document.createElement('form');
			form.method = 'POST';
			form.action = window.CBORG_AJAX.ajax_url;
			form.style.display = 'none';
			form.target = '_blank';
			form.innerHTML = '<input type="hidden" name="action" value="cborg_export_zip" />' +
				'<input type="hidden" name="nonce" value="'+window.CBORG_AJAX.nonce+'" />' +
				'<input type="hidden" name="category" value="'+currentCat+'" />';
			document.body.appendChild(form);
			form.submit();
			setTimeout(function(){form.remove();}, 1000);
		});

		document.addEventListener('click', function(e){
			var btn = e.target.closest('#cborg-copy-category');
			if (!btn) return;
			e.preventDefault();
			if (btn.disabled) return;
			var active = document.querySelector('#cborg-sidebar .cborg-cat-link.active');
			var catSlug = active ? active.getAttribute('data-cat-slug') || '' : '';
			if (!catSlug || catSlug === 'uncategorized') {
				alert('Select a category to copy.');
				return;
			}
			var catData = null;
			if (typeof window.CBORG_VARS !== "undefined" && window.CBORG_VARS.categories) {
				window.CBORG_VARS.categories.forEach(function(cat) {
					if (cat.slug === catSlug) catData = cat;
				});
			}
			if (!catData) {
				alert('Category not found.');
				return;
			}
			var catId = catData.id;
			if (!catId) {
				alert('Category not found.');
				return;
			}
			var newName = prompt('Enter the name for the duplicated category:', catData.name + ' Copy');
			if (!newName) return;
			var params = new URLSearchParams();
			params.append('action', 'cborg_copy_cat');
			params.append('nonce', window.CBORG_AJAX.nonce);
			params.append('category_id', catId);
			params.append('name', newName);
			if (catData.color) params.append('color', catData.color);
			fetch(window.CBORG_AJAX.ajax_url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString()
			}).then(function(resp){return resp.text();}).then(function(txt){
				try {
					var json = JSON.parse(txt);
				} catch(e) {
					alert('AJAX error!\n'+txt);
					return;
				}
				if (json && json.success) {
					alert('Category duplicated!');
					location.reload();
				} else {
					alert((json && json.data && json.data.message) ? json.data.message : 'Error duplicating category.');
				}
			}).catch(function(e){
				alert('AJAX error!');
			});
		});

		document.addEventListener('click', function(e){
			var btn = e.target.closest('#cborg-delete-category');
			if (!btn) return;
			e.preventDefault();
			if (btn.disabled) return;
			var active = document.querySelector('#cborg-sidebar .cborg-cat-link.active');
			var catSlug = active ? active.getAttribute('data-cat-slug') || '' : '';
			if (!catSlug) {
				alert('Select a category to delete.');
				return;
			}
			if (catSlug === 'uncategorized' || catSlug === '') {
				alert('This category cannot be deleted.');
				return;
			}
			var catId = null;
			if (typeof window.CBORG_VARS !== "undefined" && window.CBORG_VARS.categories) {
				window.CBORG_VARS.categories.forEach(function(cat) {
					if (cat.slug === catSlug) catId = cat.id;
				});
			}
			if (!catId) {
				alert('Category not found.');
				return;
			}
			if (!confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
				return;
			}
			var params = new URLSearchParams();
			params.append('action', 'cborg_delete_cat');
			params.append('nonce', window.CBORG_AJAX.nonce);
			params.append('category_id', catId);
			fetch(window.CBORG_AJAX.ajax_url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: params.toString()
			}).then(function(resp){return resp.text();}).then(function(txt){
				try {
					var json = JSON.parse(txt);
				} catch(e) {
					alert('AJAX error!\n'+txt);
					return;
				}
				if (json && json.success) {
					alert('Category deleted!');
					location.reload();
				} else {
					alert((json && json.data && json.data.message) ? json.data.message : 'Error deleting category.');
				}
			}).catch(function(e){
				alert('AJAX error!');
			});
		});
	}); // DOMContentLoaded

})();