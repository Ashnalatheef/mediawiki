<?php
/**
 * Deal with importing all those nasty globals and things
 *
 * Copyright © 2003 Brooke Vibber <bvibber@wikimedia.org>
 * https://www.mediawiki.org/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Request;

use HashBagOStuff;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\FatalError;
use MediaWiki\Exception\MWException;
use MediaWiki\Hook\GetSecurityLogContextHook;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Http\Telemetry;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\PHPSessionHandler;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionId;
use MediaWiki\Session\SessionManager;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;

// The point of this class is to be a wrapper around super globals
// phpcs:disable MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals

/**
 * The WebRequest class encapsulates getting at data passed in the
 * URL or via a POSTed form, stripping illegal input characters, and
 * normalizing Unicode sequences. This class should be used instead
 * of accessing globals such as $_GET, $_POST, and $_COOKIE.
 *
 * @ingroup HTTP
 */
class WebRequest {
	/**
	 * The parameters from $_GET, $_POST and the path router
	 * @var array
	 */
	protected $data;

	/**
	 * The parameters from $_GET. The parameters from the path router are
	 * added by interpolateTitle() during Setup.php.
	 * @var (string|string[])[]
	 */
	protected $queryAndPathParams;

	/**
	 * The parameters from $_GET only.
	 * @var (string|string[])[]
	 */
	protected $queryParams;

	/**
	 * Lazy-initialized request headers indexed by upper-case header name
	 * @var string[]
	 */
	protected $headers = [];

	/**
	 * Flag to make WebRequest::getHeader return an array of values.
	 * @since 1.26
	 */
	public const GETHEADER_LIST = 1;

	/**
	 * Lazy-init response object
	 * @var WebResponse|null
	 */
	protected ?WebResponse $response = null;

	/**
	 * Cached client IP address
	 * @var string
	 */
	private $ip;

	/**
	 * The timestamp of the start of the request, with microsecond precision.
	 * @var float
	 */
	protected $requestTime;

	/**
	 * Cached URL protocol
	 * @var string
	 */
	protected $protocol;

	/**
	 * @var SessionId|null Session ID to use for this request.
	 */
	protected ?SessionId $sessionId = null;
	/**
	 * @var Session|null Session to use for this request.
	 */
	protected ?Session $session = null;

	/** @var bool Whether this HTTP request is "safe" (even if it is an HTTP post) */
	protected $markedAsSafe = false;

	/** Cache variable for getSecurityLogContext(). */
	private ?HashBagOStuff $securityLogContext;

	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$this->requestTime = $_SERVER['REQUEST_TIME_FLOAT'];

		// POST overrides GET data
		// We don't use $_REQUEST here to avoid interference from cookies...
		$this->data = $_POST + $_GET;

