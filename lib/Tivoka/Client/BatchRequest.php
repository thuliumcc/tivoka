<?php
/**
 * Tivoka - JSON-RPC done right!
 * Copyright (c) 2011-2012 by Marcel Klehr <mklehr@gmx.net>
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package  Tivoka
 * @author Marcel Klehr <mklehr@gmx.net>
 * @author Andrzej Oczkowicz <andrzej.oczkowicz@thulium.pl>
 * @copyright (c) 2011-2012, Marcel Klehr
 */


namespace Tivoka\Client;

use Tivoka\Exception;
use Tivoka\Tivoka;

/**
 * A batch request
 * @package Tivoka
 */
class BatchRequest extends Request
{
    /** @var Request[] */
    protected $requests;

    /**
     * Constructs a new JSON-RPC batch request
     * All values of type other than Tivoka\Client\Request will be ignored
     * @param array $batch A list of requests to include, each a Tivoka_Request
     * @param RequestSerializer $serializer JSON serializer, default json_encode() with default flags
     * @see Tivoka_Client::send()
     */
    public function __construct(array $batch, $serializer = null)
    {
        $this->id = array();
        $this->serializer = $serializer ?: new RequestJsonSerializerWithParams();

        //prepare requests...
        foreach ($batch as $request) {
            if (!($request instanceof Request) && !($request instanceof Notification))
                continue;

            //request...
            if ($request instanceof Request) {
                if (in_array($request->id, $this->id, true)) continue; // strict compare
                $this->id[$request->id] = $request;
            }

            $this->requests[] = $request;
        }
    }

    /**
     * Get the raw, JSON-encoded request
     *
     * @param int $spec
     *
     * @return string the JSON encoded request
     * @throws Exception\SpecException
     */
    public function getRequest($spec)
    {
        if ($spec == Tivoka::SPEC_1_0) throw new Exception\SpecException('Batch requests are not supported by JSON-RPC 1.0 spec');
        $this->spec = $spec;
        $request = array();
        foreach ($this->requests as $req) {
            $request[] = json_decode($req->getRequest($spec), true);
        }
        return $this->request = $this->serializer->serialize($request);
    }

    /**
     * Interprets the parsed response
     *
     * @param array $json_struct json data
     *
     * @return void
     * @throws Exception\SyntaxException
     */
    public function interpretResponse($json_struct)
    {
        //validate
        if (count($json_struct) < 1 || !is_array($json_struct)) {
            throw new Exception\SyntaxException('Expected batch response, but none was received');
        }

        $requests = $this->id;
        $nullresps = array();
        $responses = array();

        //split..
        foreach ($json_struct as $resp) {
            if (!is_array($resp)) throw new Exception\SyntaxException('Expected batch response, but no array was received');

            //is jsonrpc protocol?
            if (!isset($resp['jsonrpc']) && !isset($resp['id'])) throw new Exception\SyntaxException('The received reponse doesn\'t implement the JSON-RPC prototcol.');

            //responds to an existing request?
            if (!array_key_exists($resp['id'], $requests)) {
                if ($resp['id'] != null) continue;

                $nullresps[] = $resp;
                continue;
            }

            //normal response...
            $requests[$resp['id']]->setResponse(json_encode($resp));
            unset($requests[$resp['id']]);
        }

        //handle id:null responses...
        foreach ($requests as $req) {
            if ($req instanceof Notification) continue;
            $resp = array_shift($nullresps);
            $requests[$req->id]->setResponse(json_encode($resp));
        }
    }

    /**
     * Save and parse the HTTP headers
     * @param array $headersArray parsed headers, key => value
     * @return void
     */
    public function setHeaders($headersArray)
    {
        parent::setHeaders($headersArray);

        $requests = $this->id;
        foreach ($requests as $req) {
            $requests[$req->id]->setHeaders($headersArray);
        }
    }

    /**
     * Save and parse the HTTP headers
     * @param array $raw_headers array of string coming from $http_response_header magic var
     * @return void
     */
    public function setRawHeaders($raw_headers)
    {
        parent::setRawHeaders($raw_headers);

        $requests = $this->id;
        foreach ($requests as $req) {
            $requests[$req->id]->setRawHeaders($raw_headers);
        }
    }

}
