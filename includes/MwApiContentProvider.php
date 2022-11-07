<?php

namespace MobileFrontendContentProviders;

use FormatJson;
use Html;
use MediaWiki\MediaWikiServices;
use MobileFrontend\ContentProviders\IContentProvider;
use OutputPage;

class MwApiContentProvider implements IContentProvider {
	/**
	 * @var string
	 */
	private $baseUrl;

	/**
	 * @var OutputPage
	 */
	private $out;

	/**
	 * @var string
	 */
	private $skinName;

	/**
	 * @var int|null revision (optional) of the page to be provided by the provider.
	 */
	private $revId;

	/**
	 * @var bool
	 */
	private $provideTagline;

	/**
	 * @param string $baseUrl for the MediaWiki API to be used minus query string e.g. /w/api.php
	 * @param OutputPage $out so that the ResourceLoader modules specific to the page can be added
	 * @param string $skinName the skin name the content is being provided for
	 * @param int|null $revId optional
	 * @param bool $provideTagline optional
	 */
	public function __construct( $baseUrl, OutputPage $out, $skinName, $revId = null,
		$provideTagline = false
	) {
		$this->baseUrl = $baseUrl;
		$this->out = $out;
		$this->skinName = $skinName;
		$this->revId = $revId;
		$this->provideTagline = $provideTagline;
	}

	/**
	 * @param string $url URL to fetch the content
	 * @return string
	 */
	protected function fileGetContents( $url ) {
		$response = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $url, [], __METHOD__ );

		$status = $response->execute();
		if ( !$status->isOK() ) {
			$msg = 'ContentProvider failed to load page ' . $url . ' with following error: '
				. implode(
					', ',
					array_map( static function ( $message ) {
						$params = implode( ' ', $message['params'] );
						return $message['message'] . ' (' . $params . ')';
					}, $status->getErrors() )
				);
			return json_encode( [
				'parse' => [
					'text' => Html::errorBox( $msg ),
					'modules' => [],
					'pageid' => -1,
					'revid' => -1,
					'modulestyles' => [],
					'properties' => [],
				]
			] );
		}

		return $response->getContent();
	}

	/**
	 * @inheritDoc
	 */
	public function getHTML() {
		$out = $this->out;
		$query = 'action=parse&prop=revid|text|modules|sections|properties|langlinks';
		$url = $this->baseUrl . '?formatversion=2&format=json&' . $query;
		if ( $this->revId ) {
			$url .= '&oldid=' . rawurlencode( (string)$this->revId );
		} else {
			$title = $out->getTitle();
			if ( !$title ) {
				return '';
			}
			$url .= '&page=' . rawurlencode( $title->getPrefixedDBkey() );
		}
		// The skin must exist on the target wiki and not be hidden for this to work.
		if ( in_array( $this->skinName, [ 'vector', 'minerva', 'monobook', 'timeless', 'modern', 'vector-2022' ] ) ) {
			// `useskin` - informs MobileFrontend and various other things of context to run in
			// `skin` - informs API of what content to generate.
			$url .= '&useskin=' . $this->skinName . '&skin=' . $this->skinName;
		} else {
			$url .= '&skin=apioutput';
		}

		$resp = $this->fileGetContents( $url );
		$json = FormatJson::decode( $resp, true );
		// As $this->fileGetContents() may return '' in some cases, doing;
		// FormatJson::decode( '', true ); will return "null" so check it.
		if ( is_array( $json ) && array_key_exists( 'parse', $json ) ) {
			$parse = $json['parse'];

			$out->addModules( $parse['modules'] );
			$styles = array_filter( $parse[ 'modulestyles' ], static function ( $module ) {
				return strpos( $module, 'skins.' ) === false;
			} );
			$out->addModuleStyles( $styles );
			$parserProps = $parse['properties'];
			if ( $this->provideTagline && isset( $parserProps['wikibase-shortdesc'] ) ) {
				// special handling for wikidata descriptions (T212216)
				// Note, due to varnish cache, ContentProviders run on OutputPage, but
				// currently ParserOutput is used for Wikidata descriptions which happens before this
				$out->setProperty( 'wgMFDescription', $parserProps['wikibase-shortdesc'] );
			}
			$ignoreKeys = [ 'noexternallanglinks' ];
			// Copy page properties across excluding a few we know not to work due to php serialisation)
			foreach ( array_diff( array_keys( $parserProps ), $ignoreKeys ) as $key ) {
				$out->setProperty( $key,  $parserProps[ $key ] );
			}
			// Forward certain variables so that the page is not registered as "missing"
			$out->addJsConfigVars( [
				'wgArticleId' => $parse['pageid'],
				'wgRevisionId' => $parse['revid'],
			] );
			if ( array_key_exists( 'langlinks', $parse ) ) {
				$langlinks = [];
				foreach ( $parse['langlinks'] as $lang ) {
					$langlinks[] = $lang['lang'] . ':' . $lang['title'];
				}
				$out->setLanguageLinks( $langlinks );
			}
			$sections = $parse['sections'] ?? null;
			if ( $sections ) {
				$out->setSections( $sections );
			}
			return $parse['text'];
		}

		return '';
	}
}
