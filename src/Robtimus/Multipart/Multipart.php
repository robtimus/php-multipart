<?php

namespace Robtimus\Multipart;

use ErrorException;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

/**
 * Base class of multipart types.
 *
 * @package Robtimus\Multipart
 * @author  Rob Spoor <robtimus@users.noreply.github.com>
 * @license https://www.apache.org/licenses/LICENSE-2.0.txt The Apache Software License, Version 2.0
 *
 * @SuppressWarnings("php:S1448")
 */
abstract class Multipart
{
    /**
     * The multipart boundary.
     *
     * @var string
     */
    private $boundary;

    /**
     * The content type.
     *
     * @var string
     */
    private $contentType;

    /**
     * The parts that form this multipart object.
     *
     * @var array<string|resource|callable(int):string>
     */
    private $parts = [];

    /**
     * The number of parts.
     *
     * @var integer
     */
    private $partCount = 0;

    /**
     * Whether or not the multipart is finished.
     *
     * @var bool
     */
    private $finished = false;

    /**
     * The index of the current part.
     *
     * @var int
     */
    private $index = 0;

    /**
     * For string parts only, the index within the current part.
     *
     * @var int
     */
    private $partIndex = 0;

    /**
     * The content length, or -1 if not known.
     *
     * @var int
     */
    private $contentLength = 0;

    /**
     * Creates a new multipart object.
     *
     * @param string $boundary    The multipart boundary. If empty a new boundary will be generated.
     * @param string $contentType The content type without the boundary.
     *
     * @throws InvalidArgumentException If the given content type is empty.
     */
    protected function __construct($boundary, $contentType)
    {
        Util::validateString($boundary, '$boundary');
        Util::validateNonEmptyString($contentType, '$contentType');

        $this->boundary = $boundary !== '' ? $this->escapeHeaderValue($boundary, false) : $this->generateBoundary();
        $this->contentType = $contentType . '; boundary=' . $this->boundary;
    }

    /**
     * Generates a new random boundary.
     *
     * @return string
     */
    private function generateBoundary()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Returns the multipart boundary.
     *
     * @return string
     */
    final public function getBoundary()
    {
        return $this->boundary;
    }

    /**
     * Returns the multipart's content type.
     *
     * @return string
     */
    final public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Returns the multipart's content length, or -1 if not known.
     *
     * @return int
     */
    final public function getContentLength()
    {
        return $this->contentLength;
    }

    /**
     * Starts a new part.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function startPart()
    {
        $this->add('--' . $this->boundary . "\r\n");
    }

    /**
     * Adds a Content-Disposition header.
     *
     * @param string $type     The Content-Disposition type (e.g. form-data, attachment).
     * @param string $name     The value for any name parameter.
     * @param string $filename The value for any filename parameter.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addContentDisposition($type, $name = '', $filename = '')
    {
        $header = 'Content-Disposition: ' . $this->escapeHeaderValue($type, false);
        if ($name !== '') {
            $header .= '; name="' . $this->escapeHeaderValue($name, false) . '"';
        }
        if ($filename !== '') {
            $header .= '; filename="' . $this->escapeHeaderValue($filename, false) . '"';
        }
        $this->add($header . "\r\n");
    }

    /**
     * Adds a Content-ID header.
     *
     * @param string $contentID The content ID.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addContentID($contentID)
    {
        $this->add('Content-ID: ' . $this->escapeHeaderValue($contentID, false) . "\r\n");
    }

    /**
     * Adds a Content-Type header.
     *
     * @param string $contentType The content type.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addContentType($contentType)
    {
        $this->add('Content-Type: ' . $this->escapeHeaderValue($contentType, true) . "\r\n");
    }

    /**
     * Adds a Content-Transfer-Encoding header.
     *
     * @param string $contentTransferEncoding The content transfer encoding.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addContentTransferEncoding($contentTransferEncoding)
    {
        $this->add('Content-Transfer-Encoding: ' . $this->escapeHeaderValue($contentTransferEncoding, false) . "\r\n");
    }

    /**
     * Ends the headers.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function endHeaders()
    {
        $this->add("\r\n");
    }

    /**
     * Adds the content of a part.
     *
     * @param string|resource|callable(int):string $content The content.
     *                                                      If it's a callable it should take a length argument
     *                                                      and return a string that is not larger than the input.
     * @param int                                  $length  The length of the part, or -1 if not known.
     *                                                      Ignored if the part is a string.
     *
     * @return void
     * @throws InvalidArgumentException If the content is not a string, resource or callable.
     * @throws LogicException           If the multipart is already finished.
     */
    final protected function addContent($content, $length = -1)
    {
        $this->add($content, $length);
    }

    /**
     * Adds a nested multipart.
     *
     * @param Multipart $multipart The nested multipart.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addNestedMultipart(Multipart $multipart)
    {
        $this->startPart();
        $this->addContentType($multipart->getContentType());
        $this->endHeaders();
        $this->addContent(array($multipart, 'read'), $multipart->getContentLength());
        $this->endPart();
    }

    /**
     * Ends the last part.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function endPart()
    {
        $this->add("\r\n");
    }

    /**
     * Finishes the multipart. Nothing can be added to it afterwards.
     *
     * @return Multipart this object.
     * @throws LogicException If the multipart is already finished.
     */
    final public function finish()
    {
        $this->add('--' . $this->boundary . "--\r\n");
        $this->finished = true;

        return $this;
    }

    /**
     * Returns whether or not the multipart is finished.
     *
     * @return bool
     */
    final public function isFinished()
    {
        return $this->finished;
    }

