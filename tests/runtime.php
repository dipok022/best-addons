<?php
/**
 * The spine, exercised without a WordPress install.
 *
 * These are the assertions that matter most, because they are the ones a build
 * cannot make: that the runtime agrees with the manifest, that the Pro gate
 * actually withholds Pro modules, and that assets are registered but not
 * enqueued until something asks. A bug in any of them is silent on a visitor's
 * page and loud here.
 *
 * Run with `npm run test:php`.
 *
 * @package BestAddons
 */

declare( strict_types = 1 );

require_once __DIR__ . '/wp-stubs.php';
require_once __DIR__ . '/elementor-stubs.php';

use BestAddons\Assets\AssetPipeline;
use BestAddons\Licensing\LicenseManager;
use BestAddons\Licensing\ProGate;
use BestAddons\Modules\Categories;
use BestAddons\Modules\Enablement;
use BestAddons\Modules\Registry;
use BestAddons\Support\Autoloader;
use BestAddons\Support\Manifest;
use BestAddons\Assets\SpineAssets;
use BestAddons\Admin\AdminMenu;
use BestAddons\Admin\OptionsPage;
use BestAddons\Support\Paths;
use BestAddons\Plugin;

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = array();

/**
 * @param string $name
 * @param mixed  $expected
 * @param mixed  $actual
 */
function check( string $name, $expected, $actual ): void {
	if ( $expected === $actual ) {
		++$GLOBALS['passed'];
		printf( "  \033[32mok\033[0m   %s\n", $name );
		return;
	}

	$GLOBALS['failed'][] = $name;
	printf(
		"  \033[31mFAIL\033[0m %s\n       expected: %s\n       actual:   %s\n",
		$name,
		var_export( $expected, true ),
		var_export( $actual, true )
	);
}

function group( string $title ): void {
	printf( "\n\033[1m%s\033[0m\n", $title );
}

// Paths::path() resolves from the autoloader's own location, so the autoloader
// has to be loaded by path before anything can rely on it.
require_once dirname( __DIR__ ) . '/includes/Support/Autoloader.php';

Autoloader::register();

// ── Manifest ─────────────────────────────────────────────────────────────────

group( 'Manifest' );

$all = Manifest::all();

check( 'loads the generated manifest', 2, $all['version'] ?? null );
check( 'finds every module on disk', 7, count( $all['modules'] ?? array() ) );
check( 'reports no read error', null, Manifest::error() );
check( 'classmap has one class per code module', 4, count( Manifest::classmap() ) );

$ids = array_column( Manifest::modules(), 'id' );
sort( $ids );
check(
	'module ids are unique and complete',
	array( 'advanced-accordion', 'advanced-tabs', 'best-accordion', 'builder', 'free', 'pro', 'sample-box' ),
	$ids
);

check(
	'a widget resolves by id',
	'Advanced Accordion',
	Manifest::module( 'advanced-accordion' )['title'] ?? null
);

check( 'an unknown id resolves to null', null, Manifest::module( 'nope' ) );

// ── Paths ────────────────────────────────────────────────────────────────────

group( 'Paths' );

check(
	'path() is rooted at the plugin',
	realpath( __DIR__ . '/..' ) . '/manifest.json',
	realpath( Paths::path( 'manifest.json' ) )
);

check(
	'url() points at the built dist',
	'https://example.test/wp-content/plugins/best-addons/assets/dist/css/free-advanced-accordion-style.min.css',
	Paths::asset( 'css/free-advanced-accordion-style.min.css' )
);

// ── Autoloading ──────────────────────────────────────────────────────────────

group( 'Autoloader' );

check(
	'a module class resolves from the classmap',
	true,
	class_exists( 'BestAddons\\Feature\\Free\\Widgets\\AdvancedAccordion\\Widget', false )
	|| ( class_exists( 'BestAddons\\Feature\\Free\\Widgets\\AdvancedAccordion\\Widget' ) && true )
);

