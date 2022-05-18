<?php

use MediaWiki\Settings\Config\ConfigSchemaAggregator;
use Wikimedia\StaticArrayWriter;

require_once __DIR__ . '/Maintenance.php';
require_once __DIR__ . '/includes/ConfigSchemaDerivativeTrait.php';

// Tell Setup.php to load the config schema from MainConfigSchema rather than
// any generated file, so we can use this script to re-generate a broken schema file.
define( 'MW_USE_CONFIG_SCHEMA_CLASS', 1 );

/**
 * Maintenance script that generates the PHP representation of the config-schema.yaml file.
 *
 * @ingroup Maintenance
 */
class GenerateConfigSchemaArray extends Maintenance {
	use ConfigSchemaDerivativeTrait;

	/** @var string */
	private const DEFAULT_OUTPUT_PATH = __DIR__ . '/../includes/config-schema.php';

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Generate an optimized config-schema.php file.' );

		$this->addOption(
			'output',
			'Path to output relative to $IP. Default: ' . self::DEFAULT_OUTPUT_PATH,
			false,
			true
		);
	}

	public function execute() {
		$settings = $this->loadSettingsSource();
		$aggregator = new ConfigSchemaAggregator();
		foreach ( $settings['config-schema'] as $key => $schema ) {
			$aggregator->addSchema( $key, $schema );
		}
		$schemaInverse = [
			'default' => $aggregator->getDefaults(),
			'type' => $aggregator->getTypes(),
			'mergeStrategy' => $aggregator->getMergeStrategyNames(),
		];

		$keyMask = array_flip( [
			'default',
			'type',
			'mergeStrategy',
			'description'
		] );

		$schemaExtra = [];
		foreach ( $aggregator->getDefinedKeys() as $key ) {
			$sch = $aggregator->getSchemaFor( $key );
			$sch = array_diff_key( $sch, $keyMask );

			if ( $sch ) {
				$schemaExtra[ $key ] = $sch;
			}
		}

		$content = ( new StaticArrayWriter() )->write(
			[
				'config-schema-inverse' => $schemaInverse,
				'config-schema' => $schemaExtra,
			],
			"This file is automatically generated using maintenance/generateConfigSchemaArray.php.\n" .
			"Do not modify this file manually, edit includes/MainConfigSchema.php instead.\n" .
			"phpcs:disable Generic.Files.LineLength"
		);
		$this->writeOutput( self::DEFAULT_OUTPUT_PATH, $content );
	}
}

$maintClass = GenerateConfigSchemaArray::class;
require_once RUN_MAINTENANCE_IF_MAIN;
