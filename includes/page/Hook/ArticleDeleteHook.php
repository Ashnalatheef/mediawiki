<?php

namespace MediaWiki\Page\Hook;

use MediaWiki\Page\WikiPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "ArticleDelete" to register handlers implementing this interface.
 *
 * @ingroup Hooks
 * @deprecated since 1.37, use PageDeleteHook instead. The new hook uses more modern typehints and requires callers
 * to add errors to $status, rather than the $error parameter.
 */
interface ArticleDeleteHook {
	/**
	 * This hook is called before an article is deleted.
	 *
	 * @since 1.35
	 *
	 * @param WikiPage $wikiPage WikiPage being deleted
	 * @param User $user User deleting the article
	 * @param string &$reason Reason the article is being deleted
	 * @param string &$error If the deletion was prohibited, the (raw HTML) error message to display
	 *   (added in 1.13)
	 * @param Status &$status Modify this to throw an error. Overridden by $error
	 *   (added in 1.20)
	 * @param bool $suppress Whether this is a suppression deletion or not (added in 1.27)
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onArticleDelete( WikiPage $wikiPage, User $user, &$reason, &$error, Status &$status,
		$suppress
	);
}