		$this->queryAndPathParams = $this->queryParams = $_GET;
	}

	/**
	 * Returns an entry from the $_SERVER array.
	 * This exists mainly to allow us to inject fake values for testing.
	 *
	 * @param string $name A well known key for $_SERVER,
	 *        see <https://www.php.net/manual/en/reserved.variables.server.php>.
	 *        Only fields that contain string values are supported,
	 *        so 'argv' and 'argc' are not safe to use.
	 * @param ?string $default The value to return if no value is known for the
	 *        key $name.
	 *
	 * @return ?string
	 */
	protected function getServerInfo( string $name, ?string $default = null ): ?string {
		return isset( $_SERVER[$name] ) ? (string)$_SERVER[$name] : $default;
	}

	/**
	 * Extract relevant query arguments from the http request uri's path
	 * to be merged with the normal php provided query arguments.
	 * Tries to use the REQUEST_URI data if available and parses it
	 * according to the wiki's configuration looking for any known pattern.
	 *
	 * If the REQUEST_URI is not provided we'll fall back on the PATH_INFO
	 * provided by the server if any and use that to set a 'title' parameter.
	 *
	 * This internal method handles many odd cases and is tailored specifically for
	 * used by WebRequest::interpolateTitle, for index.php requests.
	 * Consider using WebRequest::getRequestPathSuffix for other path-related use cases.
	 *
	 * @param string $want If this is not 'all', then the function
	 * will return an empty array if it determines that the URL is
	 * inside a rewrite path.
	 *
	 * @return string[] Any query arguments found in path matches.
	 * @throws FatalError If invalid routes are configured (T48998)
	 */
	protected function getPathInfo( $want = 'all' ) {
		// PATH_INFO is mangled due to https://bugs.php.net/bug.php?id=31892
		// And also by Apache 2.x, double slashes are converted to single slashes.
		// So we will use REQUEST_URI if possible.
		$url = $this->getServerInfo( 'REQUEST_URI' );
		if ( $url !== null ) {
			// Slurp out the path portion to examine...
			if ( !preg_match( '!^https?://!', $url ) ) {
				$url = 'http://unused' . $url;
			}
			$a = parse_url( $url );
			if ( !$a ) {
				return [];
			}
			$path = $a['path'] ?? '';

			global $wgScript;
			if ( $path == $wgScript && $want !== 'all' ) {
				// Script inside a rewrite path?
				// Abort to keep from breaking...
				return [];
			}

			$router = new PathRouter;

			// Raw PATH_INFO style
			$router->add( "$wgScript/$1" );

			global $wgArticlePath;
			if ( $wgArticlePath ) {
				$router->validateRoute( $wgArticlePath, 'wgArticlePath' );
				$router->add( $wgArticlePath );
			}

			global $wgActionPaths;
			$articlePaths = PathRouter::getActionPaths( $wgActionPaths, $wgArticlePath );
			if ( $articlePaths ) {
				$router->add( $articlePaths, [ 'action' => '$key' ] );
			}

			$services = MediaWikiServices::getInstance();
			global $wgVariantArticlePath;
			if ( $wgVariantArticlePath ) {
				$router->validateRoute( $wgVariantArticlePath, 'wgVariantArticlePath' );
				$router->add( $wgVariantArticlePath,
					[ 'variant' => '$2' ],
					[ '$2' => $services->getLanguageConverterFactory()
						->getLanguageConverter( $services->getContentLanguage() )
						->getVariants() ]
				);
			}

			( new HookRunner( $services->getHookContainer() ) )->onWebRequestPathInfoRouter( $router );

			$matches = $router->parse( $path );
		} else {
			global $wgUsePathInfo;
			$matches = [];
			if ( $wgUsePathInfo ) {
				$origPathInfo = $this->getServerInfo( 'ORIG_PATH_INFO' ) ?? '';
				$pathInfo = $this->getServerInfo( 'PATH_INFO' ) ?? '';
				if ( $origPathInfo !== '' ) {
					// Mangled PATH_INFO
					// https://bugs.php.net/bug.php?id=31892
					// Also reported when ini_get('cgi.fix_pathinfo')==false
					$matches['title'] = substr( $origPathInfo, 1 );
				} elseif ( $pathInfo !== '' ) {
					// Regular old PATH_INFO yay
					$matches['title'] = substr( $pathInfo, 1 );
				}
			}
		}

		return $matches;
	}

	/**
	 * If the request URL matches a given base path, extract the path part of
	 * the request URL after that base, and decode escape sequences in it.
	 *
	 * If the request URL does not match, false is returned.
	 *
	 * @since 1.35
	 * @param string $basePath The base URL path. Trailing slashes will be
	 *   stripped.
	 * @param ?string $requestUrl The request URL to examine. If not given, the
	 *   URL returned by getGlobalRequestURL() will be used.
	 * @return string|false
	 */
	public static function getRequestPathSuffix( string $basePath, ?string $requestUrl = null ) {
		$basePath = rtrim( $basePath, '/' ) . '/';
		$requestUrl ??= self::getGlobalRequestURL();
		$qpos = strpos( $requestUrl, '?' );
		if ( $qpos !== false ) {
			$requestPath = substr( $requestUrl, 0, $qpos );
		} else {
			$requestPath = $requestUrl;
		}
		if ( !str_starts_with( $requestPath, $basePath ) ) {
			return false;
		}
		return rawurldecode( substr( $requestPath, strlen( $basePath ) ) );
	}

	/**
	 * Work out an appropriate URL prefix containing scheme and host, based on
	 * information detected from $_SERVER
	 *
	 * @param bool|null $assumeProxiesUseDefaultProtocolPorts When the wiki is running behind a proxy
	 * and this is set to true, assumes that the proxy exposes the wiki on the standard ports
	 * (443 for https and 80 for http). Added in 1.38. Calls without this argument are
	 * supported for backwards compatibility but deprecated.
	 *
	 * @return string
	 */
	public static function detectServer( $assumeProxiesUseDefaultProtocolPorts = null ) {
		$assumeProxiesUseDefaultProtocolPorts ??= $GLOBALS['wgAssumeProxiesUseDefaultProtocolPorts'];

		$proto = self::detectProtocol();
		$stdPort = $proto === 'https' ? 443 : 80;

		$varNames = [ 'HTTP_HOST', 'SERVER_NAME', 'HOSTNAME', 'SERVER_ADDR' ];
		$host = 'localhost';
		$port = $stdPort;
		foreach ( $varNames as $varName ) {
			if ( !isset( $_SERVER[$varName] ) ) {
				continue;
			}

			$parts = IPUtils::splitHostAndPort( $_SERVER[$varName] );
			if ( !$parts ) {
				// Invalid, do not use
				continue;
			}

			$host = $parts[0];
			if ( $assumeProxiesUseDefaultProtocolPorts && isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
				// T72021: Assume that upstream proxy is running on the default
				// port based on the protocol. We have no reliable way to determine
				// the actual port in use upstream.
				$port = $stdPort;
			} elseif ( $parts[1] === false ) {
				if ( isset( $_SERVER['SERVER_PORT'] ) ) {
					$port = intval( $_SERVER['SERVER_PORT'] );
				} // else leave it as $stdPort
			} else {
				$port = $parts[1];
			}
			break;
		}

		return $proto . '://' . IPUtils::combineHostAndPort( $host, $port, $stdPort );
	}

	/**
	 * Detect the protocol from $_SERVER.
	 * This is for use prior to Setup.php, when no WebRequest object is available.
	 * At other times, use the non-static function getProtocol().
	 *
	 * @return string
	 */
	public static function detectProtocol() {
		if ( ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ||
			( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
			$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) ) {
			return 'https';
		} else {
			return 'http';
		}
	}

	/**
	 * Get the number of seconds to have elapsed since request start,
	 * in fractional seconds, with microsecond resolution.
	 *
	 * @return float
	 * @since 1.25
	 */
	public function getElapsedTime() {
		return microtime( true ) - $this->requestTime;
	}

	/**
	 * Get the current request ID.
	 *
	 * This is usually based on the `X-Request-Id` header, or the `UNIQUE_ID`
	 * environment variable, falling back to (process cached) randomly-generated string.
	 *
	 * @return string
	 * @since 1.27
	 */
	public static function getRequestId() {
		return Telemetry::getInstance()->getRequestId();
	}

	/**
	 * Override the unique request ID. This is for sub-requests, such as jobs,
	 * that wish to use the same id but are not part of the same execution context.
	 *
	 * @param string|null $newId
	 * @since 1.27
	 */
	public static function overrideRequestId( $newId ) {
		$telemetry = Telemetry::getInstance();
		if ( $newId === null ) {
			$telemetry->regenerateRequestId();
		} else {
			$telemetry->overrideRequestId( $newId );
		}
	}

	/**
	 * Get the current URL protocol (http or https)
	 * @return string
	 */
	public function getProtocol() {
		$this->protocol ??= self::detectProtocol();
		return $this->protocol;
	}

	/**
	 * Check for title, action, and/or variant data in the URL
	 * and interpolate it into the GET variables.
	 * This should only be run after the content language is available,
	 * as we may need the list of language variants to determine
	 * available variant URLs.
	 */
	public function interpolateTitle() {
		$matches = $this->getPathInfo( 'title' );
		foreach ( $matches as $key => $val ) {
			$this->data[$key] = $this->queryAndPathParams[$key] = $val;
		}
	}

	/**
	 * URL rewriting function; tries to extract page title and,
	 * optionally, one other fixed parameter value from a URL path.
	 *
	 * @param string $path The URL path given from the client
	 * @param array $bases One or more URLs, optionally with $1 at the end
	 * @param string|false $key If provided, the matching key in $bases will be
	 *    passed on as the value of this URL parameter
	 * @return array Array of URL variables to interpolate; empty if no match
	 */
	public static function extractTitle( $path, $bases, $key = false ) {
		foreach ( (array)$bases as $keyValue => $base ) {
			// Find the part after $wgArticlePath
			$base = str_replace( '$1', '', $base );
			if ( str_starts_with( $path, $base ) ) {
				$raw = substr( $path, strlen( $base ) );
				if ( $raw !== '' ) {
					$matches = [ 'title' => rawurldecode( $raw ) ];
					if ( $key ) {
						$matches[$key] = $keyValue;
					}
					return $matches;
				}
			}
		}
		return [];
	}

	/**
	 * Recursively normalizes UTF-8 strings in the given array.
	 *
	 * @param string|array $data
	 * @return array|string Cleaned-up version of the given
	 * @internal
	 */
	public function normalizeUnicode( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $val ) {
				$data[$key] = $this->normalizeUnicode( $val );
			}
		} else {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$data = $contLang->normalize( $data );
		}
		return $data;
	}

	/**
	 * Fetch a value from the given array or return $default if it's not set.
	 *
	 * @param array $arr
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 * @return-taint tainted
	 */
	private function getGPCVal( $arr, $name, $default ) {
		# PHP is so nice to not touch input data, except sometimes:
		# https://www.php.net/variables.external#language.variables.external.dot-in-names
		# Work around PHP *feature* to avoid *bugs* elsewhere.
		$name = strtr( $name, '.', '_' );

		if ( !isset( $arr[$name] ) ) {
			return $default;
		}

		$data = $arr[$name];
		# Optimisation: Skip UTF-8 normalization and legacy transcoding for simple ASCII strings.
		$isAsciiStr = ( is_string( $data ) && preg_match( '/[^\x20-\x7E]/', $data ) === 0 );
		if ( !$isAsciiStr ) {
			if ( isset( $_GET[$name] ) && is_string( $data ) ) {
				# Check for alternate/legacy character encoding.
				$data = MediaWikiServices::getInstance()
					->getContentLanguage()
					->checkTitleEncoding( $data );
			}
			$data = $this->normalizeUnicode( $data );
		}

		return $data;
	}

	/**
	 * Fetch a string from this web request's $_GET, $_POST or path router vars WITHOUT any
	 * Unicode or line break normalization. This is a fast alternative for values that are known
	 * to be simple, e.g. pure ASCII. When reading user input, use {@see getText} instead.
	 *
	 * Array values are discarded for security reasons. Use {@see getArray} or {@see getIntArray}.
	 *
	 * @since 1.28
	 * @param string $name
	 * @return string|null The value, or null if none set
	 * @return-taint tainted
	 */
	public function getRawVal( $name ): ?string {
		$name = strtr( $name, '.', '_' ); // See comment in self::getGPCVal()
		if ( !isset( $this->data[$name] ) || is_array( $this->data[$name] ) ) {
			return null;
		}
		return (string)$this->data[$name];
	}

	/**
	 * Fetch a text string from this web request's $_GET, $_POST or path router vars and partially
	 * normalize it.
	 *
	 * Use of this method is discouraged. It doesn't normalize line breaks and defaults to null
	 * instead of the empty string. Instead:
	 * - Use {@see getText} when reading user input or form fields that are expected to contain
	 *   non-ASCII characters.
	 * - Use {@see getRawVal} when reading ASCII strings, such as parameters used to select
	 *   predefined behaviour in the software.
	 *
	 * Array values are discarded for security reasons. Use {@see getArray} or {@see getIntArray}.
	 *
	 * @param string $name
	 * @param string|null $default
	 * @return string|null The input value, or $default if none set
	 * @return-taint tainted
	 */
	public function getVal( $name, $default = null ) {
		$val = $this->getGPCVal( $this->data, $name, $default );
		if ( is_array( $val ) ) {
			$val = $default;
		}

		return $val === null ? null : (string)$val;
	}

	/**
	 * Fetch a text string from this web request's $_GET, $_POST or path router vars and return it
	 * in normalized form.
	 *
	 * This normalizes Unicode sequences (via {@see getGPCVal}) and line breaks.
	 *
	 * This should be used for all user input and form fields that are expected to contain non-ASCII
	 * characters, especially if the value will be stored or compared against stored values. Without
	 * normalization, logically identically values might not match when they are typed on different
	 * OS' or keyboards.
	 *
	 * Array values are discarded for security reasons. Use {@see getArray} or {@see getIntArray}.
	 *
	 * @param string $name
	 * @param string $default
	 * @return string The normalized input value, or $default if none set
	 * @return-taint tainted
	 */
	public function getText( $name, $default = '' ) {
		$val = $this->getVal( $name, $default );
		return str_replace( "\r\n", "\n", $val );
	}

	/**
	 * Set an arbitrary value into our get/post data.
	 *
	 * @param string $key Key name to use
	 * @param mixed $value Value to set
	 * @return mixed Old value if one was present, null otherwise
	 */
	public function setVal( $key, $value ) {
		$ret = $this->data[$key] ?? null;
		$this->data[$key] = $value;
		return $ret;
	}

	/**
	 * Unset an arbitrary value from our get/post data.
	 *
	 * @param string $key Key name to use
	 * @return mixed Old value if one was present, null otherwise
	 */
	public function unsetVal( $key ) {
		if ( !isset( $this->data[$key] ) ) {
			$ret = null;
		} else {
			$ret = $this->data[$key];
			unset( $this->data[$key] );
		}
		return $ret;
	}

	/**
	 * Fetch an array from this web request's $_GET, $_POST or path router vars,
	 * or return $default if it's not set. If source was scalar, will return an
	 * array with a single element. If no source and no default, returns null.
	 *
	 * @param string $name
	 * @param array|null $default Optional default (or null)
	 * @return array|null
	 * @return-taint tainted
	 */
	public function getArray( $name, $default = null ) {
		$val = $this->getGPCVal( $this->data, $name, $default );
		if ( $val === null ) {
			return null;
		} else {
			return (array)$val;
		}
	}

	/**
	 * Fetch an array of integers from this web request's $_GET, $_POST or
	 * path router vars, or return $default if it's not set. If source was
	 * scalar, will return an array with a single element. If no source and
	 * no default, returns null. If an array is returned, contents are
	 * guaranteed to be integers.
	 *
	 * @param string $name
	 * @param array|null $default Option default (or null)
	 * @return int[]|null
	 * @return-taint none
	 */
	public function getIntArray( $name, $default = null ) {
		$val = $this->getArray( $name, $default );
		if ( is_array( $val ) ) {
			$val = array_map( 'intval', $val );
		}
		return $val;
	}

	/**
	 * Fetch an integer value from this web request's $_GET, $_POST or
	 * path router vars, or return $default if not set. Guaranteed to return
	 * an integer; non-numeric input will typically return 0.
	 *
	 * @param string $name
	 * @param int $default
	 * @return int
	 */
	public function getInt( $name, $default = 0 ): int {
		return intval( $this->getRawVal( $name ) ?? $default );
	}

	/**
	 * Fetch an integer value from this web request's $_GET, $_POST or
	 * path router vars, or return null if empty. Guaranteed to return an
	 * integer or null; non-numeric input will typically return null.
	 *
	 * @param string $name
	 * @return int|null
	 */
	public function getIntOrNull( $name ): ?int {
		$val = $this->getRawVal( $name );
		return is_numeric( $val ) ? intval( $val ) : null;
	}

	/**
	 * Fetch a floating point value from this web request's $_GET, $_POST
	 * or path router vars, or return $default if not set. Guaranteed to
	 * return a float; non-numeric input will typically return 0.
	 *
	 * @since 1.23
	 * @param string $name
	 * @param float $default
	 * @return float
	 */
	public function getFloat( $name, $default = 0.0 ): float {
		return floatval( $this->getRawVal( $name ) ?? $default );
	}

	/**
	 * Fetch a boolean value from this web request's $_GET, $_POST or path
	 * router vars or return $default if not set. Guaranteed to return true
	 * or false, with normal PHP semantics for boolean interpretation of strings.
	 *
	 * @param string $name
	 * @param bool $default
	 * @return bool
	 */
	public function getBool( $name, $default = false ): bool {
		return (bool)( $this->getRawVal( $name ) ?? $default );
	}

	/**
	 * Fetch a boolean value from this web request's $_GET, $_POST or path router
	 * vars or return $default if not set. Unlike getBool, the string "false" will
	 * result in boolean false, which is useful when interpreting information sent
	 * from JavaScript.
	 *
	 * @param string $name
	 * @param bool $default
	 * @return bool
	 */
	public function getFuzzyBool( $name, $default = false ): bool {
		$value = $this->getRawVal( $name );
		if ( $value === null ) {
			return (bool)$default;
		}

		return $value && strcasecmp( $value, 'false' ) !== 0;
	}

	/**
	 * Return true if the named value is set in this web request's $_GET,
	 * $_POST or path router vars, whatever that value is (even "0").
	 * Return false if the named value is not set. Example use is checking
	 * for the presence of check boxes in forms.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function getCheck( $name ): bool {
		# Checkboxes and buttons are only present when clicked
		# Presence connotes truth, absence false
		return $this->getRawVal( $name ) !== null;
	}

	/**
	 * Extracts the (given) named values from this web request's $_GET, $_POST or path
	 * router vars into an array. No transformation is performed on the values.
	 *
	 * @param string ...$names If no arguments are given, returns all input values
	 * @return array
	 * @return-taint tainted
	 */
	public function getValues( ...$names ) {
		if ( $names === [] ) {
			$names = array_keys( $this->data );
		}

		$retVal = [];
		foreach ( $names as $name ) {
			$value = $this->getGPCVal( $this->data, $name, null );
			if ( $value !== null ) {
				$retVal[$name] = $value;
			}
		}
		return $retVal;
	}

	/**
	 * Returns the names of this web request's $_GET, $_POST or path router vars,
	 * excluding those in $exclude.
	 *
	 * @param array $exclude
	 * @return array
	 * @return-taint tainted
	 */
	public function getValueNames( $exclude = [] ) {
		return array_diff( array_keys( $this->getValues() ), $exclude );
	}

	/**
	 * Get the values passed in $_GET and the path router parameters.
	 * No transformation is performed on the values.
	 *
	 * @codeCoverageIgnore
	 * @return (string|string[])[] Might contain arrays in case there was a `&param[]=…` parameter
	 * @return-taint tainted
	 */
	public function getQueryValues() {
		return $this->queryAndPathParams;
	}

	/**
	 * Get the values passed in $_GET only, not including the path
	 * router parameters. This is less suitable for self-links to index.php but
	 * useful for other entry points. No transformation is performed on the
	 * values.
	 *
	 * @since 1.34
	 * @return (string|string[])[] Might contain arrays in case there was a `&param[]=…` parameter
	 */
	public function getQueryValuesOnly() {
		return $this->queryParams;
	}

	/**
	 * Get the values passed via POST.
	 * No transformation is performed on the values.
	 *
	 * @since 1.32
	 * @codeCoverageIgnore
	 * @return (string|string[])[] Might contain arrays in case there was a `&param[]=…` parameter
	 */
	public function getPostValues() {
		return $_POST;
	}

	/**
	 * Return the contents of the URL query string with no decoding. Use when you need to
	 * know exactly what was sent, e.g. for an OAuth signature over the elements.
	 *
	 * @codeCoverageIgnore
	 * @return string
	 * @return-taint tainted
	 */
	public function getRawQueryString() {
		return $this->getServerInfo( 'QUERY_STRING' ) ?? '';
	}

	/**
	 * Return the contents of the POST with no decoding. Use when you need to
	 * know exactly what was sent, e.g. for an OAuth signature over the elements.
	 *
	 * @return string
	 * @return-taint tainted
	 */
	public function getRawPostString() {
		if ( !$this->wasPosted() ) {
			return '';
		}
		return $this->getRawInput();
	}

	/**
	 * Return the raw request body, with no processing. Cached since some methods
	 * disallow reading the stream more than once. As stated in the php docs, this
	 * does not work with enctype="multipart/form-data".
	 *
	 * @return string
	 * @return-taint tainted
	 */
	public function getRawInput() {
		static $input = null;
		$input ??= file_get_contents( 'php://input' );
		return $input;
	}

	/**
	 * Get the HTTP method used for this request.
	 *
	 * @return string
	 */
	public function getMethod() {
		return $this->getServerInfo( 'REQUEST_METHOD' ) ?: 'GET';
	}

	/**
	 * Returns true if the present request was reached by a POST operation,
	 * false otherwise (GET, HEAD, or command-line).
	 *
	 * Note that values retrieved by the object may come from the
	 * GET URL etc even on a POST request.
	 *
	 * @return bool
	 */
	public function wasPosted() {
		return $this->getMethod() == 'POST';
	}

	/**
	 * Return the session for this request
	 *
	 * This might unpersist an existing session if it was invalid.
	 *
	 * @since 1.27
	 * @return Session
	 */
	public function getSession(): Session {
		$sessionId = $this->getSessionId();

		// Destroy the old session if someone has set a new session ID
		if ( $this->session && $sessionId && $this->session->getId() !== $sessionId->getId() ) {
			$this->session = null;
		}

		// Look up session by ID if provided
		if ( !$this->session && $sessionId ) {
			$this->session = SessionManager::singleton()->getSessionById( $sessionId->getId(), true, $this );
		}

		// If it was not provided, or a session with that ID doesn't exist, create a new one
		if ( !$this->session ) {
			$this->session = SessionManager::singleton()->getSessionForRequest( $this );
			$this->sessionId = $this->session->getSessionId();
		}
		return $this->session;
	}

	/**
	 * Set the session for this request
	 * @since 1.27
	 * @internal For use by MediaWiki\Session classes only
	 * @param SessionId $sessionId
	 */
	public function setSessionId( SessionId $sessionId ) {
		$this->sessionId = $sessionId;
	}

	/**
	 * Get the session id for this request, if any
	 * @since 1.27
	 * @internal For use by MediaWiki\Session classes only
	 * @return SessionId|null
	 */
	public function getSessionId() {
		// If this is the main request, and we're using built-in PHP session handling,
		// and the session ID wasn't overridden, return the PHP session's ID.
		if ( PHPSessionHandler::isEnabled() && $this === RequestContext::getMain()->getRequest() ) {
			$id = session_id();
			if ( $id !== '' ) {
				// Someone used session_id(), so we need to follow suit.
				// We can't even cache this in $this->sessionId.
				return new SessionId( $id );
			}
		}

		return $this->sessionId;
	}

	/**
	 * Get a cookie from the $_COOKIE jar
	 *
	 * @param string $key The name of the cookie
	 * @param string|null $prefix A prefix to use for the cookie name, if not $wgCookiePrefix
	 * @param mixed|null $default What to return if the value isn't found
	 * @return mixed Cookie value or $default if the cookie not set
	 * @return-taint tainted
	 */
	public function getCookie( $key, $prefix = null, $default = null ) {
		if ( $prefix === null ) {
			global $wgCookiePrefix;
			$prefix = $wgCookiePrefix;
		}
		$name = $prefix . $key;
		// Work around mangling of $_COOKIE
		$name = strtr( $name, '.', '_' );
		if ( isset( $_COOKIE[$name] ) ) {
			// For duplicate cookies in the format of name[]=value;name[]=value2,
			// PHP will assign an array value for the 'name' cookie in $_COOKIE.
			// Neither RFC 6265 nor its preceding RFCs define such behavior,
			// and MediaWiki does not rely on it either, so treat the cookie as absent if so (T363980).
			if ( is_array( $_COOKIE[$name] ) ) {
				return $default;
			}
			return $_COOKIE[$name];
		} else {
			return $default;
		}
	}

	/**
	 * Get a cookie set with SameSite=None.
	 *
	 * @deprecated since 1.42 use getCookie(), but note the different $prefix default
	 *
	 * @param string $key The name of the cookie
	 * @param string $prefix A prefix to use, empty by default
	 * @param mixed|null $default What to return if the value isn't found
	 * @return mixed Cookie value or $default if the cookie is not set
	 */
	public function getCrossSiteCookie( $key, $prefix = '', $default = null ) {
		wfDeprecated( __METHOD__, '1.42' );
		return $this->getCookie( $key, $prefix, $default );
	}

	/**
	 * Return the path and query string portion of the main request URI.
	 * This will be suitable for use as a relative link in HTML output.
	 *
	 * @throws MWException
	 * @return string
	 * @return-taint tainted
	 */
	public static function getGlobalRequestURL() {
		// This method is called on fatal errors; it should not depend on anything complex.

		if ( isset( $_SERVER['REQUEST_URI'] ) && strlen( $_SERVER['REQUEST_URI'] ) ) {
			$base = $_SERVER['REQUEST_URI'];
		} elseif ( isset( $_SERVER['HTTP_X_ORIGINAL_URL'] )
			&& strlen( $_SERVER['HTTP_X_ORIGINAL_URL'] )
		) {
			// Probably IIS; doesn't set REQUEST_URI
			$base = $_SERVER['HTTP_X_ORIGINAL_URL'];
		} elseif ( isset( $_SERVER['SCRIPT_NAME'] ) ) {
			$base = $_SERVER['SCRIPT_NAME'];
			if ( isset( $_SERVER['QUERY_STRING'] ) && $_SERVER['QUERY_STRING'] != '' ) {
				$base .= '?' . $_SERVER['QUERY_STRING'];
			}
		} else {
			// This shouldn't happen!
			throw new MWException(
				"Web server doesn't provide either " .
				"REQUEST_URI, HTTP_X_ORIGINAL_URL or SCRIPT_NAME. Report details " .
				"of your web server configuration to https://phabricator.wikimedia.org/"
			);
		}
		// User-agents should not send a fragment with the URI, but
		// if they do, and the web server passes it on to us, we
		// need to strip it or we get false-positive redirect loops
		// or weird output URLs
		$hash = strpos( $base, '#' );
		if ( $hash !== false ) {
			$base = substr( $base, 0, $hash );
		}

		if ( $base[0] == '/' ) {
			// More than one slash will look like it is protocol relative
			return preg_replace( '!^/+!', '/', $base );
		} else {
			// We may get paths with a host prepended; strip it.
			return preg_replace( '!^[^:]+://[^/]+/+!', '/', $base );
		}
	}

	/**
	 * Return the path and query string portion of the request URI.
	 * This will be suitable for use as a relative link in HTML output.
	 *
	 * @throws MWException
	 * @return string
	 * @return-taint tainted
	 */
	public function getRequestURL() {
		return self::getGlobalRequestURL();
	}

	/**
	 * Return the request URI with the canonical service and hostname, path,
	 * and query string. This will be suitable for use as an absolute link
	 * in HTML or other output.
	 *
	 * If $wgServer is protocol-relative, this will return a fully
	 * qualified URL with the protocol of this request object.
	 *
	 * @return string
	 * @return-taint tainted
	 */
	public function getFullRequestURL() {
		$urlUtils = MediaWikiServices::getInstance()->getUrlUtils();
		// Pass an explicit PROTO constant instead of PROTO_CURRENT so that we
		// do not rely on state from the global $wgRequest object (which it would,
		// via UrlUtils::getServer()/UrlUtils::expand()/$wgRequest->protocol).
		if ( $this->getProtocol() === 'http' ) {
			return ( $urlUtils->getServer( PROTO_HTTP ) ?? '' ) . $this->getRequestURL();
		} else {
			return ( $urlUtils->getServer( PROTO_HTTPS ) ?? '' ) . $this->getRequestURL();
		}
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return string
	 */
	public function appendQueryValue( $key, $value ) {
		return $this->appendQueryArray( [ $key => $value ] );
	}

	/**
	 * Appends or replaces value of query variables.
	 *
	 * @param array $array Array of values to replace/add to query
	 * @return string
	 */
	public function appendQueryArray( $array ) {
		$newquery = $this->getQueryValues();
		unset( $newquery['title'] );
		$newquery = array_merge( $newquery, $array );

		return wfArrayToCgi( $newquery );
	}

	/**
	 * Check for limit and offset parameters on the input, and return sensible
	 * defaults if not given. The limit must be positive and is capped at 5000.
	 * Offset must be positive but is not capped.
	 *
	 * @param UserIdentity $user UserIdentity to get option for
	 * @param int $deflimit Limit to use if no input and the user hasn't set the option.
	 * @param string $optionname To specify an option other than rclimit to pull from.
	 * @return int[] First element is limit, second is offset
	 */
	public function getLimitOffsetForUser( UserIdentity $user, $deflimit = 50, $optionname = 'rclimit' ) {
		$limit = $this->getInt( 'limit', 0 );
		if ( $limit < 0 ) {
			$limit = 0;
		}
		if ( ( $limit == 0 ) && ( $optionname != '' ) ) {
			$limit = MediaWikiServices::getInstance()
				->getUserOptionsLookup()
				->getIntOption( $user, $optionname );
		}
		if ( $limit <= 0 ) {
			$limit = $deflimit;
		}
		if ( $limit > 5000 ) {
			$limit = 5000; # We have *some* limits...
		}

		$offset = $this->getInt( 'offset', 0 );
		if ( $offset < 0 ) {
			$offset = 0;
		}

		return [ $limit, $offset ];
	}

	/**
	 * Return the path to the temporary file where PHP has stored the upload.
	 *
	 * @param string $key
	 * @return string|null String or null if no such file.
	 */
	public function getFileTempname( $key ) {
		return $this->getUpload( $key )->getTempName();
	}

	/**
	 * Return the upload error or 0
	 *
	 * @param string $key
	 * @return int
	 */
	public function getUploadError( $key ) {
		return $this->getUpload( $key )->getError();
	}

	/**
	 * Return the original filename of the uploaded file, as reported by
	 * the submitting user agent. HTML-style character entities are
	 * interpreted and normalized to Unicode normalization form C, in part
	 * to deal with weird input from Safari with non-ASCII filenames.
	 *
	 * Other than this the name is not verified for being a safe filename.
	 *
	 * @param string $key
	 * @return string|null String or null if no such file.
	 */
	public function getFileName( $key ) {
		return $this->getUpload( $key )->getName();
	}

	/**
	 * Return a MediaWiki\Request\WebRequestUpload object corresponding to the key
	 *
	 * @param string $key
	 * @return WebRequestUpload
	 */
	public function getUpload( $key ) {
		return new WebRequestUpload( $this, $key );
	}

	/**
	 * Return a handle to WebResponse style object, for setting cookies,
	 * headers and other stuff, for Request being worked on.
	 */
	public function response(): WebResponse {
		/* Lazy initialization of response object for this request */
		if ( !$this->response ) {
			$this->response = new WebResponse();
		}
		return $this->response;
	}

	/**
	 * Initialise the header list
	 */
	protected function initHeaders() {
		if ( count( $this->headers ) ) {
			return;
		}

		$this->headers = array_change_key_case( getallheaders(), CASE_UPPER );
	}

	/**
	 * Get an array containing all request headers
	 *
	 * @return string[] Mapping header name to its value
	 * @return-taint tainted
	 */
	public function getAllHeaders() {
		$this->initHeaders();
		return $this->headers;
	}

	/**
	 * Get a request header, or false if it isn't set.
	 *
	 * @param string $name Case-insensitive header name
	 * @param int $flags Bitwise combination of:
	 *   WebRequest::GETHEADER_LIST  Treat the header as a comma-separated list
	 *                               of values, as described in RFC 2616 § 4.2.
	 *                               (since 1.26).
	 * @return string|string[]|false False if header is unset; otherwise the
	 *  header value(s) as either a string (the default) or an array, if
	 *  WebRequest::GETHEADER_LIST flag was set.
	 * @return-taint tainted
	 */
	public function getHeader( $name, $flags = 0 ) {
		$this->initHeaders();
		$name = strtoupper( $name );
		if ( !isset( $this->headers[$name] ) ) {
			return false;
		}
		$value = $this->headers[$name];
		if ( $flags & self::GETHEADER_LIST ) {
			$value = array_map( 'trim', explode( ',', $value ) );
		}
		return $value;
	}

	/**
	 * Get data from the session
	 *
	 * @note Prefer $this->getSession() instead if making multiple calls.
	 * @param string $key Name of key in the session
	 * @return mixed
	 */
	public function getSessionData( $key ) {
		return $this->getSession()->get( $key );
	}

	/**
	 * @note Prefer $this->getSession() instead if making multiple calls.
	 * @param string $key Name of key in the session
	 * @param mixed $data
	 */
	public function setSessionData( $key, $data ) {
		$this->getSession()->set( $key, $data );
	}

	/**
	 * Parse the Accept-Language header sent by the client into an array
	 *
	 * @return array [ languageCode => q-value ] sorted by q-value in
	 *   descending order then appearing time in the header in ascending order.
	 * May contain the "language" '*', which applies to languages other than those explicitly listed.
	 *
	 * This logic is aligned with RFC 7231 section 5 (previously RFC 2616 section 14),
	 * at <https://tools.ietf.org/html/rfc7231#section-5.3.5>.
	 *
	 * Earlier languages in the list are preferred as per the RFC 23282 extension to HTTP/1.1,
	 * at <https://tools.ietf.org/html/rfc3282>.
	 * @return-taint tainted
	 */
	public function getAcceptLang() {
		// Modified version of code found at
		// http://www.thefutureoftheweb.com/blog/use-accept-language-header
		$acceptLang = $this->getHeader( 'Accept-Language' );
		if ( !$acceptLang ) {
			return [];
		}

		// Return the language codes in lower case
		$acceptLang = strtolower( $acceptLang );

		// Break up string into pieces (languages and q factors)
		if ( !preg_match_all(
			'/
				# a language code or a star is required
				([a-z]{1,8}(?:-[a-z]{1,8})*|\*)
				# from here everything is optional
				\s*
				(?:
					# this accepts only numbers in the range ;q=0.000 to ;q=1.000
					;\s*q\s*=\s*
					(1(?:\.0{0,3})?|0(?:\.\d{0,3})?)?
				)?
			/x',
			$acceptLang,
			$matches,
			PREG_SET_ORDER
		) ) {
			return [];
		}

		// Create a list like "en" => 0.8
		$langs = [];
		foreach ( $matches as $match ) {
			$languageCode = $match[1];
			// When not present, the default value is 1
			$qValue = (float)( $match[2] ?? 1.0 );
			if ( $qValue ) {
				$langs[$languageCode] = $qValue;
			}
		}

		// Sort list by qValue
		arsort( $langs, SORT_NUMERIC );
		return $langs;
	}

	/**
	 * Fetch the raw IP from the request
	 *
	 * @since 1.19
	 * @return string|null
	 */
	protected function getRawIP() {
		$remoteAddr = $this->getServerInfo( 'REMOTE_ADDR' );
		if ( !$remoteAddr ) {
			return null;
		}
		if ( str_contains( $remoteAddr, ',' ) ) {
			throw new MWException( 'Remote IP must not contain multiple values' );
		}

		return IPUtils::canonicalize( $remoteAddr );
	}

	/**
	 * Work out the IP address based on various globals
	 * For trusted proxies, use the XFF client IP (first of the chain)
	 *
	 * @since 1.19
	 * @return string
	 */
	public function getIP(): string {
		global $wgUsePrivateIPs;

		# Return cached result
		if ( $this->ip !== null ) {
			return $this->ip;
		}

		# collect the originating IPs
		$ip = $this->getRawIP();
		if ( !$ip ) {
			throw new MWException( 'Unable to determine IP.' );
		}

		$services = MediaWikiServices::getInstance();
		# Append XFF
		$forwardedFor = $this->getHeader( 'X-Forwarded-For' );
		if ( $forwardedFor !== false ) {
			$proxyLookup = $services->getProxyLookup();
			$isConfigured = $proxyLookup->isConfiguredProxy( $ip );
			$ipchain = array_map( 'trim', explode( ',', $forwardedFor ) );
			$ipchain = array_reverse( $ipchain );
			array_unshift( $ipchain, $ip );

			# Step through XFF list and find the last address in the list which is a
			# trusted server. Set $ip to the IP address given by that trusted server,
			# unless the address is not sensible (e.g. private). However, prefer private
			# IP addresses over proxy servers controlled by this site (more sensible).
			# Note that some XFF values might be "unknown" with Squid/Varnish.
			foreach ( $ipchain as $i => $curIP ) {
				$curIP = IPUtils::sanitizeIP(
					IPUtils::canonicalize(
						self::canonicalizeIPv6LoopbackAddress( $curIP )
					)
				);
				if ( !$curIP || !isset( $ipchain[$i + 1] ) || $ipchain[$i + 1] === 'unknown'
					|| !$proxyLookup->isTrustedProxy( $curIP )
				) {
					break; // IP is not valid/trusted or does not point to anything
				}
				if (
					IPUtils::isPublic( $ipchain[$i + 1] ) ||
					$wgUsePrivateIPs ||
					// T50919; treat IP as valid
					$proxyLookup->isConfiguredProxy( $curIP )
				) {
					$nextIP = $ipchain[$i + 1];

					// Follow the next IP according to the proxy
					$nextIP = IPUtils::canonicalize(
						self::canonicalizeIPv6LoopbackAddress( $nextIP )
					);
					if ( !$nextIP && $isConfigured ) {
						// We have not yet made it past CDN/proxy servers of this site,
						// so either they are misconfigured or there is some IP spoofing.
						throw new MWException( "Invalid IP given in XFF '$forwardedFor'." );
					}
					$ip = $nextIP;

					// keep traversing the chain
					continue;
				}
				break;
			}
		}

		// Allow extensions to modify the result
		$hookContainer = $services->getHookContainer();
		// Optimisation: Hot code called on most requests (T85805).
		if ( $hookContainer->isRegistered( 'GetIP' ) ) {
			// @phan-suppress-next-line PhanTypeMismatchArgument Type mismatch on pass-by-ref args
			( new HookRunner( $hookContainer ) )->onGetIP( $ip );
		}

		if ( !$ip ) {
			throw new MWException( 'Unable to determine IP.' );
		}

		$this->ip = $ip;
		return $ip;
	}

	/**
	 * Converts ::1 (IPv6 loopback address) to 127.0.0.1 (IPv4 loopback address);
	 * assists in matching trusted proxies.
	 *
	 * @param string $ip
	 * @return string either '127.0.0.1' or $ip
	 * @since 1.36
	 */
	public static function canonicalizeIPv6LoopbackAddress( $ip ) {
		// Code moved from IPUtils library. See T248237#6614927
		if ( preg_match( '/^0*' . IPUtils::RE_IPV6_GAP . '1$/', $ip ) ) {
			return '127.0.0.1';
		}
		return $ip;
	}

	/**
	 * @param string $ip
	 * @return void
	 * @since 1.21
	 */
	public function setIP( $ip ) {
		$this->ip = $ip;
	}

	/**
	 * Check if this request uses a "safe" HTTP method
	 *
	 * Safe methods are verbs (e.g. GET/HEAD/OPTIONS) used for obtaining content. Such requests
	 * are not expected to mutate content, especially in ways attributable to the client. Verbs
	 * like POST and PUT are typical of non-safe requests which often change content.
	 *
	 * @return bool
	 * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
	 * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
	 * @since 1.28
	 */
	public function hasSafeMethod() {
		if ( $this->getServerInfo( 'REQUEST_METHOD' ) === null ) {
			return false; // CLI mode
		}

		return in_array( $this->getServerInfo( 'REQUEST_METHOD' ), [ 'GET', 'HEAD', 'OPTIONS', 'TRACE' ] );
	}

	/**
	 * Whether this request should be identified as being "safe"
	 *
	 * This means that the client is not requesting any state changes and that database writes
	 * are not inherently required. Ideally, no visible updates would happen at all. If they
	 * must, then they should not be publicly attributed to the end user.
	 *
	 * In more detail:
	 *   - Cache populations and refreshes MAY occur.
	 *   - Private user session updates and private server logging MAY occur.
	 *   - Updates to private viewing activity data MAY occur via DeferredUpdates.
	 *   - Other updates SHOULD NOT occur (e.g. modifying content assets).
	 *
	 * @deprecated since 1.41, use hasSafeMethod() instead.
	 *
	 * @return bool
	 * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
	 * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
	 * @since 1.28
	 */
	public function isSafeRequest() {
		wfDeprecated( __METHOD__, '1.41' );
		if ( $this->markedAsSafe && $this->wasPosted() ) {
			return true; // marked as a "safe" POST
		}

		return $this->hasSafeMethod();
	}

	/**
	 * Mark this request as identified as being nullipotent even if it is a POST request
	 *
	 * POST requests are often used due to the need for a client payload, even if the request
	 * is otherwise equivalent to a "safe method" request.
	 *
	 * @deprecated since 1.41
	 *
	 * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
	 * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
	 * @since 1.28
	 */
	public function markAsSafeRequest() {
		wfDeprecated( __METHOD__, '1.41' );
		$this->markedAsSafe = true;
	}

	/**
	 * Determine if the request URL matches one of a given set of canonical CDN URLs.
	 *
	 * MediaWiki uses this to determine whether to set a long 'Cache-Control: s-maxage='
	 * header on the response. {@see MainConfigNames::CdnMatchParameterOrder} controls whether
	 * the matching is sensitive to the order of query parameters.
	 *
	 * @param string[] $cdnUrls URLs to match against
	 * @return bool
	 * @since 1.39
	 */
	public function matchURLForCDN( array $cdnUrls ) {
		$services = MediaWikiServices::getInstance();
		$reqUrl = (string)$services->getUrlUtils()->expand( $this->getRequestURL(), PROTO_INTERNAL );
		$config = $services->getMainConfig();
		if ( $config->get( MainConfigNames::CdnMatchParameterOrder ) ) {
			// Strict matching
			return in_array( $reqUrl, $cdnUrls, true );
		}

		// Loose matching (order of query parameters is ignored)
		$reqUrlParts = explode( '?', $reqUrl, 2 );
		$reqUrlBase = $reqUrlParts[0];
		$reqUrlParams = count( $reqUrlParts ) === 2 ? explode( '&', $reqUrlParts[1] ) : [];
		// The order of parameters after the sort() call below does not match
		// the order set by the CDN, and does not need to. The CDN needs to
		// take special care to preserve the relative order of duplicate keys
		// and array-like parameters.
		sort( $reqUrlParams );
		foreach ( $cdnUrls as $cdnUrl ) {
			if ( strlen( $reqUrl ) !== strlen( $cdnUrl ) ) {
				continue;
			}
			$cdnUrlParts = explode( '?', $cdnUrl, 2 );
			$cdnUrlBase = $cdnUrlParts[0];
			if ( $reqUrlBase !== $cdnUrlBase ) {
				continue;
			}
			$cdnUrlParams = count( $cdnUrlParts ) === 2 ? explode( '&', $cdnUrlParts[1] ) : [];
			sort( $cdnUrlParams );
			if ( $reqUrlParams === $cdnUrlParams ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns an array suitable for addition to a PSR-3 log context that will contain information
	 * about the request that is useful when investigating security or abuse issues (IP, user agent
	 * etc).
	 *
	 * @param ?UserIdentity $user The user whose action is being logged. Optional; passing it will
	 *   result in more context information. The user is not required to exist locally. It must
	 *   have a name though (ie. it should not have the IP address as its name; temp users are
	 *   allowed).
	 * @return array By default it will have the following keys:
	 *   - clientIp: the IP address (as in WebRequest::getIP())
	 *   - ua: the User-Agent header (or the Api-User-Agent header when using the action API)
	 *   - originalUserAgent: the User-Agent header (only when Api-User-Agent is also present)
	 *   Furthermore, when $user has been provided:
	 *   - user: the username
	 *   - user_exists_locally: whether the user account exists on the current wiki
	 *   More fields can be added via the GetSecurityLogContext hook.
	 *
	 * @since 1.45
	 * @see GetSecurityLogContextHook
	 */
	public function getSecurityLogContext( ?UserIdentity $user = null ): array {
		$this->securityLogContext ??= new HashBagOStuff( [ 'maxKeys' => 5 ] );
		$cacheKey = $user
			? $this->securityLogContext->makeKey( 'user', $user->getName() )
			: $this->securityLogContext->makeKey( 'anon', '-' );
		if ( $this->securityLogContext->hasKey( $cacheKey ) ) {
			return $this->securityLogContext->get( $cacheKey );
		}

		$context = [
			'clientIp' => $this->getIP(),
		];

		$userAgent = $this->getHeader( 'User-Agent' );
		$apiUserAgent = null;
		if ( defined( 'MW_API' ) ) {
			// matches ApiMain::getUserAgent()
			$apiUserAgent = $this->getHeader( 'Api-User-Agent' );
		}
		$context['ua'] = $apiUserAgent ?: $userAgent;
		if ( $apiUserAgent ) {
			$context['originalUserAgent'] = $userAgent;
		}

		if ( $user ) {
			$context += [
				'user' => $user->getName(),
				'user_exists_locally' => $user->isRegistered(),
			];
		}

		$info = [ 'request' => $this, 'user' => $user ];
		$hookRunner = new HookRunner( MediaWikiServices::getInstance()->getHookContainer() );
		$hookRunner->onGetSecurityLogContext( $info, $context );

		$this->securityLogContext->set( $cacheKey, $context );
		return $context;
	}
}

/** @deprecated class alias since 1.41 */
class_alias( WebRequest::class, 'WebRequest' );
