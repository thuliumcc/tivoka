<?php


use Tivoka\Client\Connection\Http;

class HttpTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldParseHeadersWithStatusLine()
    {
        //given
        $headers = array(
            "HTTP/1.1    200     OK",
            "Date: Wed, 22 Jun 2016 10:03:19 GMT",
            "X-Powered-By: PHP/5.4.39-0+deb7u2",
            "Content-Type: application/json"
        );

        //when
        $parsed = Http::http_parse_headers($headers);

        //then
        $this->assertEquals(array(
            'http_status' => array('protocol_version' => '1.1',
                'http_code' => '200',
                'status_text' => 'OK'
            ),
            'Date' => 'Wed, 22 Jun 2016 10:03:19 GMT',
            'X-Powered-By' => 'PHP/5.4.39-0+deb7u2',
            'Content-Type' => 'application/json'
        ), $parsed);
    }
}
