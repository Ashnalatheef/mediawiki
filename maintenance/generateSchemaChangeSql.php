<?php

/**
 * Convert a JSON abstract schema change to a schema change file in the given DBMS type
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
 * @ingroup Maintenance
 */

use MediaWiki\Maintenance\SchemaGenerator;
use MediaWiki\Maintenance\SchemaMaintenance;

// @codeCoverageIgnoreStart
require_once __DIR__ . '/includes/SchemaMaintenance.php';
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to generate schema from abstract json files.
 *
 * @ingroup Maintenance
 */
class GenerateSchemaChangeSql extends SchemaMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Build SQL files for schema changes from abstract JSON files' );
	}

	protected function generateSchema( string $platform, string $jsonPath ): string {
		return ( new SchemaGenerator() )->generateSchemaChange( $platform, $jsonPath );
	}

}

// @codeCoverageIgnoreStart
$maintClass = GenerateSchemaChangeSql::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
