<?php

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiDisabled;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiModuleManager;
use MediaWiki\Api\ApiQueryDisabled;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

/**
 * Checks that all API modules, core and extensions, conform to the conventions:
 * - have documentation i18n messages (the test won't catch everything since
 *   i18n messages can vary based on the wiki configuration, but it should
 *   catch many cases for forgotten i18n)
 * - do not have inconsistencies in the parameter definitions
 *
 * @group API
 * @group Database
 * @coversNothing
 */
class ApiStructureTest extends MediaWikiIntegrationTestCase {

	/** @var array Sets of globals to test. Each array element is input to HashConfig */
	private static $testGlobals = [
		[
			MainConfigNames::MiserMode => false,
		],
		[
			MainConfigNames::MiserMode => true,
		],
	];

	private static function newMain(): ApiMain {
		$main = new ApiMain( RequestContext::getMain() );
		$main->getContext()->setLanguage( 'en' );
		$main->getContext()->setTitle(
			Title::makeTitle( NS_SPECIAL, 'Badtitle/dummy title for ApiStructureTest' )
		);

		// Inject ApiDisabled and ApiQueryDisabled so they can be tested too
		$main->getModuleManager()->addModule( 'disabled', 'action', ApiDisabled::class );
		$main->getModuleFromPath( 'query' )
			->getModuleManager()->addModule( 'query-disabled', 'meta', ApiQueryDisabled::class );
		return $main;
	}

	/**
	 * Test a message
	 * @param string|array|Message $msg Message definition, see Message::newFromSpecifier()
	 * @param string $what Which message is being checked
	 */
	private function checkMessage( $msg, $what ) {
		// Message::newFromSpecifier() will throw and fail the test if the specifier isn't valid
		$msg = Message::newFromSpecifier( $msg );
		$this->assertTrue( $msg->exists(), "API $what message \"{$msg->getKey()}\" must exist. Did you forgot to add it to your i18n/en.json?" );
	}

	/**
	 * @dataProvider provideDocumentationExists
	 * @param string $path Module path
	 * @param array $globals Globals to set
	 */
	public function testDocumentationExists( $path, array $globals ) {
		// Set configuration variables
		$this->overrideConfigValues( $globals );

		$main = self::newMain();

		// Fetch module.
		$module = TestingAccessWrapper::newFromObject( $main->getModuleFromPath( $path ) );

		// Test messages for flags.
		foreach ( $module->getHelpFlags() as $flag ) {
			$this->checkMessage( "api-help-flag-$flag", "Flag $flag" );
		}

		// Module description messages.
		$this->checkMessage( $module->getSummaryMessage(), 'Module summary' );
		$extendedDesc = $module->getExtendedDescription();
		if ( is_array( $extendedDesc ) && is_array( $extendedDesc[0] ) ) {
			// The definition in getExtendedDescription() may also specify fallback keys. This is weird,
			// and it was never needed for other API doc messages, so it's only supported here.
			$extendedDesc = Message::newFallbackSequence( $extendedDesc[0] )
				->params( array_slice( $extendedDesc, 1 ) );
		}
		$this->checkMessage( $extendedDesc, 'Module help top text' );

		// Messages for examples.
		foreach ( $module->getExamplesMessages() as $qs => $msg ) {
			$this->assertStringStartsNotWith( 'api.php?', $qs,
				"Query string must not begin with 'api.php?'" );
			$this->checkMessage( $msg, "Example $qs" );
		}
	}

	public static function provideDocumentationExists() {
		$main = self::newMain();
		$paths = self::getSubModulePaths( $main->getModuleManager() );
		array_unshift( $paths, $main->getModulePath() );

		$ret = [];
		foreach ( $paths as $path ) {
			foreach ( self::$testGlobals as $globals ) {
				$g = [];
				foreach ( $globals as $k => $v ) {
					$g[] = "$k=" . var_export( $v, 1 );
				}
				$k = "Module $path with " . implode( ', ', $g );
				$ret[$k] = [ $path, $globals ];
			}
		}
		return $ret;
	}

	private function doTestParameters( string $path, array $params, string $name, ApiMain $main ): void {
		$dataName = $this->dataName();
		$this->assertNotSame( '', $name, "$dataName: Name cannot be empty" );
		$this->assertArrayHasKey( $name, $params, "$dataName: Existence check" );

		$ret = $main->getParamValidator()->checkSettings(
			$main->getModuleFromPath( $path ), $params, $name, []
		);

		// Warn about unknown keys. Don't fail, they might be for forward- or back-compat.
		if ( is_array( $params[$name] ) ) {
			$keys = array_diff(
				array_keys( $params[$name] ),
				$ret['allowedKeys']
			);
			if ( $keys ) {
				// Don't fail for this, for back-compat
				$this->addWarning(
					"$dataName: Unrecognized settings keys were used: " . implode( ', ', $keys )
				);
			}
		}

		if ( count( $ret['issues'] ) === 1 ) {
			$this->fail( "$dataName: Validation failed: " . reset( $ret['issues'] ) );
		} elseif ( $ret['issues'] ) {
			$this->fail( "$dataName: Validation failed:\n* " . implode( "\n* ", $ret['issues'] ) );
		}

		// Check message existence
		$done = [];
		foreach ( $ret['messages'] as $msg ) {
			// We don't really care about the parameters, so do it simply
			$key = $msg->getKey();
			if ( !isset( $done[$key] ) ) {
				$done[$key] = true;
				$this->checkMessage( $key, "$dataName: Parameter" );
			}
		}
	}

	/**
	 * @dataProvider provideParameters
	 */
	public function testParameters( string $path, string $argset, array $args ): void {
		$main = self::newMain();
		$module = $main->getModuleFromPath( $path );
		$params = $module->getFinalParams( ...$args );
		if ( !$params ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		foreach ( $params as $param => $_ ) {
			$this->doTestParameters( $path, $params, $param, $main );
		}
	}

	public static function provideParameters(): Iterator {
		$main = self::newMain();
		$paths = self::getSubModulePaths( $main->getModuleManager() );
		array_unshift( $paths, $main->getModulePath() );
		$argsets = [
			'plain' => [],
			'for help' => [ ApiBase::GET_VALUES_FOR_HELP ],
		];

		foreach ( $paths as $path ) {
			foreach ( $argsets as $argset => $args ) {
				// NOTE: Retrieving the module parameters here may have side effects such as DB queries that
				// should be avoided in data providers (T341731). So do that in the test method instead.
				yield "Module $path, argset $argset" => [ $path, $argset, $args ];
			}
		}
	}

	/**
	 * Return paths of all submodules in an ApiModuleManager, recursively
	 * @param ApiModuleManager $manager
	 * @return string[]
	 */
	private static function getSubModulePaths( ApiModuleManager $manager ) {
		$paths = [];
		foreach ( $manager->getNames() as $name ) {
			$module = $manager->getModule( $name );
			$paths[] = $module->getModulePath();
			$subManager = $module->getModuleManager();
			if ( $subManager ) {
				$paths = array_merge( $paths, self::getSubModulePaths( $subManager ) );
			}
		}
		return $paths;
	}
}
