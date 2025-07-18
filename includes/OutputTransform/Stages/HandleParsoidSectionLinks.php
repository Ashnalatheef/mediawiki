<?php
declare( strict_types = 1 );

namespace MediaWiki\OutputTransform\Stages;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\OutputTransform\ContentDOMTransformStage;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;

/**
 * Add anchors and other heading formatting, and replace the section link placeholders.
 * @internal
 */
class HandleParsoidSectionLinks extends ContentDOMTransformStage {

	private TitleFactory $titleFactory;

	public function __construct(
		ServiceOptions $options, LoggerInterface $logger, TitleFactory $titleFactory
	) {
		parent::__construct( $options, $logger );
		$this->titleFactory = $titleFactory;
	}

	public function shouldRun( ParserOutput $po, ?ParserOptions $popts, array $options = [] ): bool {
		// Only run this stage if it is parsoid content
		return ( $options['isParsoidContent'] ?? false );
	}

	/**
	 * Check if the heading has attributes that can only be added using HTML syntax.
	 *
	 * In the Parsoid default future, we might prefer checking for stx=html.
	 */
	private function isHtmlHeading( Element $h ): bool {
		foreach ( $h->attributes as $attr ) {
			// Condition matches DiscussionTool's CommentFormatter::handleHeading
			if (
				!in_array( $attr->name, [ 'id', 'data-object-id', 'about', 'typeof' ], true ) &&
				!Sanitizer::isReservedDataAttribute( $attr->name )
			) {
				return true;
			}
		}
		// FIXME(T100856): stx info probably shouldn't be in data-parsoid
		// Id is ignored above since it's a special case, make use of metadata
		// to determine if it came from wikitext
		if ( DOMDataUtils::getDataParsoid( $h )->reusedId ?? false ) {
			return true;
		}
		return false;
	}

	public function transformDOM(
		Document $dom, ParserOutput $po, ?ParserOptions $popts, array &$options
	): Document {
		$skin = $this->resolveSkin( $options );
		$titleText = $po->getTitleText();
		// Transform:
		//  <section data-mw-section-id=...>
		//   <h2 id=...><span id=... typeof="mw:FallbackId"></span> ... </h2>
		//   ...section contents..
		// To:
		//  <section data-mw-section-id=...>
		//   <div class="mw-heading mw-heading2">
		//    <h2 id=...><span id=... typeof="mw:FallbackId"></span> ... </h2>
		//    <span class="mw-editsection">...section edit link...</span>
		//   </div>
		// That is, we're wrapping a <div> around the <h2> generated by
		// Parsoid, and then (assuming section edit links are enabled)
		// adding a <span> with the section edit link
		// inside that <div>
		//
		// If COLLAPSIBLE_SECTIONS is set, then we also wrap a <div>
		// around the section *contents*.
		$toc = $po->getTOCData();
		$sections = ( $toc !== null ) ? $toc->getSections() : [];
		// use the TOC data to extract the headings:
		foreach ( $sections as $section ) {
			if ( $section->anchor === '' ) {
				// T375002 / T368722: The empty string isn't a valid id so
				// Parsoid will have reassigned it and we'll never be able
				// to select by it below.  There's no sense in logging an
				// error since it's a common enough occurrence at present.
				continue;
			}
			$h = $dom->getElementById( $section->anchor );
			if ( $h === null ) {
				$this->logger->error(
					__METHOD__ . ': Heading missing for anchor',
					$section->toLegacy()
				);
				continue;
			}

			if ( $this->isHtmlHeading( $h ) ) {
				// This is a <h#> tag with attributes added using HTML syntax.
				// Mark it with a class to make them easier to distinguish (T68637).
				DOMCompat::getClassList( $h )->add( 'mw-html-heading' );

				// Do not add the wrapper if the heading has attributes added using HTML syntax (T353489).
				continue;
			}

			$fromTitle = $section->fromTitle;
			$div = $dom->createElement( 'div' );
			if (
				$fromTitle !== null &&
				( $options['enableSectionEditLinks'] ?? true ) &&
				!$po->getOutputFlag( ParserOutputFlags::NO_SECTION_EDIT_LINKS )
			) {
				$editPage = $this->titleFactory->newFromTextThrow( $fromTitle );
				$html = $skin->doEditSectionLink(
					$editPage, $section->index, $h->textContent,
					$skin->getLanguage()
				);
				DOMCompat::setInnerHTML( $div, $html );
			}

			// Reuse existing wrapper if present.
			$maybeWrapper = $h->parentNode;
			'@phan-var \Wikimedia\Parsoid\DOM\Element $maybeWrapper';
			if (
				DOMCompat::nodeName( $maybeWrapper ) === 'div' &&
				DOMCompat::getClassList( $maybeWrapper )->contains( 'mw-heading' )
			) {
				// Transfer section edit link children to existing wrapper
				// All contents of the div (the section edit link) will be
				// inserted immediately following the <h> tag
				$ref = $h->nextSibling;
				while ( $div->firstChild !== null ) {
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal firstChild is non-null (PHP81)
					$maybeWrapper->insertBefore( $div->firstChild, $ref );
				}
				$div = $maybeWrapper; // for use below
			} else {
				// Move <hX> to new wrapper: the div contents are currently
				// the section edit link. We first replace the h with the
				// div, then insert the <h> as the first child of the div
				// so the section edit link is immediately following the <h>.
				$div->setAttribute(
					'class', 'mw-heading mw-heading' . $section->hLevel
				);
				$h->parentNode->replaceChild( $div, $h );
				// Work around bug in phan (https://github.com/phan/phan/pull/4837)
				// by asserting that $div->firstChild is non-null here.  Actually,
				// ::insertBefore will work fine if $div->firstChild is null (if
				// "doEditSectionLink" returned nothing, for instance), but
				// phan incorrectly thinks the second argument must be non-null.
				$divFirstChild = $div->firstChild;
				'@phan-var \DOMNode $divFirstChild'; // asserting non-null (PHP81)
				$div->insertBefore( $h, $divFirstChild );
			}
			// Create collapsible section wrapper if requested.
			if ( $po->getOutputFlag( ParserOutputFlags::COLLAPSIBLE_SECTIONS ) ) {
				$contentsDiv = $dom->createElement( 'div' );
				while ( $div->nextSibling !== null ) {
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal
					$contentsDiv->appendChild( $div->nextSibling );
				}
				$div->parentNode->appendChild( $contentsDiv );
			}
		}
		return $dom;
	}

	/**
	 * Extracts the skin from the $options array, with a fallback on request context skin
	 * @param array $options
	 * @return Skin
	 */
	private function resolveSkin( array $options ): Skin {
		$skin = $options[ 'skin' ] ?? null;
		if ( !$skin ) {
			// T348853 passing $skin will be mandatory in the future
			$skin = RequestContext::getMain()->getSkin();
		}
		return $skin;
	}
}