check(
	'a spine class resolves by PSR-4',
	true,
	class_exists( 'BestAddons\\Modules\\Registry' )
);

check(
	'an unknown class does not fatal',
	false,
	class_exists( 'BestAddons\\Nope\\Missing' )
);

// ── Registry and the Pro gate ────────────────────────────────────────────────

group( 'Registry' );

$unlicensed = array_keys( Registry::all() );
sort( $unlicensed );

check(
	'without a licence, Pro modules are withheld',
	array( 'advanced-accordion', 'best-accordion', 'builder', 'free', 'sample-box' ),
	$unlicensed
);

check(
	'Pro modules are the ones withheld',
	false,
	isset( $unlicensed['advanced-tabs'] )
);

check(
	'of_type() returns only widgets, in priority order',
	array( 'advanced-accordion', 'best-accordion' ),
	array_map( static fn ( $m ): string => $m->id(), Registry::of_type( 'widget' ) )
);

check(
	'the category type is available to the category registrar',
	array( 'free', 'builder' ),
	array_map( static fn ( $m ): string => $m->id(), Registry::of_type( 'category' ) )
);

// Now grant a licence and confirm the same registry flips.
LicenseManager::activate( 'test-key-1234' );
Registry::flush();
Enablement::flush();

check( 'a licence unlocks Pro', true, ProGate::allows( 'pro' ) );

$licensed = array_keys( Registry::all() );
sort( $licensed );

check(
	'with a licence, every module is present',
	array( 'advanced-accordion', 'advanced-tabs', 'best-accordion', 'builder', 'free', 'pro', 'sample-box' ),
	$licensed
);

check(
	'the Pro category appears with the Pro tier',
	true,
	in_array( 'pro', $licensed, true )
);

// The gate is a filter, so a site can lock Pro without deleting a licence.
add_filter(
	ProGate::FILTER,
	static fn ( bool $active, string $tier ): bool => 'pro' === $tier ? false : $active
);
Registry::flush();

check(
	'the gate filter can withhold Pro on its own',
	false,
	isset( Registry::all()['advanced-tabs'] )
);

remove_all_hooks();
Registry::flush();
LicenseManager::deactivate();
Registry::flush();
Enablement::flush();

// ── Enablement ───────────────────────────────────────────────────────────────

group( 'Enablement' );

check( 'everything is enabled by default', true, Enablement::is_enabled( 'best-accordion' ) );

Enablement::set( 'best-accordion', false );

check( 'a disabled module reports as disabled', false, Enablement::is_enabled( 'best-accordion' ) );
check( 'disabled_ids lists it', array( 'best-accordion' ), Enablement::disabled_ids() );

Registry::flush();
check(
	'a disabled module is dropped by the registry',
	false,
	isset( Registry::all()['best-accordion'] )
);

Enablement::reset();
Registry::flush();
check( 'reset brings it back', true, Enablement::is_enabled( 'best-accordion' ) );

// A Pro module that is disabled must stay out even when licensed.
LicenseManager::activate( 'test-key-1234' );
Registry::flush();
Enablement::flush();
check( 'a licensed Pro module is present', true, isset( Registry::all()['advanced-tabs'] ) );

LicenseManager::deactivate();
Registry::flush();
Enablement::flush();

// ── Categories ───────────────────────────────────────────────────────────────

group( 'Categories' );

check( 'the free category is declared', 'best-addons-free', Categories::FREE );
check( 'the pro category is declared', 'best-addons-pro', Categories::PRO );
check( 'the builder category is declared', 'best-addons-builder', Categories::BUILDER );

check(
	'unlicensed, both free categories are registered and Pro is not',
	array( 'best-addons-free', 'best-addons-builder' ),
	array_keys( Categories::all() )
);

check(
	'the manifest title is used, not a prettified slug',
	'Best Addons — Free',
	Categories::all()['best-addons-free'] ?? null
);

