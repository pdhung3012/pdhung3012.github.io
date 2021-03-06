<?php
/**
 * Expose reference information for a page via prop=references API.
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
 * @see https://www.mediawiki.org/wiki/Extension:Cite#API
 */

class ApiQueryReferences extends ApiQueryBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'rf' );
	}

	public function getAllowedParams() {
		return [
		   'continue' => [
			   ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
		   ],
		];
	}

	public function execute() {
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'cite' );
		if ( !$config->get( 'CiteStoreReferencesData' ) ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'apierror-citestoragedisabled' );
			} else {
				$this->dieUsage( 'Cite extension reference storage is not enabled', 'citestoragedisabled' );
			}
		}
		$params = $this->extractRequestParams();
		$titles = $this->getPageSet()->getGoodTitles();
		ksort( $titles );
		if ( !is_null( $params['continue'] ) ) {
			$startId = (int)$params['continue'];
			// check it is definitely an int
			$this->dieContinueUsageIf( strval( $startId ) !== $params['continue'] );
		} else {
			$startId = false;
		}

		foreach ( $titles as $pageId => $title ) {
			// Skip until you have the correct starting point
			if ( $startId !== false && $startId !== $pageId ) {
				continue;
			} else {
				$startId = false;
			}
			$storedRefs = Cite::getStoredReferences( $title );
			$allReferences = [];
			// some pages may not have references stored
			if ( $storedRefs !== false ) {
				// a page can have multiple <references> tags but they all have unique keys
				foreach ( $storedRefs['refs'] as $index => $grouping ) {
					foreach ( $grouping as $group => $members ) {
						foreach ( $members as $name => $ref ) {
							$ref['name'] = $name;
							$key = $ref['key'];
							if ( is_string( $name ) ) {
								$id = Cite::getReferencesKey( $name . '-' . $key );
							} else {
								$id = Cite::getReferencesKey( $key );
							}
							$ref['group'] = $group;
							$ref['reflist'] = $index;
							$allReferences[$id] = $ref;
						}
					}
				}
			}
			// set some metadata since its an assoc data structure
			ApiResult::setArrayType( $allReferences, 'kvp', 'id' );
			// Ship a data representation of the combined references.
			$fit = $this->addPageSubItems( $pageId, $allReferences );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
				break;
			}
		}
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&prop=references&titles=Albert%20Einstein' =>
				'apihelp-query+references-example-1',
		];
	}

}
