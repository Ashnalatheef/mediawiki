LinkCache {#linkcache}
========

The LinkCache class maintains a list of article titles and the information about
whether or not the article exists in the database. This is used to mark up links
when displaying a page. If the same link appears more than once on any page,
then it only has to be looked up once. In most cases, link lookups are done in
batches with the LinkBatch class, or the equivalent in Parser::replaceLinkHolders(),
so the link cache is mostly useful for short snippets of parsed text (such as
the site notice), and for links in the navigation areas of the skin.

The link cache was formerly used to track links used in a document for the
purposes of updating the link tables. This application is now deprecated.

To create a batch, you can use the following code:

```php
$pages = [ 'Main Page', 'Project:Help', /* ... */ ];
$titles = [];

foreach( $pages as $page ){
	$titles[] = Title::newFromText( $page );
}

$linkBatchFactory = MediaWikiServices::getInstance()->getLinkBatchFactory();
$batch = $linkBatchFactory->newLinkBatch( $titles );
$batch->execute();
```