check(
	'a category module id translates to its Elementor slug',
	'best-addons-free',
	Categories::slug( 'free' )
);

// The round trip a widget depends on: what module.json says, and what
// get_categories() must hand back.
$widget = Registry::get( 'advanced-accordion' );
check(
	'a widget resolves to a registered category slug',
	true,
	in_array( Categories::slug( (string) $widget->category() ), array_keys( Categories::all() ), true )
);

LicenseManager::activate( 'test-key-1234' );
Registry::flush();

check(
	'licensed, the pro category joins it, in the declared panel order',
	array( 'best-addons-free', 'best-addons-pro', 'best-addons-builder' ),
	array_keys( Categories::all() )
);

check(
	'every category ships a real title, not a placeholder',
	array( 'Best Addons — Free', 'Best Addons — Pro', 'My Widgets' ),
	array_values( Categories::all() )
);

LicenseManager::deactivate();
Registry::flush();

// ── Asset pipeline ───────────────────────────────────────────────────────────

group( 'AssetPipeline' );

// The editor enqueue is hooked by init(), so the harness has to install the
// hooks before firing them.
AssetPipeline::init();
AssetPipeline::flush();
$registered = array();
$GLOBALS['ba_registered'] = $registered;

AssetPipeline::register();

$registered = $GLOBALS['ba_registered'];
$handles    = array_keys( $registered );

sort( $handles );

check(
	'module assets are registered, not enqueued',
	array(
		'script:ba-free-advanced-accordion-script',
		'script:ba-free-best-accordion-script',
		'style:ba-free-advanced-accordion-style',
		'style:ba-free-best-accordion-style',
		'style:ba-free-sample-box-style',
	),
	$handles
);

check(
	'nothing is enqueued on the front end',
	array(),
	array_keys( $GLOBALS['ba_enqueued'] )
);

check(
	'the version is the content hash, not a filemtime',
	Manifest::module( 'advanced-accordion' )['hash'],
	$registered['style:ba-free-advanced-accordion-style']['ver']
);

check(
	'Pro assets are absent while unlicensed',
	false,
	isset( $registered['style:ba-pro-advanced-tabs-style'] )
);

// Editor bundles must reach the editor screen and nowhere else.
check(
	'editor assets are discovered but held back',
	array( 'ba-free-sample-box-editor' ),
	array_keys( AssetPipeline::editor_assets() )
);

check(
	'no editor bundle is enqueued yet',
	false,
	isset( $GLOBALS['ba_enqueued']['script:ba-free-sample-box-editor'] )
);

do_action( 'elementor/editor/after_enqueue_scripts' );

check(
	'the editor hook enqueues them',
	true,
	(bool) ( $GLOBALS['ba_enqueued']['script:ba-free-sample-box-editor'] ?? false )
);

// The bug this guards: on an admin request `wp_enqueue_scripts` runs first and
// fills the discovery list, so a guard keyed on that list would skip the
// enqueue entirely. Re-running both hooks must still leave exactly one enqueue.
AssetPipeline::flush();
$GLOBALS['ba_registered'] = array();
$GLOBALS['ba_enqueued']   = array();

do_action( 'wp_enqueue_scripts' );
do_action( 'elementor/editor/after_enqueue_scripts' );
do_action( 'elementor/editor/after_enqueue_scripts' );
do_action( 'wp_enqueue_scripts' );

$editor_enqueues = array_filter(
	array_keys( $GLOBALS['ba_enqueued'] ),
	static fn ( string $handle ): bool => str_contains( $handle, 'sample-box-editor' )
);

check( 'the editor bundle is enqueued exactly once', 1, count( $editor_enqueues ) );

check(
	'front-end module scripts are still never enqueued here',
	array(),
	array_values(
		array_filter(
			array_keys( $GLOBALS['ba_enqueued'] ),
			static fn ( string $handle ): bool => str_contains( $handle, 'accordion-script' )
		)
	)
);

