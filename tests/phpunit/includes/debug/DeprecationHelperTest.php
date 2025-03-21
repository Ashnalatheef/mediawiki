<?php

use MediaWiki\MainConfigNames;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Debug\DeprecationHelper
 */
class DeprecationHelperTest extends MediaWikiIntegrationTestCase {

	/** @var TestDeprecatedClass */
	private $testClass;

	/** @var TestDeprecatedSubclass */
	private $testSubclass;

	protected function setUp(): void {
		parent::setUp();
		$this->testClass = new TestDeprecatedClass();
		$this->testSubclass = new TestDeprecatedSubclass();
		$this->overrideConfigValue( MainConfigNames::DevelopmentWarnings, false );
	}

	/**
	 * @dataProvider provideGet
	 */
	public function testGet( $propName, $expectedLevel, $expectedMessage ) {
		if ( $expectedLevel ) {
			$this->assertErrorTriggered( function () use ( $propName ) {
				$this->assertSame( null, $this->testClass->$propName );
			}, $expectedLevel, $expectedMessage );
		} else {
			$this->assertDeprecationWarningIssued( function () use ( $propName ) {
				$expectedValue = $propName === 'fallbackDeprecatedMethodName' ? 'FOO' : 1;
				$this->assertSame( $expectedValue, $this->testClass->$propName );
			}, $expectedMessage );
		}
	}

	public static function provideGet() {
		return [
			[ 'protectedDeprecated', null,
				'Use of TestDeprecatedClass::$protectedDeprecated was deprecated in MediaWiki 1.23. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'privateDeprecated', null,
				'Use of TestDeprecatedClass::$privateDeprecated was deprecated in MediaWiki 1.24. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'fallbackDeprecated', null,
				'Use of TestDeprecatedClass::$fallbackDeprecated was deprecated in MediaWiki 1.25. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'fallbackDeprecatedMethodName', null,
				'Use of TestDeprecatedClass::$fallbackDeprecatedMethodName was deprecated in MediaWiki 1.26. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'fallbackGetterOnly', null,
				'Use of TestDeprecatedClass::$fallbackGetterOnly was deprecated in MediaWiki 1.25. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'protectedNonDeprecated', E_USER_ERROR,
				'Cannot access non-public property TestDeprecatedClass::$protectedNonDeprecated' ],
			[ 'privateNonDeprecated', E_USER_ERROR,
				'Cannot access non-public property TestDeprecatedClass::$privateNonDeprecated' ],
			[ 'nonExistent', E_USER_NOTICE, 'Undefined property: TestDeprecatedClass::$nonExistent' ],
		];
	}

	public function testDeprecateDynamicPropertyAccess() {
		$testObject = new class extends TestDeprecatedClass {
			public function __construct() {
				parent::__construct();
				$this->deprecateDynamicPropertiesAccess( '1.23' );
			}
		};
		$this->assertDeprecationWarningIssued(
			static function () use ( $testObject ) {
				$testObject->dynamic_property = 'bla';
			},
			'Use of TestDeprecatedClass::$dynamic_property was deprecated in MediaWiki 1.23. ' .
				'[Called from DeprecationHelperTest::{closure'
		);
	}

	public function testDynamicPropertyNullCoalesce() {
		$testObject = new TestDeprecatedClass();
		$this->assertSame( 'bla', $testObject->dynamic_property ?? 'bla' );
	}

	public function testDynamicPropertyNullCoalesceDeprecated() {
		$testObject = new class extends TestDeprecatedClass {
			public function __construct() {
				parent::__construct();
				$this->deprecateDynamicPropertiesAccess( '1.23' );
			}
		};
		$this->assertDeprecationWarningIssued(
			function () use ( $testObject ) {
				$this->assertSame( 'bla', $testObject->dynamic_property ?? 'bla' );
			},
			'Use of TestDeprecatedClass::$dynamic_property was deprecated in MediaWiki 1.23. ' .
				'[Called from DeprecationHelperTest::{closure'
		);
	}

	public function testDynamicPropertyOnMockObject() {
		$testObject = $this->getMockBuilder( TestDeprecatedClass::class )
			->enableProxyingToOriginalMethods()
			->getMock();
		$testObject->blabla = 'test';
		$this->assertSame( 'test', $testObject->blabla );
	}

	/**
	 * @dataProvider provideSet
	 */
	public function testSet( $propName, $expectedLevel, $expectedMessage, $expectedValue = 0 ) {
		$this->assertPropertySame( 1, $this->testClass, $propName );
		if ( $expectedLevel ) {
			$this->assertErrorTriggered( function () use ( $propName ) {
				$this->testClass->$propName = 0;
				$this->assertPropertySame( 1, $this->testClass, $propName );
			}, $expectedLevel, $expectedMessage );
		} else {
			if ( $propName === 'nonExistent' ) {
				$this->testClass->$propName = 0;
			} else {
				$this->assertDeprecationWarningIssued( function () use ( $propName ) {
					$this->testClass->$propName = 0;
				}, $expectedMessage );
			}
			$this->assertPropertySame( 0, $this->testClass, $propName );
		}

		$this->assertPropertySame( $expectedValue, $this->testClass, $propName );
	}

