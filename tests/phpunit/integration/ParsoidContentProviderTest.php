<?php

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Title\Title;
use MobileFrontendContentProviders\ParsoidContentProvider;

/**
 * @group MobileFrontend
 * @coversDefaultClass \MobileFrontendContentProviders\ParsoidContentProvider
 * @covers ::__construct
 */
class ParsoidContentProviderTest extends MediaWikiIntegrationTestCase {
	private const BASE_URL = '';
	private const PARSE_API_URL = '/w/api.php?action=parse&format=json&prop=modules%7Cjsconfigvars%7Csections'
		. '&parser=parsoid&useskin=minerva&formatversion=2&page=Test_Title';
	private const PARSE_API_RESPONSE = '{ "parse": { "modules": [], "jsconfigvars": {} } }';

	/**
	 * @param string $html
	 * @return string
	 */
	private function wrapHTML( $html ) {
		return '<html><head><title>full page html</title></head><body>' . $html . '</body></html>';
	}

	/**
	 * @param string $baseUrl
	 * @param Title|null $title
	 * @param bool $isMobile
	 * @return ParsoidContentProvider
	 */
	private function makeParsoidContentProvider( $baseUrl, ?Title $title = null, $isMobile = true ) {
		$skin = $this->createMock( Skin::class );
		$skin->method( 'getSkinName' )
			->willReturn( 'minerva' );
		$out = $this->createMock( OutputPage::class );
		$out->method( 'getSkin' )->willReturn( $skin );
		if ( $title ) {
			$out->method( 'getTitle' )->willReturn( $title );
		}
		return new ParsoidContentProvider( $baseUrl, $out, $isMobile );
	}

	private function createTestTitle() {
		return Title::newFromText( 'Test Title' );
	}

	/**
	 * @param string $rawResponse
	 * @return MWHttpRequest
	 */
	private function mockHttpRequest( $rawResponse ) {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );
		$httpRequestMock->method( 'execute' )
			->willReturn( StatusValue::newGood() );
		$httpRequestMock->method( 'getContent' )
			->willReturn( $rawResponse );
		return $httpRequestMock;
	}

	/**
	 * @param array[] $request
	 * @return HttpRequestFactory
	 */
	private function mockHTTPFactory( $request ) {
		$factoryMock = $this->createMock( HttpRequestFactory::class );
		$responsesByUrl = [];
		foreach ( $request as [ $url, $rawResponse ] ) {
			$responsesByUrl[$url] = $this->mockHttpRequest( $rawResponse );
		}
		$factoryMock
			->method( 'create' )
			->willReturnCallback( static function ( $url ) use ( $responsesByUrl ) {
				return $responsesByUrl[ $url ];
			} );

		return $factoryMock;
	}

	/**
	 * Mock bad HTTP factory so ->isOK() returns false
	 * @return HttpRequestFactory
	 */
	private function mockBadHTTPFactory() {
		$badHttpRequestMock = $this->createMock( MWHttpRequest::class );
		$badHttpRequestMock->method( 'execute' )
			->willReturn( StatusValue::newFatal( 'fatal' ) );
		$badHttpRequestMock->expects( $this->never() )
			->method( 'getContent' )
			->willReturn( '{}' );

		$badFactoryMock = $this->createMock( HttpRequestFactory::class );
		$badFactoryMock->method( 'create' )
			->willReturn( $badHttpRequestMock );

		return $badFactoryMock;
	}

	/**
	 * Test path when HTTP request was not completed successfully
	 * @covers ::getHTML
	 * @covers ::fileGetContents
	 */
	public function testHttpRequestIsNotOK() {
		$title = $this->createTestTitle();

		$this->setService( 'HttpRequestFactory', $this->mockBadHTTPFactory() );
		$ParsoidContentProvider = $this->makeParsoidContentProvider( self::BASE_URL, $title );
		$actual = $ParsoidContentProvider->getHTML();

		$this->assertSame( '', $actual );
	}

	/**
	 * @covers ::getHTML
	 */
	public function testGetHtmlWithNoTitle() {
		$ParsoidContentProvider = $this->makeParsoidContentProvider( self::BASE_URL, null );

		$actual = $ParsoidContentProvider->getHTML();
		$this->assertSame( '', $actual );
	}

	/**
	 * @covers ::getHTML
	 */
	public function testGetHtmlWithOperationNotOkay() {
		$httpFalseRequestMock = $this->createMock( MWHttpRequest::class );

		$httpFalseRequestMock->method( 'execute' )
			->willReturn( StatusValue::newFatal( 'fatal' ) );

		$factoryMock = $this->createMock( HttpRequestFactory::class );
		$factoryMock->expects( $this->once() )
			->method( 'create' )
			->with( self::BASE_URL . '/wiki/Test_Title?useparsoid=1&useskin=minerva&useformat=mobile' )
			->willReturn( $httpFalseRequestMock );

		$title = $this->createTestTitle();

		$this->setService( 'HttpRequestFactory', $factoryMock );
		$ParsoidContentProvider = $this->makeParsoidContentProvider( self::BASE_URL, $title );
		$actual = $ParsoidContentProvider->getHTML();

		$this->assertSame( '', $actual );
	}

	/**
	 * @covers ::getHTML
	 * @covers ::fileGetContents
	 */
	public function testGetHtmlWithResponseDecodedNotArray() {
		$title = $this->createTestTitle();

		$url = self::BASE_URL . '/wiki/Test_Title?useparsoid=1&useskin=minerva&useformat=mobile';
		$html = '<div class="mw-parser-output" data-mw-parsoid-version="1">text</div>';
		$this->setService(
			'HttpRequestFactory',
			$this->mockHTTPFactory( [
				[
					$url,
					$this->wrapHTML( $html )
				],
				[
					self::PARSE_API_URL,
					self::PARSE_API_RESPONSE
				],
			] )
		);
		$ParsoidContentProvider = $this->makeParsoidContentProvider( self::BASE_URL, $title );
		$actual = $ParsoidContentProvider->getHTML();

		$this->assertSame( $html, $actual );
	}

	/**
	 * @covers ::getHTML
	 * @covers ::fileGetContents
	 */
	public function testGetHtmlWithValidResponse() {
		$html = '<div class="mw-parser-output" data-mw-parsoid-version="1">value<h2>l</h2>t</div>';
		$sampleResponse = $this->wrapHTML( $html );
		$title = $this->createTestTitle();

		$url = self::BASE_URL . '/wiki/Test_Title?useparsoid=1&useskin=minerva&useformat=mobile';
		$this->setService(
			'HttpRequestFactory',
			$this->mockHTTPFactory( [
				[ $url, $sampleResponse ],
				[ self::PARSE_API_URL, self::PARSE_API_RESPONSE ],
			] )
		);
		$ParsoidContentProvider = $this->makeParsoidContentProvider( self::BASE_URL, $title );
		$actual = $ParsoidContentProvider->getHTML();

		$this->assertSame( $html, $actual );
	}
}
