<?php

use MediaWiki\Http\HttpRequestFactory;
use MobileFrontendContentProviders\ParsoidContentProvider;

/**
 * @group MobileFrontend
 * @coversDefaultClass \MobileFrontendContentProviders\ParsoidContentProvider
 * @covers ::__construct
 */
class ParsoidContentProviderTest extends MediaWikiTestCase {
	private const BASE_URL = '/w/api.php';

	/**
	 * @param string $baseUrl
	 * @param Title|null $title
	 * @return ParsoidContentProvider
	 */
	private function makeParsoidContentProvider( $baseUrl, Title $title = null ) {
		$out = new OutputPage( new RequestContext() );
		if ( $title ) {
			$out->setTitle( $title );
		} else {
			// make sure RequestContext doesn't pick up a title from the global
			$this->setMwGlobals( 'wgTitle', null );
		}
		return new ParsoidContentProvider( $baseUrl, $out );
	}

	private function createTestTitle() {
		return Title::newFromText( 'Test Title' );
	}

	/**
	 * @param string $url
	 * @param string $rawResponse
	 * @return HttpRequestFactory
	 */
	private function mockHTTPFactory( $url, $rawResponse ) {
		$httpRequestMock = $this->createMock( MWHttpRequest::class );

		$httpRequestMock->expects( $this->at( 0 ) )
			->method( 'execute' )
			->willReturn( StatusValue::newGood() );

		$httpRequestMock->expects( $this->at( 1 ) )
			->method( 'getContent' )
			->willReturn( $rawResponse );

		$factoryMock = $this->createMock( HttpRequestFactory::class );
		$factoryMock->expects( $this->once() )
			->method( 'create' )
			->with( $url )
			->willReturn( $httpRequestMock );

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
			->with( self::BASE_URL . '/page/html/Test_Title' )
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

		$url = self::BASE_URL . '/page/html/Test_Title';
		$this->setService( 'HttpRequestFactory', $this->mockHTTPFactory( $url, 'text' ) );
		$ParsoidContentProvider = $this->makeParsoidContentProvider( self::BASE_URL, $title );
		$actual = $ParsoidContentProvider->getHTML();

		$this->assertSame( 'text', $actual );
	}

	/**
	 * @covers ::getHTML
	 * @covers ::fileGetContents
	 */
	public function testGetHtmlWithValidResponse() {
		$sampleResponse = 'value<h2>l</h2>t';
		$title = $this->createTestTitle();

		$url = self::BASE_URL . '/page/html/Test_Title';
		$this->setService( 'HttpRequestFactory', $this->mockHTTPFactory( $url, $sampleResponse ) );
		$ParsoidContentProvider = $this->makeParsoidContentProvider( self::BASE_URL, $title );
		$actual = $ParsoidContentProvider->getHTML();

		$expected = "value<h2>l</h2>t";
		$this->assertSame( $expected, $actual );
	}
}