	public static function provideSet() {
		return [
			[ 'protectedDeprecated', null,
				'Use of TestDeprecatedClass::$protectedDeprecated was deprecated in MediaWiki 1.23. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'privateDeprecated', null,
				'Use of TestDeprecatedClass::$privateDeprecated was deprecated in MediaWiki 1.24. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'fallbackDeprecated', null,
				'Use of TestDeprecatedClass::$fallbackDeprecated was deprecated in MediaWiki 1.25. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'fallbackDeprecatedMethodName', null,
				'Use of TestDeprecatedClass::$fallbackDeprecatedMethodName was deprecated in MediaWiki 1.26. ' .
					'[Called from DeprecationHelperTest::{closure' ],
			[ 'fallbackGetterOnly', E_USER_ERROR,
				'Cannot access non-public property TestDeprecatedClass::$fallbackGetterOnly' ],
			[ 'protectedNonDeprecated', E_USER_ERROR,
				'Cannot access non-public property TestDeprecatedClass::$protectedNonDeprecated', 1 ],
			[ 'privateNonDeprecated', E_USER_ERROR,
				'Cannot access non-public property TestDeprecatedClass::$privateNonDeprecated', 1 ],
			[ 'nonExistent', null,
				'Use of TestDeprecatedClass::$nonExistent was deprecated in MediaWiki 1.23. ' .
					'[Called from DeprecationHelperTest::{closure' ],
		];
	}

	public function testInternalGet() {
		$this->assertSame( [
			'prod' => 1,
			'prond' => 1,
			'prid' => 1,
			'prind' => 1,
		], $this->testClass->getThings() );
	}

	public function testInternalSet() {
		$this->testClass->setThings( 2, 2, 2, 2 );
		$wrapper = TestingAccessWrapper::newFromObject( $this->testClass );
		$this->assertSame( 2, $wrapper->protectedDeprecated );
		$this->assertSame( 2, $wrapper->protectedNonDeprecated );
		$this->assertSame( 2, $wrapper->privateDeprecated );
		$this->assertSame( 2, $wrapper->privateNonDeprecated );
	}

	public function testSubclassGetSet() {
		$fullName = 'TestDeprecatedClass::$privateNonDeprecated';
		$this->assertErrorTriggered( function () {
			$this->assertSame( null, $this->testSubclass->getNondeprecatedPrivateParentProperty() );
		}, E_USER_ERROR, "Cannot access non-public property $fullName" );
		$this->assertErrorTriggered( function () {
			$this->testSubclass->setNondeprecatedPrivateParentProperty( 0 );
			$wrapper = TestingAccessWrapper::newFromObject( $this->testSubclass );
			$this->assertSame( 1, $wrapper->privateNonDeprecated );
		}, E_USER_ERROR, "Cannot access non-public property $fullName" );

		$fullName = 'TestDeprecatedSubclass::$subclassPrivateNondeprecated';
		$this->assertErrorTriggered( function () {
			$this->assertSame( null, $this->testSubclass->subclassPrivateNondeprecated );
		}, E_USER_ERROR, "Cannot access non-public property $fullName" );
		$this->assertErrorTriggered( function () {
			$this->testSubclass->subclassPrivateNondeprecated = 0;
			$wrapper = TestingAccessWrapper::newFromObject( $this->testSubclass );
			$this->assertSame( 1, $wrapper->subclassPrivateNondeprecated );
		}, E_USER_ERROR, "Cannot access non-public property $fullName" );
	}

	protected function assertErrorTriggered( callable $callback, $level, $message ) {
		$actualLevel = $actualMessage = null;
		set_error_handler( static function ( $errorCode, $errorStr ) use ( &$actualLevel, &$actualMessage ) {
			$actualLevel = $errorCode;
			$actualMessage = $errorStr;
		} );
		$callback();
		restore_error_handler();
		$this->assertSame( $level, $actualLevel );
		$this->assertSame( $message, $actualMessage );
	}

	protected function assertPropertySame( $expected, $object, $propName ) {
		try {
			$this->assertSame( $expected, TestingAccessWrapper::newFromObject( $object )->$propName );
		} catch ( ReflectionException $e ) {
			if ( !preg_match( "/Property (TestDeprecated(Class|Subclass)::\\$?)?$propName does not exist/",
				$e->getMessage() )
			) {
				throw $e;
			}
			// property_exists accepts monkey-patching, Reflection / TestingAccessWrapper doesn't
			if ( property_exists( $object, $propName ) ) {
				$this->assertSame( $expected, $object->$propName );
			}
		}
	}

	protected function assertDeprecationWarningIssued( callable $callback, string $expectedMessage ) {
		$this->expectDeprecationAndContinue( '/' . preg_quote( $expectedMessage, '/' ) . '/' );
		$callback();
	}

	/**
	 * Test bad MW version values to throw exceptions as expected
	 *
	 * @dataProvider provideBadMWVersion
	 */
	public function testBadMWVersion( $version, $expected ) {
		$this->expectException( $expected );

		wfDeprecated( __METHOD__, $version );
	}

	public static function provideBadMWVersion() {
		return [
			[ 1, Exception::class ],
			[ 1.33, Exception::class ],
			[ true, Exception::class ],
			[ null, Exception::class ]
		];
	}
}
