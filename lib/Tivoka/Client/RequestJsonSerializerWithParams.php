<?php
namespace Tivoka\Client;

class RequestJsonSerializerWithParams implements RequestSerializer
{
    /**
     * @var null
     */
    private $jsonEncodeParameters;

    public function __construct($jsonEncodeParameters = null)
    {
        $this->jsonEncodeParameters = $jsonEncodeParameters;
    }

    public function serialize($data)
    {
        return json_encode($data, $this->jsonEncodeParameters);
    }
}