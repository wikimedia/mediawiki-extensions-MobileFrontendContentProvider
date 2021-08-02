<?php

use MediaWiki\MediaWikiServices;
use MobileFrontendContentProviders\ContentProviderFactory;

return [
	'MobileFrontendContentProvider.Config' => static function ( MediaWikiServices $services ) {
		return $services->getService( 'ConfigFactory' )
			->makeConfig( 'MobileFrontendContentProvider' );
	},
	'MobileFrontendContentProvider.Factory' =>
	static function ( MediaWikiServices $services ): ContentProviderFactory {
		$config = $services->getService( 'MobileFrontendContentProvider.Config' );
		return new ContentProviderFactory( $config );
	}
];
