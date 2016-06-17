<?php

use Tivoka\Client\BatchRequest;
use Tivoka\Client\Request;
use Tivoka\Client\RequestJsonSerializerWithParams;
use Tivoka\Tivoka;

class BatchRequestTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldAddSerializer()
    {
        //given
        $serializer = new RequestJsonSerializerWithParams(JSON_UNESCAPED_SLASHES);
        $batchRequest = new BatchRequest([new Request("test", ["p" => "11/11/2011"])], $serializer);

        //when
        $request = $batchRequest->getRequest(Tivoka::SPEC_2_0);

        //then
        $this->assertContains("11/11/2011", $request);
    }
}
