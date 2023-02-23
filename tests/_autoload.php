<?php

// Register the autoloader without polluting the global namespace.
( static function () {
	$root_dir          = dirname( __DIR__ );
	$tec_apm_class_map = [
		'Tribe_Columns'         => $root_dir . '/lib/tribe-columns.class.php',
		'Tribe_Filters'         => $root_dir . '/lib/tribe-filters.class.php',
		'Tribe_Meta_Box'        => $root_dir . '/lib/tribe-meta-box.class.php',
		'Tribe_Meta_Box_Helper' => $root_dir . '/lib/tribe-meta-box-helper.class.php',
	];

	spl_autoload_register( static function ( string $class ) use ( $tec_apm_class_map ): void {

		if ( ! isset( $tec_apm_class_map[ $class ] ) ) {
			return;
		}

		require_once $tec_apm_class_map[ $class ];
	} );
} )();
