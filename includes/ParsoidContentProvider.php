<?php

namespace MobileFrontendContentProviders;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MobileFrontend\ContentProviders\IContentProvider;
use OutputPage;

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

	/**
	 * @param string $baseUrl for the MediaWiki API to be used minus query string e.g. /w/api.php
	 * @param OutputPage $out
	 */
	public function __construct( $baseUrl, OutputPage $out ) {
		$this->baseUrl = $baseUrl;
		$this->out = $out;
		$this->title = $out->getTitle();
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
		// Parsoid renders HTML incompatible with PHP parser and needs its own styles
		$this->out->addModuleStyles( 'mediawiki.skinning.content.parsoid' );

		$url = $this->baseUrl . '/page/html/';
		$url .= urlencode( $title->getPrefixedDBkey() );

		$resp = $this->fileGetContents( $url );
		if ( $resp ) {
			return $resp;
		} else {
			return '';
		}
	}
}
