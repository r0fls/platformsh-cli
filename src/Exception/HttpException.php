<?php

namespace Platformsh\Cli\Exception;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use Platformsh\Client\Exception\ApiResponseException;

class HttpException extends \RuntimeException
{
    protected $message = 'An API error occurred.';

    /**
     * @param string            $message
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     */
    public function __construct($message = null, RequestInterface $request = null, ResponseInterface $response = null)
    {
        $message = $message ?: $this->message;
        if ($request !== null && $response !== null) {
            $details = "[url] " . $request->getUrl();
            $details .= " [status code] " . $response->getStatusCode();
            $details .= " [reason phrase] " . $response->getReasonPhrase();
            $details .= ApiResponseException::getErrorDetails($response);
            $message .= "\n\nDetails:\n" . $details;
        }

        parent::__construct($message, $this->code);
    }
}
