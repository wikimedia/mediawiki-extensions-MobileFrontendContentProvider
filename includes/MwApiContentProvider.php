<?php

namespace MobileFrontendContentProviders;

use FormatJson;
use MediaWiki\Html\Html;
use MediaWiki\Html\HtmlHelper;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutputFlags;
use MobileFrontend\ContentProviders\IContentProvider;
use OutputPage;
use ParserOutput;
use Wikimedia\RemexHtml\Serializer\SerializerNode;

class MwApiContentProvider implements IContentProvider {
	/**
	 * @var string
	 */
	private $baseUrl;

	/**
	 * @var string|null
	 */
	private $articlePath;

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
	 * @param string|null $articlePath target article path to change links to (null for no change)
	 * @param OutputPage $out so that the ResourceLoader modules specific to the page can be added
	 * @param string $skinName the skin name the content is being provided for
	 * @param int|null $revId optional
	 * @param bool $provideTagline optional
	 */
	public function __construct( $baseUrl, ?string $articlePath, OutputPage $out, $skinName, $revId = null,
		$provideTagline = false
	) {
		$this->baseUrl = $baseUrl;
		$this->articlePath = $articlePath;
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
		$query = '?formatversion=2&format=json&' . $query;
		$baseUrl = $this->baseUrl;

		if ( $this->revId ) {
			$query .= '&oldid=' . rawurlencode( (string)$this->revId );
		} else {
			$title = $out->getTitle();
			if ( !$title ) {
				return '';
			}
			$dbKey = $title->getPrefixedDBkey();
			$prefixedTitle = rawurlencode( $dbKey );
			$parts = explode( ':', $dbKey );
			if ( count( $parts ) === 2 ) {
				// change URL
				$lang = strtolower( $parts[0] );
				$baseUrl = preg_replace( '/https:\/\/en\./', 'https://' . $lang . '.', $baseUrl );

			}
			$query .= '&page=' . $prefixedTitle;
		}
		// The skin must exist on the target wiki and not be hidden for this to work.
		if ( in_array( $this->skinName, [ 'vector', 'minerva', 'monobook', 'timeless', 'modern', 'vector-2022' ] ) ) {
			// `useskin` - informs MobileFrontend and various other things of context to run in
			// `skin` - informs API of what content to generate.
			$query .= '&useskin=' . $this->skinName . '&skin=' . $this->skinName;
		} else {
			$query .= '&skin=apioutput';
		}

		$resp = $this->fileGetContents( $baseUrl . $query );
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
				$out->setProperty( $key, $parserProps[ $key ] );
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
				$tocPout = new ParserOutput;
				$tocPout->setSections( $sections );
				$tocPout->setOutputFlag( ParserOutputFlags::SHOW_TOC, $parse['showtoc'] ?? true );
				$out->addParserOutputMetadata( $tocPout );
			}

			$text = $parse['text'];

			if ( $this->articlePath ) {
				// Fix the article path of href's if required
				$resp = $this->fileGetContents(
					$this->baseUrl . '?action=query&format=json&meta=siteinfo&formatversion=2'
				);
				$json = FormatJson::decode( $resp, true );
				if ( is_array( $json ) && array_key_exists( 'query', $json ) ) {
					$remoteArticlePath = $json['query']['general']['articlepath'];
					if ( $remoteArticlePath !== $this->articlePath ) {
						$pattern = '/' . str_replace(
							preg_quote( '$1', '/' ),
							'([^?]*)',
							preg_quote( $remoteArticlePath, '/' )
						) . '/';
						$articlePath = $this->articlePath;
						$text = HtmlHelper::modifyElements(
							$text,
							static function ( SerializerNode $node ): bool {
								return $node->name === 'a' && isset( $node->attrs['href'] );
							},
							static function ( SerializerNode $node ) use ( $pattern, $articlePath ): SerializerNode {
								$node->attrs['href'] = preg_replace(
									$pattern,
									$articlePath,
									$node->attrs['href']
								);
								return $node;
							}
						);
					}
				}
			}

			return $text;
		}

		return '';
	}
}