    /**
     * Escapes a header value.
     *
     * @param string $value       The value to escape.
     * @param bool   $allowQuotes Whether or not to allow quotes in the value.
     *
     * @return string
     */
    private function escapeHeaderValue($value, $allowQuotes)
    {
        $result = str_replace("\r", '%0D', $value);
        $result = str_replace("\n", '%0A', $result);
        if (!$allowQuotes) {
            $result = str_replace('"', '%22', $result);
        }
        return $result;
    }

    /**
     * Adds a piece of a part.
     *
     * @param string|resource|callable(int):string $part   The part to add.
     *                                                     If it's a callable it should take a length argument
     *                                                     and return a string that is not larger than the input.
     * @param int                                  $length The length of the part, or -1 if not known.
     *                                                     Ignored if the part is a string.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    private function add($part, $length = -1)
    {
        if ($this->finished) {
            throw new LogicException('can\'t add to a finished multipart object');
        }

        if (is_string($part)) {
            $length = strlen($part);
            $this->parts[] = $part;
            $this->partCount++;
            if ($this->contentLength !== -1) {
                $this->contentLength += $length;
            }
        } elseif (is_resource($part) || is_callable($part)) {
            $this->parts[] = $part;
            $this->partCount++;
            if ($length === -1) {
                $this->contentLength = -1;
            } elseif ($this->contentLength !== -1) {
                $this->contentLength += $length;
            }
        } else {
            throw new InvalidArgumentException('non-supported part type: ' . gettype($part));
        }
    }

    /**
     * Reads a portion of this multipart object.
     *
     * @param int $length The maximum length of the portion to read.
     *
     * @return string a portion of this multipart object not larger than the given length,
     *                or an empty string if nothing remains to be read.
     * @throws LogicException           If the multipart is not yet finished.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    final public function read($length)
    {
        if (!$this->finished) {
            throw new LogicException('can\'t read from a non-finished multipart object');
        }

        Util::validateInt($length, '$length');
        if ($length <= 0) {
            return '';
        }

        return $this->doRead($length);
    }

    /**
     * Reads a portion of this multipart object.
     *
     * @param int<1, max> $length The maximum length of the portion to read.
     *
     * @return string a portion of this multipart object not larger than the given length,
     *                or an empty string if nothing remains to be read.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    private function doRead($length)
    {
        while ($this->index < $this->partCount) {
            $data = $this->doReadFromPart($length);
            if ($data !== '') {
                return $data;
            }
            $this->index++;
            $this->partIndex = 0;
        }
        return '';
    }

    /**
     * Reads a portion of the current part of this multipart object.
     *
     * @param int<1, max> $length The maximum length of the portion to read.
     *
     * @return string a portion of this multipart object not larger than the given length,
     *                or an empty string if nothing remains to be read.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    private function doReadFromPart($length)
    {
        $part = $this->parts[$this->index];
        if (is_string($part)) {
            $partLength = strlen($part);
            $length = min($length, $partLength - $this->partIndex);
            $result = $length === 0 ? '' : substr($part, $this->partIndex, $length);
            $this->partIndex += $length;
            return $result;
        } elseif (is_resource($part)) {
            $result = @fread($part, $length);
            if ($result === false) {
                throw new ErrorException(error_get_last()['message']);
            }
            return $result;
        } elseif (is_callable($part)) {
            return call_user_func($part, $length);
        } else {
            throw new UnexpectedValueException('non-supported part type: ' . gettype($part));
        }
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    /**
     * A version of the read method that is compatible with cURL.
     *
     * @param resource $ch     The cURL handle; ignored.
     * @param resource $fd     The file descriptor passed to cURL by the CURLOPT_INFILE option; ignored.
     * @param int      $length The maximum length of the portion to read.
     *
     * @return string a portion of this multipart object not larger than the given length,
     *                or an empty string if nothing remains to be read.
     * @throws LogicException           If the multipart is not yet finished.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    final public function curl_read($ch, $fd, $length)
    {
        return $this->read($length);
    }
    // phpcs:enable

    /**
     * Buffers the content of this multipart object.
     * Note that this method should be called before calling read,
     * otherwise the contents that have already read may not be part of the buffered content.
     * If the content is already buffered, this method will simply return the buffered content.
     *
     * @param int $bufferSize The size to use for reading parts of the content.
     *
     * @return string The content of this multipart object.
     * @throws InvalidArgumentException If the buffer size is not at least 1.
     * @throws LogicException           If the multipart is not yet finished.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    final public function buffer($bufferSize = 8192)
    {
        if (!$this->finished) {
            throw new LogicException('can\'t buffer a non-finished multipart object');
        }

        Util::validatePositiveInt($bufferSize, '$bufferSize');
        return $this->doBuffer($bufferSize);
    }

    /**
     * Buffers the content of this multipart object.
     *
     * @param int<1, max> $bufferSize The size to use for reading parts of the content.
     *
     * @return string The content of this multipart object.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    private function doBuffer($bufferSize = 8192)
    {
        if (!$this->isBuffered()) {
            $this->index = 0;
            $this->partIndex = 0;

            $content = '';
            while (($data = $this->doRead($bufferSize)) !== '') {
                $content .= $data;
            }
            $this->parts = [$content];
            $this->partCount = 1;
            $this->contentLength = strlen($content);
        }
        $this->index = 0;
        $this->partIndex = 0;

        return $this->parts[0];
    }

    /**
     * Returns whether or not the content is currently buffered.
     *
     * @return bool
     */
    final public function isBuffered()
    {
        return $this->partCount === 1 && is_string($this->parts[0]) && $this->contentLength === strlen($this->parts[0]);
    }

    /**
     * Returns this multipart object as a string. It will buffer the object to achieve this.
     * Note that this method should be called before calling read,
     * otherwise the contents that have already read may not be part of the result.
     *
     * @return string this multipart object as a string.
     */
    final public function __toString()
    {
        return $this->doBuffer();
    }
}
