-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: tests/phpunit/data/schema-maintenance/patch-drop-ct_tag.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP INDEX change_tag_rc_tag_nonuniq;

DROP INDEX change_tag_log_tag_nonuniq;

DROP INDEX change_tag_rev_tag_nonuniq;

DROP INDEX change_tag_tag_id;

ALTER TABLE change_tag
  DROP ct_tag;
