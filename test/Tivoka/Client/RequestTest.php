<?php

use Tivoka\Client\Request;
use Tivoka\Client\RequestSerializer;
use Tivoka\Tivoka;

class CustomJsonSerializer implements RequestSerializer
{
    public function serialize($data)
    {
        $data["id"] = "1xxx1";
        $data["serializer"] = "CustomJsonSerializer";
        return json_encode($data);
    }
}

class RequestTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldSetRequestSerializer()
    {
        //given
        $serializer = new CustomJsonSerializer();
        $request = new Request("test", null, $serializer);

        //when
        $requestJson = $request->getRequest(Tivoka::SPEC_2_0);

        //then
        $this->assertEquals('{"jsonrpc":"2.0","method":"test","id":"1xxx1","serializer":"CustomJsonSerializer"}', $requestJson);
    }
}
