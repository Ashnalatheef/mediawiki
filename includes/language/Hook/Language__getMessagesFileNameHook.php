<?php

namespace MediaWiki\Language\Hook;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "Language::getMessagesFileName" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface Language__getMessagesFileNameHook {
	/**
	 * Use this hook to change the path of a localisation file.
	 *
	 * @since 1.35
	 *
	 * @param string $code Language code or the language we're looking for a messages file for
	 * @param string &$file Messages file path. You can override this to change the location.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onLanguage__getMessagesFileName( $code, &$file );
}

/** @deprecated class alias since 1.45 */
class_alias( Language__getMessagesFileNameHook::class,
	'MediaWiki\\Languages\\Hook\\Language__getMessagesFileNameHook' );
