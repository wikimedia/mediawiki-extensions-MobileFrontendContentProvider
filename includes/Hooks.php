<?php
namespace MobileFrontendContentProviders;

use Action;
use MediaWiki\MediaWikiServices;
use SpecialPage;

class Hooks {
	/**
	 * Invocation of hook SpecialPageBeforeExecute
	 *
	 * We use this hook to ensure that login/account creation pages
	 * are redirected to HTTPS if they are not accessed via HTTPS and
	 * $wgSecureLogin == true - but only when using the
	 * mobile site.
	 *
	 * @param SpecialPage $special
	 * @param string $subpage subpage name
	 */
	public static function onSpecialPageBeforeExecute( SpecialPage $special, $subpage ) {
		/** @var ContentProviderFactory $contentProviderFactory */
		$contentProviderFactory = $services->getService( 'MobileFrontend.ContentProviderFactory' );
		// Set foreign script path on special pages e.g. Special:Nearby
		$contentProviderFactory->addForeignScriptPath( $out );
	}

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
	public static function onOutputPageBeforeHTML( $out ) {
		$config = MediaWikiServices::getInstance()->getService( 'MobileFrontendContentProvider.Config' );
		$contentProviderApi = $config->get( 'MFContentProviderScriptPath' );
		if ( $contentProviderApi ) {
			$out->addModule( 'mobile.contentProviderApi' );
		}
	}

	/**
	 * MobileFrontendContentProvider hook handler
	 * @see https://www.mediawiki.org/wiki/Extension:MobileFrontend/onMobileFrontendContentProvider
	 *
	 * Applies MobileFormatter to mobile viewed content
	 * Also enables Related Articles in the footer in the beta mode.
	 * Adds inline script to allow opening of sections while JS is still loading
	 *
	 * @param ContentProviderFactory &$provider
	 * @param OutputPage $out the OutputPage object to which wikitext is added
	 */
	public static function onMobileFrontendContentProvider(
		&$provider, $out
	) {
		$services = MediaWikiServices::getInstance();
		$title = $out->getTitle();
		$config = $services->getService( 'MobileFrontendContentProvider.Config' );
		if ( !$config->get( 'MFContentProviderEnabled' ) ) {
			return;
		}

		// User has asked to honor local content so exit.
		if ( $title->exists() && $config->get( 'MFContentProviderTryLocalContentFirst' ) ) {
			return;
		}

		if ( Action::getActionName( $out->getContext() ) !== 'view' ) {
			return;
		}
		/** @var ContentProviderFactory $contentProviderFactory */
		$contentProviderFactory = $services->getService( 'MobileFrontendContentProvider.Factory' );
		$provider = $contentProviderFactory->getProvider( $out, true );
	}
}
