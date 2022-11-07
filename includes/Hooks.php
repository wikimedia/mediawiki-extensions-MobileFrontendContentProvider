<?php
namespace MobileFrontendContentProviders;

use Action;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutputFlags;
use MobileFrontend\ContentProviders\IContentProvider;
use OutputPage;
use ParserOutput;

class Hooks {
	/**
	 * OutputPageBeforeHTML hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageBeforeHTML
	 *
	 * Applies MobileFormatter to mobile viewed content
	 * Also enables Related Articles in the footer in the beta mode.
	 * Adds inline script to allow opening of sections while JS is still loading
	 *
	 * @param OutputPage $out the OutputPage object to which wikitext is added
	 */
	public static function onOutputPageBeforeHTML( OutputPage $out ) {
		$services = MediaWikiServices::getInstance();
		$config = $services->getService( 'MobileFrontendContentProvider.Config' );
		$contentProviderApi = $config->get( 'MFContentProviderScriptPath' );
		if ( $contentProviderApi ) {
			$out->addModules( 'mobile.contentProviderApi' );
		}
	}

	/**
	 * OutputPageParserOutput hook handler
	 * @see https://www.mediawiki.org/wiki/Extension:MobileFrontend/onOutputPageParserOutput
	 *
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 */
	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ) {
		if ( !self::shouldApplyContentProvider( $out ) ) {
			return;
		}
		if ( class_exists( 'MediaWiki\Parser\ParserOutputFlags' ) ) {
			$parserOutput->setOutputFlag( ParserOutputFlags::SHOW_TOC );
		} else {
			// For MediaWiki < 1.39
			$parserOutput->setTOCHTML( '<!-- >' );
		}
	}

	/**
	 * @param OutputPage $out
	 * @return bool
	 */
	private static function shouldApplyContentProvider( $out ) {
		$services = MediaWikiServices::getInstance();
		$config = $services->getService( 'MobileFrontendContentProvider.Config' );
		$title = $out->getTitle();
		if ( !$config->get( 'MFContentProviderEnabled' ) ) {
			return false;
		}

		// User has asked to honor local content so exit.
		if ( $config->get( 'MFContentProviderTryLocalContentFirst' ) && $title->exists() ) {
			return false;
		}

		if ( Action::getActionName( $out->getContext() ) !== 'view' ) {
			return false;
		}
		return true;
	}

	/**
	 * MobileFrontendContentProvider hook handler
	 * @see https://www.mediawiki.org/wiki/Extension:MobileFrontend/onMobileFrontendContentProvider
	 *
	 * Applies MobileFormatter to mobile viewed content
	 * Also enables Related Articles in the footer in the beta mode.
	 * Adds inline script to allow opening of sections while JS is still loading
	 *
	 * @param IContentProvider &$provider
	 * @param OutputPage $out the OutputPage object to which wikitext is added
	 */
	public static function onMobileFrontendContentProvider(
		IContentProvider &$provider, OutputPage $out
	) {
		if ( !self::shouldApplyContentProvider( $out ) ) {
			return;
		}
		$services = MediaWikiServices::getInstance();
		/** @var ContentProviderFactory $contentProviderFactory */
		$contentProviderFactory = $services->getService( 'MobileFrontendContentProvider.Factory' );
		$provider = $contentProviderFactory->getProvider( $out, true );
	}
}
