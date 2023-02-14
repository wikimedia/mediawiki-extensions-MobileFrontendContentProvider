<?php

use MobileFrontendContentProviders\ContentProviderFactory;
use MobileFrontendContentProviders\MwApiContentProvider;
use MobileFrontendContentProviders\ParsoidContentProvider;

/**
 * @group MobileFrontend
 * @coversDefaultClass \MobileFrontendContentProviders\ContentProviderFactory
 */
class ContentProviderFactoryTest extends MediaWikiIntegrationTestCase {
	// Test HTML
	private const TEST_HTML = '<a>Anchor</a>';

	/**
	 * Mock OutputPage class
	 * @return OutputPage
	 */
	private function mockOutputPage() {
		// Mock Title class
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'exists' )->willReturn( true );

		// Mock Skin class
		$mockSkin = $this->createMock( Skin::class );
		$mockSkin->method( 'getSkinName' )->willReturn( 'testSkin' );

		// Mock the FauxRequest class
		$mockFauxRequest = $this->createMock( FauxRequest::class );
		$mockFauxRequest->method( 'getIntOrNull' )->willReturn( 12345 );

		// Mock OutputPage class
		$mockOutputPage = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getTitle', 'getRequest', 'getSkin' ] )
			->getMock();

		$mockOutputPage->method( 'getTitle' )
			->willReturn( $mockTitle );

		$mockOutputPage->method( 'getRequest' )
			->willReturn( $mockFauxRequest );

		$mockOutputPage->method( 'getSkin' )
			->willReturn( $mockSkin );

		return $mockOutputPage;
	}

	/**
	 * Mock the OutputPage class where title doesn't exist
	 * @return OutputPage
	 */
	private function mockOutputPageWithNoTitle() {
		// Mock Title class
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'exists' )->willReturn( false );

		// Mock OutputPage class.
		$mockOutputPageNoTitle = $this->createMock( OutputPage::class );
		$mockOutputPageNoTitle->method( 'getTitle' )->willReturn( $mockTitle );

		return $mockOutputPageNoTitle;
	}

	/**
	 * @covers ::getProvider
	 */
	public function testGetProviderWithNoMFContentProvider() {
		$mockOutputPage = $this->mockOutputPage();
		$config = new HashConfig( [
			'MFContentProviderClass' => '',
			'MFContentProviderTryLocalContentFirst' => false,
			'MFContentProviderScriptPath' => '',
		] );
		$factory = new ContentProviderFactory( $config );
		$this->expectException( RuntimeException::class );
		$factory->getProvider( $mockOutputPage, self::TEST_HTML );
	}

	/**
	 * @covers ::getProvider
	 */
	public function testGetProviderWithInvalidContentProvider() {
		$mockOutputPage = $this->mockOutputPage();
		$config = new HashConfig( [
			'MFContentProviderClass' => ContentProviderFactory::class,
			'MFContentProviderTryLocalContentFirst' => false,
			'MFContentProviderScriptPath' => false
		] );
		$factory = new ContentProviderFactory( $config );
		$this->expectException( RuntimeException::class );
		$factory->getProvider( $mockOutputPage, self::TEST_HTML );
	}

	/**
	 * This test suite tests the use of various MFContentProviderClass and
	 * MFContentProviderTryLocalContentFirst on different scenarios. When
	 * true or false for MFContentProviderTryLocalContentFirst.
	 * @covers ::getProvider
	 * @dataProvider contentProvidersDataProvider
	 */
	public function testGetProvider( $contentProvider, $localContent, $expected ) {
		$mockOutputPage = $this->mockOutputPage();
		$config = new HashConfig( [
			'MFContentProviderClass' => $contentProvider,
			'MFContentProviderTryLocalContentFirst' => $localContent,
			'MFContentProviderScriptPath' => false,
			'MFParsoidContentProviderBaseUri' => 'http://localhost/',
			'MFMwApiContentProviderBaseUri' => 'http://localhost/',
			'MFMwApiContentProviderFixArticlePath' => false,
		] );
		$factory = new ContentProviderFactory( $config );
		$provider = $factory->getProvider( $mockOutputPage, self::TEST_HTML );

		if ( $expected === null ) {
			$this->assertSame( $expected, $provider );
		} else {
			$this->assertInstanceOf( $expected, $provider );
		}
	}

	/**
	 * Data provider for testGetProviderWithDefaultContentProviderWithNoTitle()
	 * @return array
	 */
	public function contentProvidersDataProvider() {
		return [
			[ ParsoidContentProvider::class, true, null ],
			[ MwApiContentProvider::class, true, null ],
			[ ParsoidContentProvider::class, false, ParsoidContentProvider::class ],
			[ MwApiContentProvider::class, false, MwApiContentProvider::class ]
		];
	}
}
