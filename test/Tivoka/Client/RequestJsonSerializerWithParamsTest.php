<?php

use Tivoka\Client\RequestJsonSerializerWithParams;

class RequestJsonSerializerWithParamsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldSpecifyJsonEncodeParameter()
    {
        //given
        $serializer = new RequestJsonSerializerWithParams(JSON_UNESCAPED_SLASHES);

        //when
        $json = $serializer->serialize(["test" => "11/11/2011"]);

        //then
        $this->assertEquals('{"test":"11/11/2011"}', $json);
    }
}
