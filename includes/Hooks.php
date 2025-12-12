<?php
namespace MobileFrontendContentProviders;

use Action;
use MediaWiki\Api\ApiParse;
use MediaWiki\MediaWikiServices;
use MobileFrontend\ContentProviders\IContentProvider;
use OutputPage;

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

	public static function onApiParseMakeOutputPage( ApiParse $api, OutputPage $out ) {
		$out->setProperty( 'DisableMobileFrontendContentProvider', true );
	}

	/**
	 * @param OutputPage $out
	 * @return bool
	 */
	private static function shouldApplyContentProvider( $out ) {
		$services = MediaWikiServices::getInstance();
		$config = $services->getService( 'MobileFrontendContentProvider.Config' );
		$title = $out->getTitle();
		// Don't apply content provider in hook for tests
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			return false;
		}
		// don't run on action=parse.
		if ( $out->getProperty( 'DisableMobileFrontendContentProvider' ) ) {
			return false;
		}
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
		$provider = $contentProviderFactory->getProvider( $out, false );
	}
}
