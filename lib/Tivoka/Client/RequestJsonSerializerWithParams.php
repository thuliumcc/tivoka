<?php
namespace Tivoka\Client;

class RequestJsonSerializerWithParams implements RequestSerializer
{
    public function serialize($data)
    {
        return json_encode($data);
    }
}