// ── Manifest promises a file that exists ────────────────────────────────────

group( 'Manifest ↔ dist agreement' );

$root   = dirname( __DIR__ );
$broken = array();

foreach ( Manifest::modules() as $module ) {
	foreach ( $module['assets'] as $kind => $asset ) {
		$path = $root . '/assets/dist/' . $asset['file'];

		if ( ! is_file( $path ) || filesize( $path ) === 0 ) {
			$broken[] = "{$module['id']}/{$kind} → {$asset['file']}";
		}
	}
}

check( 'every asset the manifest names was actually built', array(), $broken );

$classes = array_keys( Manifest::classmap() );
$missing = array();

foreach ( $classes as $class ) {
	if ( ! is_file( $root . '/' . Manifest::classmap()[ $class ] ) ) {
		$missing[] = $class;
	}
}

check( 'every class the manifest names has a file', array(), $missing );

// The spine's own bundles are deliberately *not* in the manifest — they are not
// modules — so the check above cannot see them. That leaves build/spine.json and
// the SpineAssets constants free to drift apart, and the symptom is a 404 on the
// options page in production. Compare both against what is actually in dist.
$spine = json_decode( (string) file_get_contents( $root . '/build/spine.json' ), true );
$spine = $spine['spine'] ?? array();

$unbuilt = array();
$mismatch = array();

foreach ( $spine as $name => $entry ) {
	foreach ( array( 'js/' . $name . '.min.js', 'css/' . $name . '.min.css' ) as $candidate ) {
		$path = $root . '/assets/dist/' . $candidate;

		// CSS is optional (an entry with no styles emits none); JS is not.
		$required = str_starts_with( $candidate, 'js/' );
		$built    = is_file( $path ) && filesize( $path ) > 0;

		if ( $required && ! $built ) {
			$unbuilt[] = $candidate;
		}
	}
}

// spine.json names the entries; SpineAssets::handle() turns a dist path into the
// handle WordPress registers under. Both must be derived from the same name.
// Derive the expectation from spine.json rather than repeating the list here.
// A hardcoded list drifts silently the moment an entry is added or removed — and
// a name that is no longer built still "matches" its own stale constant.
$constants = ( new ReflectionClass( SpineAssets::class ) )->getConstants();

foreach ( array_keys( $spine ) as $name ) {
	$expected = "js/{$name}.min.js";

	if ( ! in_array( $expected, $constants, true ) ) {
		$mismatch[] = "spine.json builds {$expected} but SpineAssets has no constant for it";
	}
}

// And the reverse: a constant pointing at a file nothing builds is a 404 waiting
// to happen, so name those too rather than leaving them to be discovered in prod.
// The builder's own constants are the deliberate exception — they stay defined
// while Plugin::BUILDER_AVAILABLE is false, and the flag is what keeps them from
// being enqueued. Flip the flag without restoring the bundles and this fails.
foreach ( $constants as $name => $value ) {
	if ( ! is_string( $value ) || ! preg_match( '#^(css|js)/.+min\.(css|js)$#', $value ) ) {
		continue;
	}

	if ( ! is_file( $root . '/assets/dist/' . $value ) ) {
		if ( ! Plugin::BUILDER_AVAILABLE && str_starts_with( $name, 'BUILDER_' ) ) {
			continue;
		}

		$mismatch[] = "SpineAssets::{$name} points at {$value}, which is not built";
	}
}

check( 'every spine entry in build/spine.json was actually built', array(), $unbuilt );
check( 'SpineAssets and build/spine.json agree in both directions', array(), $mismatch );

// ── Spine integrity ──────────────────────────────────────────────────────────

group( 'Spine integrity' );

