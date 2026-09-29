'use strict';

(function () {

	function buildSidebarHtml(currentCat, baseUrl) {
		let currentCatId = null;
		let currentCatColor = null;
		if (currentCat && window.CBORG_VARS?.categories) {
			window.CBORG_VARS.categories.forEach(cat => {
				if (cat.slug === currentCat) {
					currentCatId = cat.id;
					currentCatColor = cat.color || null;
				}
			});
		}
		const editUrl = currentCatId
			? `term.php?taxonomy=image_category&tag_ID=${currentCatId}&post_type=attachment`
			: 'edit-tags.php?taxonomy=image_category&post_type=attachment';

		let html = '<div id="cborg-sidebar">';
		html += `<div class="cborg-sidebar-header">
			<h3 class="cborg-sidebar-title">${window.CBORG_VARS?.i18n?.filterByCat || 'Categories'}</h3>
			<div class="cborg-toolbar">
				<a href="edit-tags.php?taxonomy=image_category&post_type=attachment" class="button button-small cborg-toolbar-btn" target="_blank" rel="noopener" title="${window.CBORG_VARS?.i18n?.newCat || 'New Category'}">
					<span class="dashicons dashicons-plus-alt2"></span>
				</a>
				<a href="${editUrl}" class="button button-small cborg-toolbar-btn cborg-edit-cat" target="_blank" rel="noopener" title="${window.CBORG_VARS?.i18n?.editCat || 'Edit Category'}">
					<span class="dashicons dashicons-edit"></span>
				</a>
				<button id="cborg-copy-category" class="button button-small cborg-toolbar-btn" title="${window.CBORG_VARS?.i18n?.copyCat || 'Copy Category'}">
					<span class="dashicons dashicons-admin-page"></span>
				</button>
				<button id="cborg-delete-category" class="button button-small cborg-toolbar-btn" title="${window.CBORG_VARS?.i18n?.deleteCat || 'Delete Category'}">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</div>`;

		html += '<ul>';

		html += `<li data-fixed="1">
			<a href="${baseUrl}" class="cborg-cat-link${!currentCat ? ' active' : ''}" data-cat-slug="">
				<span class="cborg-folder-spacer"></span>
				<span class="cborg-cat-label">
					<span class="dashicons dashicons-images-alt2"></span>
					<span class="cborg-cat-text">${window.CBORG_VARS?.i18n?.allCategories || 'All Files'}</span>
				</span>
				<span class="cborg-cat-count"></span>
			</a>
		</li>`;

		const flat = window.CBORG_VARS?.categories || [];
		let uncatNode = null;
		const map = {};
		flat.forEach(item => {
			map[item.id] = {
				id: item.id,
				parent: item.parent || 0,
				slug: item.slug,
				name: item.name,
				count: typeof item.count === "number" ? item.count : 0,
				color: item.color || null,
				children: []
			};
		});
		flat.forEach(item => {
			const node = map[item.id];
			if (node.parent && node.parent !== 0 && map[node.parent]) {
				map[node.parent].children.push(node);
			}
		});
		flat.forEach(item => {
			if (item.slug === 'uncategorized') uncatNode = map[item.id];
		});
		if (uncatNode) {
			const slug = uncatNode.slug || '';
			const name = uncatNode.name || '';
			const count = typeof uncatNode.count === 'number' ? uncatNode.count : 0;
			const activeClass = (currentCat === slug) ? ' active' : '';
			const dashStyle = uncatNode.color ? `color:${uncatNode.color};` : '';
			html += `<li data-fixed="1">
				<a href="${baseUrl}&image_category=${encodeURIComponent(slug)}" class="cborg-cat-link${activeClass}" data-cat-slug="${escapeHTML(slug)}">
					<span class="cborg-folder-spacer"></span>
					<span class="cborg-cat-label">
						<span class="dashicons dashicons-category" style="${dashStyle}"></span>
						<span class="cborg-cat-text">${escapeHTML(name)}</span>
					</span>
					<span class="cborg-cat-count">${count}</span>
				</a>
			</li>`;
		}

		if (window.CBORG_VARS?.protectorActive && window.CBORG_VARS?.protectedCount >= 0) {
			const pSlug = window.CBORG_VARS.protectedSlug || 'protected';
			const pActive = (currentCat === pSlug) ? ' active' : '';
			html += `<li data-fixed="1" class="cborg-protected-item">
				<a href="${baseUrl}&image_category=${encodeURIComponent(pSlug)}" class="cborg-cat-link cborg-protected-link${pActive}" data-cat-slug="${escapeHTML(pSlug)}" data-virtual="1">
					<span class="cborg-folder-spacer"></span>
					<span class="cborg-cat-label">
						<span class="dashicons dashicons-lock"></span>
						<span class="cborg-cat-text">${escapeHTML(window.CBORG_VARS?.i18n?.protected || 'Protected')}</span>
					</span>
					<span class="cborg-cat-count">${window.CBORG_VARS.protectedCount || 0}</span>
				</a>
			</li>`;
		}

		html += '<li class="cborg-divider"><hr class="cborg-cat-divider" /></li>';

		const roots = [];
		flat.forEach(item => {
			if (item.slug !== 'uncategorized') {
				const node = map[item.id];
				if (node.parent && node.parent !== 0 && map[node.parent]) {
				} else {
					roots.push(node);
				}
			}
		});

		function renderNode(node) {
			const slug = node.slug || '';
			if (slug === 'uncategorized') return;
			const name = node.name || '';
			const count = typeof node.count === 'number' ? node.count : 0;
			const activeClass = (currentCat === slug) ? ' active' : '';
			const dashStyle = node.color ? `color:${node.color};` : '';
			const hasChildren = node.children && node.children.length;
			const liClass = hasChildren ? 'cborg-has-children' : '';
			let openClass = '';
			if (hasChildren && node.children.some(child => child.slug === currentCat)) {
				openClass = ' cborg-open';
			}
			html += `<li class="${liClass}${openClass}">`;
			html += `<a href="${baseUrl}${slug ? '&image_category=' + encodeURIComponent(slug) : ''}" class="cborg-cat-link${activeClass}" data-cat-slug="${escapeHTML(slug)}">` +
				(hasChildren
					? '<span class="cborg-folder-toggle" tabindex="0" aria-label="Open subcategories"></span>'
					: '<span class="cborg-folder-spacer"></span>'
				) +
				`<span class="cborg-cat-label">
					<span class="dashicons dashicons-category" style="${dashStyle}"></span>
					<span class="cborg-cat-text">${escapeHTML(name)}</span>
				</span>
				<span class="cborg-cat-count">${count}</span>
			</a>`;
			if (hasChildren) {
				html += '<ul class="cborg-subcats">';
				node.children.forEach(child => renderNode(child));
				html += '</ul>';
			}
			html += '</li>';
		}
		roots.forEach(root => renderNode(root));

		html += '</ul>';
		html += `<div class="cborg-sidebar-footer">
			<button id="cborg-export-zip" class="button button-primary">
				<span class="dashicons dashicons-download"></span>
				${escapeHTML(window.CBORG_VARS?.i18n?.exportCat || 'Export Selected Category')}
			</button>
		</div>`;
		html += '<div id="cborg-sidebar-resize-handle"></div>';

		html += '</div>';
		return html;
	}

	function escapeHTML(str) {
		if (typeof str !== "string") return "";
		return str.replace(/[&<>'"]/g, function (tag) {
			const charsToReplace = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'\'': '&#39;',
				'"': '&quot;'
			};
			return charsToReplace[tag] || tag;
		});
	}

	function updateAllFolderIcons() {
		const listItems = document.querySelectorAll('#cborg-sidebar li');
		listItems.forEach(li => {
			const icon = li.querySelector('.cborg-cat-link .dashicons-category, .cborg-cat-link .dashicons-open-folder');
			if (!icon) return;
			if (li.classList.contains('cborg-open') || (li.querySelector('.cborg-cat-link')?.classList.contains('active'))) {
				icon.classList.remove('dashicons-category');
				icon.classList.add('dashicons-open-folder');
			} else {
				icon.classList.remove('dashicons-open-folder');
				icon.classList.add('dashicons-category');
			}
		});
	}

	function placeSidebarAndWrap() {
		if (document.getElementById('cborg-global-container')) return;
		const wrap = document.querySelector('.wrap');
		if (!wrap) return;
		let sidebar = document.getElementById('cborg-sidebar');
		if (!sidebar) {
			const isGrid = !!document.querySelector('.attachments-browser');
			const params = new URLSearchParams(window.location.search);
			const currentCat = params.get('image_category') || '';
			const baseUrl = location.pathname + (isGrid ? '?mode=grid' : '?mode=list');
			const html = buildSidebarHtml(currentCat, baseUrl);
			const tempDiv = document.createElement('div');
			tempDiv.innerHTML = html;
			sidebar = tempDiv.firstChild;
		}
		const global = document.createElement('div');
		global.id = 'cborg-global-container';
		wrap.parentNode.insertBefore(global, wrap);
		global.appendChild(sidebar);
		global.appendChild(wrap);

		const savedWidth = localStorage.getItem('cborg_sidebar_width');
		if (savedWidth) {
			sidebar.style.width = savedWidth + 'px';
			document.documentElement.style.setProperty('--cborg-sidebar-width', savedWidth + 'px');
		}
	}

	function enableSidebarResize() {
		const sidebar = document.getElementById('cborg-sidebar');
		const handle = document.getElementById('cborg-sidebar-resize-handle');
		if (!sidebar || !handle) return;

		let dragging = false, startX = 0, startWidth = 0;

		handle.addEventListener('mousedown', function (e) {
			e.preventDefault();
			dragging = true;
			startX = e.clientX;
			startWidth = sidebar.offsetWidth;
			document.body.classList.add('cborg-resizing');

			function mouseMoveHandler(e) {
				if (!dragging) return;
				const dx = e.clientX - startX;
				const newWidth = Math.min(500, Math.max(300, startWidth + dx));
				sidebar.style.width = newWidth + 'px';
				document.documentElement.style.setProperty('--cborg-sidebar-width', newWidth + 'px');
			}
			function mouseUpHandler(e) {
				if (!dragging) return;
				dragging = false;
				document.body.classList.remove('cborg-resizing');
				const finalWidth = sidebar.offsetWidth;
				localStorage.setItem('cborg_sidebar_width', finalWidth);
				document.removeEventListener('mousemove', mouseMoveHandler);
				document.removeEventListener('mouseup', mouseUpHandler);
			}
			document.addEventListener('mousemove', mouseMoveHandler);
			document.addEventListener('mouseup', mouseUpHandler);
		});
	}

	window.CBORG = window.CBORG || {};
	window.CBORG.buildSidebarHtml = buildSidebarHtml;
	window.CBORG.updateAllFolderIcons = updateAllFolderIcons;
	window.CBORG.placeSidebarAndWrap = placeSidebarAndWrap;
	window.CBORG.enableSidebarResize = enableSidebarResize;

})();