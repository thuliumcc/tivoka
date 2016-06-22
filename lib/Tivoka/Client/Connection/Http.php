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
 * @author Rafał Wrzeszcz <rafal.wrzeszcz@wrzasq.pl>
 * @author Andrzej Oczkowicz <andrzej.oczkowicz@thulium.pl>
 * @copyright (c) 2011-2012, Marcel Klehr
 */

namespace Tivoka\Client\Connection;

use Tivoka\Client\BatchRequest;
use Tivoka\Exception;
use Tivoka\Client\Request;

/**
 * HTTP connection
 * @package Tivoka
 */
class Http extends AbstractConnection
{

    public $target;
    public $headers = array();

    private $ch;
    private $defaultHeaders = array(
        "Content-Type" => "application/json",
        "Connection" => "Close"
    );

    /**
     * Constructs connection
     * @access private
     * @param string $target URL
     *
     * @throws Exception\Exception
     */
    public function __construct($target)
    {
        //validate url...
        if (!filter_var($target, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)) {
            throw new Exception\Exception('Valid URL (scheme://domain[/path][/file]) required.');
        }

        //validate scheme...
        $t = parse_url($target);
        if (strtolower($t['scheme']) != 'http' && strtolower($t['scheme']) != 'https') {
            throw new Exception\Exception('Unknown or unsupported scheme given.');
        }

        $this->target = $target;

        // Create cURL handle only once here, and use this single handle for all requests. This
        // way, the cookies set will be kept in memory and reused for subsequent requests.
        if (extension_loaded('curl')) {
            $this->ch = curl_init($this->target);
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, true);
        }
    }

    public function __destruct()
    {
        if (!is_null($this->ch)) {
            curl_close($this->ch);
        }
    }

    /**
     * Sets the HTTP headers to use for upcoming send requests
     * @param string $label label of header
     * @param string $value value of header
     * @return Http Self instance
     */
    public function setHeader($label, $value)
    {
        $this->headers[$label] = $value;
        return $this;
    }

    public function getHeaders()
    {
        return array_merge($this->defaultHeaders, $this->headers);
    }

    /**
     * Sends a JSON-RPC request
     *
     * @param Request $request A Tivoka request
     *
     * @return Request if sent as a batch request the BatchRequest object will be returned
     * @throws Exception\Exception
     */
    public function send(Request $request)
    {
        if (func_num_args() > 1) $request = func_get_args();
        if (is_array($request)) {
            $request = new BatchRequest($request);
        }

        if (!($request instanceof Request)) throw new Exception\Exception('Invalid data type to be sent to server');

        if (!is_null($this->ch)) {
            $headers = array();
            foreach ($this->getHeaders() as $label => $value) {
                $headers[] = $label . ": " . $value;
            }
            $response_headers = array();
            $headerFunction = function ($ch, $header) use (&$response_headers) {
                $header2 = rtrim($header, "\r\n");
                if ($header2 != '') {
                    $response_headers[] = $header2;
                }
                return strlen($header); // Use original header length!
            };
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
            if (isset($this->options['ssl_verify_peer'])) {
                curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, $this->options['ssl_verify_peer']);
            }
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request->getRequest($this->spec));
            curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, $headerFunction);
            $response = @curl_exec($this->ch);
        } elseif (ini_get('allow_url_fopen')) {
            // preparing connection...
            $context = array(
                'http' => array(
                    'content' => $request->getRequest($this->spec),
                    'header' => "",
                    'method' => 'POST',
                    'timeout' => $this->timeout
                )
            );
            if (isset($this->options['ssl_verify_peer'])) {
                $context['ssl']['verify_peer'] = $this->options['ssl_verify_peer'];
            }
            foreach ($this->getHeaders() as $label => $value) {
                $context['http']['header'] .= $label . ": " . $value . "\r\n";
            }
            //sending...
            $response = @file_get_contents($this->target, false, stream_context_create($context));
            $response_headers = $http_response_header;
        } else {
            throw new Exception\ConnectionException('Install cURL extension or enable allow_url_fopen');
        }
        if ($response === FALSE) {
            throw new Exception\ConnectionException('Connection to "' . $this->target . '" failed');
        }

        $headersArray = self::http_parse_headers($response_headers);
        $this->validateHttpResponseCode($headersArray, $request->getValidHttpCodes());

        $request->setHeaders($headersArray);
        $request->setRawHeaders($response_headers);
        $request->setResponse($response);
        return $request;
    }

    /**
     * Parses headers as returned by magic variable $http_response_header
     * @param array $headers array of string coming from $http_response_header
     * @return array associative array linking a header label with its value
     */
    public static function http_parse_headers($headers)
    {
        $headers_array = array();
        foreach ($headers as $header) {
            if (preg_match('/(?P<label>[^ :]+):(?P<body>(.|\r?\n(?= +))*)$/', $header, $matches)) {
                $headers_array[$matches["label"]] = trim($matches["body"]);
            } else {
                if (preg_match('|HTTP/(?<protocol_version>\d+\.\d+)\s+(?<http_code>\d+)\s*(?<status_text>\w*)|i', $header, $matches)) {
                    $http_status = array_intersect_key($matches, array_flip(array('protocol_version', 'http_code', 'status_text')));
                    $headers_array['http_status'] = $http_status;
                }
            }
        };
        return $headers_array;
    }

    private function validateHttpResponseCode($headersArray, $validHttpCodesForRequest)
    {
        if (isset($headersArray['http_status']['http_code'])) {
            $httpCode = $headersArray['http_status']['http_code'];
            if (in_array($httpCode, $validHttpCodesForRequest)) {
                $httpStatus = $headersArray['http_status']['status_text'];
                throw new Exception\ConnectionException("Connection to '{$this->target}' failed. Http code: '$httpCode', status: '$httpStatus'.");
            }
        }
    }
}
