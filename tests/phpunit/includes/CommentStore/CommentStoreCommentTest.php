<?php

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\CommentStore\CommentStoreComment
 *
 * @license GPL-2.0-or-later
 */
class CommentStoreCommentTest extends TestCase {
	use MediaWikiCoversValidator;

	public function testConstructorWithMessage() {
		$message = new Message( 'test' );
		$comment = new CommentStoreComment( null, 'test', $message );

		$this->assertSame( $message, $comment->message );
	}

	public function testConstructorWithoutMessage() {
		$text = '{{template|param}}';
		$comment = new CommentStoreComment( null, $text );

		$this->assertSame( $text, $comment->message->text() );
	}

}
