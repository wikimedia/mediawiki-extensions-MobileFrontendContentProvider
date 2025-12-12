<?php

namespace MobileFrontendContentProviders;

use Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MobileFrontend\ContentProviders\IContentProvider;
use OutputPage;
use RuntimeException;

class ContentProviderFactory {
	private const MW_API = MwApiContentProvider::class;
	private const PARSOID_API = ParsoidContentProvider::class;
	private const PHP_PARSER = DefaultContentProvider::class;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param Config $config ProviderFactory config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Turn on foreign api script path for page
	 * @param OutputPage $out to allow the addition of modules and styles
	 *  as required by the content
	 */
	public function addForeignScriptPath( OutputPage $out ) {
		$contentProviderScriptPath = $this->config->get( 'MFContentProviderScriptPath' );

		if ( $contentProviderScriptPath ) {
			// It's very possible this might break compatibility with other extensions
			// so this should not be used outside development :). Please see README.md
			$out->addJsConfigVars( [
				'wgScriptPath' => $contentProviderScriptPath
			] );
			// This injects a global ajaxSend event which ensures origin=* is added to all ajax requests
			// This helps with compatibility of VisualEditor!
			// This is intentionally simplistic as all queries we care about
			// are guaranteed to already have a query string
			$out->addModules( 'mobile.contentProviderApi' );
		}
	}

	/**
	 * @param OutputPage $out to allow the addition of modules and styles
	 *  as required by the content
	 * @param bool $provideTagline (optional) whether wikidata descriptions
	 *  should be provided for if the provider supports it.
	 * @throws RuntimeException Thrown when specified ContentProvider doesn't exist
	 * @return IContentProvider|null
	 */
	public function getProvider( OutputPage $out, $provideTagline = false
	) {
		$contentProviderClass = $this->config->get( 'MFContentProviderClass' );

		if ( !class_exists( $contentProviderClass ) ) {
			// Map old MobileFrontend classes to new ones
			$contentProviderClass = str_replace(
				'MobileFrontend\ContentProviders',
				'MobileFrontendContentProviders',
				$contentProviderClass
			);
			if ( !class_exists( $contentProviderClass ) ) {
				throw new RuntimeException(
					"Provider `$contentProviderClass` specified in MFContentProviderClass does not exist." );
			}
		}
		$preserveLocalContent = $this->config->get( 'MFContentProviderTryLocalContentFirst' );
		$title = $out->getTitle();
		// On local content display the content provider script path so that edit works as expected
		// at the cost of searching local content.
		if ( $preserveLocalContent && $title && $title->exists() ) {
			return null;
		}

		$this->addForeignScriptPath( $out );
		$parsoidClass = 'MobileFrontendContentProviders\\ParsoidContentProvider';

		$shouldUseParsoid = false;
		$context = $out->getContext();
		$req = $context->getRequest();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ParserMigration' ) ) {
			$oracle = MediaWikiServices::getInstance()->getService( 'ParserMigration.Oracle' );
			$shouldUseParsoid =
				$oracle->shouldUseParsoid( $context->getUser(), $req, $title );
		}
		$providerType = $out->getRequest()->getText( 'mfprovidertype' );

		$useParsoid = $out->getRequest()->getBool( 'useparsoid' );
		if ( $useParsoid ) {
			$contentProviderClass = $parsoidClass;
		}

		// Where default is parsoid switchover
		if ( $shouldUseParsoid && !$providerType && !$useParsoid ) {
			$providerType = 'parsoid';
		}
		switch ( $providerType ) {
			case 'parsoid':
				$contentProviderClass = $parsoidClass;
				break;
			default:
				break;
		}
		$project = $out->getRequest()->getText( 'mfproviderproject' );
		$lang = $out->getRequest()->getText( 'mfproviderlang' ) ?: 'en';
		$baseUrlParsoid = $this->config->get( 'MFParsoidContentProviderBaseUri' );
		switch ( $project ) {
			case 'wikifunctions':
				throw new RuntimeException( 'Not supported!' );
			case 'meta':
				$baseUrl = 'https://meta.wikimedia.org/w/api.php';
				$baseUrlParsoid = 'https://meta.wikimedia.org/';
				break;
			case 'mediawiki':
				$baseUrl = 'https://www.' . $project . '.org/w/api.php';
				$baseUrlParsoid = 'https://www.' . $project . '.org/';
				break;
			case 'wikiversity':
			case 'wikiquote':
			case 'wikinews':
			case 'wikisource':
			case 'wikibooks':
			case 'wiktionary':
			case 'wikivoyage':
			case 'wikipedia':
				$baseUrl = 'https://' . $lang . '.' . $project . '.org/w/api.php';
				$baseUrlParsoid = 'https://' . $lang . '.' . $project . '.org/';
				break;
			default:
				$baseUrl = $this->config->get( 'MFMwApiContentProviderBaseUri' );
				break;
		}
		switch ( $contentProviderClass ) {
			case self::PARSOID_API:
				return new ParsoidContentProvider( $baseUrlParsoid, $out );
			case self::MW_API:
				$skinName = $out->getSkin()->getSkinName();
				$rev = $out->getRequest()->getIntOrNull( 'oldid' );
				$articlePath = null;
				if ( $this->config->get( 'MFMwApiContentProviderFixArticlePath' ) ) {
					$articlePath = $this->config->get( 'ArticlePath' );
				}
				return new MwApiContentProvider( $baseUrl, $articlePath, $out, $skinName, $rev, $provideTagline );
			case self::PHP_PARSER:
				return null;
			default:
				throw new RuntimeException(
					"Unknown provider `$contentProviderClass` specified in MFContentProviderClass" );
		}
	}
}