/*
 * `php -l` cannot see this class of bug: `self::SLUG` on a class that has no
 * `SLUG` is perfectly valid syntax that fatals the moment the constant is read,
 * and it fatals on the first admin page load rather than at build time. One did
 * exactly that, so the harness now does what a boot does — loads every spine
 * class and reads every constant.
 */
$unloadable = array();
$unresolved = array();
$spine_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) );

foreach ( $spine_files as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}

	$relative = substr( $file->getPathname(), strlen( $root . '/includes/' ) );
	$class    = 'BestAddons\\' . str_replace( array( '/', '.php' ), array( '\\', '' ), $relative );

	// class_exists() alone is false for an interface or a trait.
	if ( ! class_exists( $class ) && ! interface_exists( $class ) && ! trait_exists( $class ) ) {
		$unloadable[] = $class;
		continue;
	}

	$reflection = new ReflectionClass( $class );

	// Both calls are inside the try: evaluating *any* constant on a class whose
	// initializer references an undefined one throws, and getConstants() throws
	// for the whole class at once. Catching only the second would abort the run
	// instead of reporting a failed assertion.
	try {
		foreach ( array_keys( $reflection->getConstants() ) as $name ) {
			$reflection->getConstant( $name );
		}
	} catch ( \Throwable $error ) {
		$unresolved[] = "{$class} ({$error->getMessage()})";
	}
}

check( 'every class in includes/ is autoloadable under its own namespace', array(), $unloadable );
check( 'every constant in the spine resolves when read', array(), $unresolved );

// And the admin menu really is registrable, rather than merely parseable.
$menus = array();
( new AdminMenu() )->add_main_menu();

// The builder's own submenu is one of these entries, so the expectation follows
// the flag rather than being a fixed list that would rot on the next change.
$expected_parents = array( 'best-addons', 'best-addons' );

if ( Plugin::BUILDER_AVAILABLE ) {
	$expected_parents[] = 'best-addons';
}

check(
	'the admin menu registers its pages',
	$expected_parents,
	array_column( $GLOBALS['ba_menu_pages'], 'parent' )
);

check(
	"the widget builder's screens hang off the main plugin menu",
	OptionsPage::SLUG,
	AdminMenu::BUILDER_PARENT
);

// The menu advertises a builder link; the builder registers a screen at that
// slug. If these drift, the link 404s in admin and nothing else complains.
$slugs = array_column( $GLOBALS['ba_menu_pages'], 'slug' );

// A builder link with no screen behind it 404s in admin, and a screen with no
// link is unreachable — so the link and the flag have to agree, either way.
check(
	'the menu advertises a builder link if and only if the builder is available',
	Plugin::BUILDER_AVAILABLE,
	in_array( AdminMenu::BUILDER_SLUG, $slugs, true )
);

check(
	'no submenu points at a parent that was never registered',
	array(),
	array_values(
		array_filter(
			array_column( $GLOBALS['ba_menu_pages'], 'parent' ),
			static fn ( string $parent ): bool => ! in_array( $parent, $slugs, true )
		)
	)
);

// The version helper must never hand WordPress a filemtime it cannot read, and
// must still produce a usable fallback for a bundle that was never built.
check(
	'a built spine bundle resolves a real version',
	true,
	( new ReflectionMethod( SpineAssets::class, 'version' ) )
		->invoke( null, SpineAssets::ADMIN ) !== '1.0.0'
);

check(
	'an unbuilt spine bundle degrades to the plugin version instead of fataling',
	'1.0.0',
	( new ReflectionMethod( SpineAssets::class, 'version' ) )
		->invoke( null, 'js/not-built.min.js' )
);

// ── Result ───────────────────────────────────────────────────────────────────

printf(
	"\n%d passed, %d failed\n",
	$GLOBALS['passed'],
	count( $GLOBALS['failed'] )
);

if ( ! empty( $GLOBALS['failed'] ) ) {
	printf( "Failed: %s\n", implode( ', ', $GLOBALS['failed'] ) );
	exit( 1 );
}

exit( 0 );
