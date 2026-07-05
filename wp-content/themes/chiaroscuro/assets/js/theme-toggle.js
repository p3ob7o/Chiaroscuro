/**
 * Handles the front-end color mode toggle.
 *
 * @package Chiaroscuro
 */

(function () {
	'use strict';

	var key  = 'pb-theme';
	var root = document.documentElement;

	function storedTheme() {
		try {
			return localStorage.getItem( key );
		} catch (error) {
			return null;
		}
	}

	function preferredTheme() {
		var stored = storedTheme();

		if (stored === 'dark' || stored === 'light') {
			return stored;
		}

		return window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
	}

	function applyTheme(theme) {
		var isDark = theme === 'dark';

		root.dataset.theme     = theme;
		root.style.colorScheme = theme;

		document.querySelectorAll( '.wp-block-chiaroscuro-theme-toggle' ).forEach(
			function (button) {
				button.setAttribute( 'aria-pressed', isDark ? 'true' : 'false' );
			}
		);
	}

	applyTheme( preferredTheme() );

	document.addEventListener(
		'click',
		function (event) {
			var button = event.target.closest( '.wp-block-chiaroscuro-theme-toggle' );

			if ( ! button) {
				return;
			}

			var next = root.dataset.theme === 'dark' ? 'light' : 'dark';

			try {
				localStorage.setItem( key, next );
			} catch (error) {
			}

			applyTheme( next );
		}
	);
}());
