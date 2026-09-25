/**
 * Build configuration.
 *
 * Extends the @wordpress/scripts default config with two explicit entries:
 *
 * - `editor` — the React checklist panel. Its SCSS is imported from index.js, so
 *   wp-scripts emits build/editor.js, build/editor.css and build/editor.asset.php.
 * - `admin`  — style only (the products list column and the settings page are plain
 *   server-rendered HTML). wp-scripts' RemoveEmptyScripts plugin drops the empty
 *   companion JS file, leaving only build/admin.css.
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		editor: path.resolve( __dirname, 'assets/js/editor/index.js' ),
		admin: path.resolve( __dirname, 'assets/scss/admin.scss' ),
	},
};
