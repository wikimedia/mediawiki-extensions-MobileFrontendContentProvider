<?php

namespace MobileFrontendContentProviders;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MobileFrontend\ContentProviders\IContentProvider;
use OutputPage;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Wt2Html\XHtmlSerializer;

/**
 * Sources content from the Mobile-Content-Service
 * This requires allow_url_fopen to be set.
 */
class ParsoidContentProvider implements IContentProvider {
	/** @var Title|null */
	private $title;
	/** @var OutputPage */
	private $out;
	/** @var string */
	private $baseUrl;
	/** @var string */
	private $skinName;
	/** @var bool */
	private $isMobile;

	/**
	 * @param string $baseUrl for the MediaWiki API to be used minus query string e.g. /w/api.php
	 * @param OutputPage $out
	 * @param bool|null $isMobile Whether to request mobile-formatted content (for testing or override purposes)
	 */
	public function __construct( $baseUrl, OutputPage $out, $isMobile = null ) {
		$this->baseUrl = $baseUrl;
		$this->out = $out;
		$this->title = $out->getTitle();
		$this->skinName = $out->getSkin()->getSkinName();
		if ( $isMobile !== null ) {
			$this->isMobile = $isMobile;
		} elseif ( ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) ) {
			$services = MediaWikiServices::getInstance();
			$this->isMobile = $services->getService( 'MobileFrontend.Context' )->shouldDisplayMobileView();
		} else {
			$this->isMobile = false;
		}
	}

	/**
	 * Adds configuration variables and modules to the page.
	 *
	 * @param OutputPage $out
	 */
	private function addModulesAndConfigToPage( OutputPage $out ) {
		$title = $out->getTitle();
		$url = $this->baseUrl . '/w/api.php';
		$url .= '?action=parse&format=json&prop=modules%7Cjsconfigvars%7Csections&parser=parsoid';
		$url .= '&useskin=' . $this->skinName . '&formatversion=2&page=';
		$url .= urlencode( $title->getPrefixedDBkey() );
		$response = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $url, [], __METHOD__ );

		$status = $response->execute();
		if ( !$status->isOK() ) {
			return;
		}

		$resp = json_decode( $response->getContent(), true );
		$parse = $resp['parse'] ?? [];
		$out->addModules( $parse[ 'modules' ] ?? [] );
		$out->addJsConfigVars( $parse['jsconfigvars'] ?? [] );
		$sections = $parse['sections'] ?? null;
		if ( $sections ) {
			$tocPout = new ParserOutput;
			$tocPout->setSections( $sections );
			$tocPout->setOutputFlag( ParserOutputFlags::SHOW_TOC, $parse['showtoc'] ?? true );
			$out->addParserOutputMetadata( $tocPout );
		}
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
			return '';
		}

		return $response->getContent();
	}

	/**
	 * @inheritDoc
	 */
	public function getHTML() {
		$title = $this->title;
		if ( !$title ) {
			return '';
		}
		$out = $this->out;
		// Parsoid renders HTML incompatible with PHP parser and needs its own styles
		$out->addModuleStyles( 'mediawiki.skinning.content.parsoid' );

		$url = $this->baseUrl . '/wiki/';
		$url .= urlencode( $title->getPrefixedDBkey() );
		$url .= '?useparsoid=1&useskin=' . $this->skinName;
		if ( $this->isMobile ) {
			$url .= '&useformat=mobile';
		}

		$resp = $this->fileGetContents( $url );
		if ( $resp ) {
			$html = $resp;
		} else {
			$html = '';
		}
		$doc = DOMUtils::parseHTML( $html );
		$body = DOMCompat::getBody( $doc );
		// check for data-mw-parsoid-version to make sure we are looking at the main article
		$pOut = DOMCompat::querySelector( $body, '.mw-parser-output[data-mw-parsoid-version]' );
		if ( !$pOut ) {
			return '';
		}
		$container = $pOut->parentNode;

		foreach ( $container->childNodes as $childNode ) {
			if (
				get_class( $childNode ) !== 'Wikimedia\Parsoid\DOM\Text' &&
				strpos( $childNode->getAttribute( 'class' ), 'mw-parser-output' ) === false
			) {
				$childNode->parentNode->removeChild( $childNode );
			}
		}

		$this->addModulesAndConfigToPage( $out );
		return $container ? XHtmlSerializer::serialize(
			$container, [ 'innerXML' => true, 'smartQuote' => false ]
		)['html'] : '';
	}
}
