<?php

namespace Robtimus\Multipart;

use ErrorException;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;
use ValueError;

/**
 * Base class of multipart types.
 *
 * @package Robtimus\Multipart
 * @author  Rob Spoor <robtimus@users.noreply.github.com>
 * @license https://www.apache.org/licenses/LICENSE-2.0.txt The Apache Software License, Version 2.0
 */
abstract class Multipart
{
    /**
     * The multipart boundary.
     *
     * @var string
     */
    private $_boundary;

    /**
     * The content type.
     *
     * @var string
     */
    private $_contentType;

    /**
     * The parts that form this multipart object.
     *
     * @var array<string|resource|callable(int):string>
     */
    private $_parts = [];

    /**
     * The number of parts.
     *
     * @var integer
     */
    private $_partCount = 0;

    /**
     * Whether or not the multipart is finished.
     *
     * @var bool
     */
    private $_finished = false;

    /**
     * The index of the current part.
     *
     * @var int
     */
    private $_index = 0;

    /**
     * For string parts only, the index within the current part.
     *
     * @var int
     */
    private $_partIndex = 0;

    /**
     * The content length, or -1 if not known.
     *
     * @var int
     */
    private $_contentLength = 0;

    /**
     * Creates a new multipart object.
     *
     * @param string $boundary    The multipart boundary. If empty a new boundary will be generated.
     * @param string $contentType The content type without the boundary.
     *
     * @throws ValueError If the given content type is empty.
     */
    protected function __construct(string $boundary, string $contentType)
    {
        Util::validateNonEmptyString($contentType, '$contentType');

        $this->_boundary = $boundary !== '' ? $boundary : $this->_generateBoundary();
        $this->_contentType = $contentType . '; boundary=' . $this->_boundary;
    }

    /**
     * Generates a new random boundary.
     *
     * @return string
     */
    private function _generateBoundary(): string
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
    final public function getBoundary(): string
    {
        return $this->_boundary;
    }

    /**
     * Returns the multipart's content type.
     *
     * @return string
     */
    final public function getContentType(): string
    {
        return $this->_contentType;
    }

    /**
     * Returns the multipart's content length, or -1 if not known.
     *
     * @return int
     */
    final public function getContentLength(): int
    {
        return $this->_contentLength;
    }

    /**
     * Starts a new part.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function startPart(): void
    {
        $this->_add('--' . $this->_boundary . "\r\n");
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
    final protected function addContentDisposition(string $type, string $name = '', string $filename = '')
    {
        $headerValue = $type;
        if ($name !== '') {
            $headerValue .= '; name="' . $name . '"';
        }
        if ($filename !== '') {
            $headerValue .= '; filename="' . $filename . '"';
        }
        $this->_addHeader('Content-Disposition', $headerValue);
    }

    /**
     * Adds a Content-ID header.
     *
     * @param string $contentID The content ID.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addContentID(string $contentID): void
    {
        $this->_addHeader('Content-ID', $contentID);
    }

    /**
     * Adds a Content-Type header.
     *
     * @param string $contentType The content type.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addContentType(string $contentType): void
    {
        $this->_addHeader('Content-Type', $contentType);
    }

    /**
     * Adds a Content-Transfer-Encoding header.
     *
     * @param string $contentTransferEncoding The content transfer encoding.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addContentTransferEncoding(string $contentTransferEncoding): void
    {
        $this->_addHeader('Content-Transfer-Encoding', $contentTransferEncoding);
    }

    /**
     * Adds a header.
     *
     * @param string $headerName  The name of the header.
     * @param string $headerValue The value of the header.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    private function _addHeader(string $headerName, string $headerValue): void
    {
        $this->_add($headerName . ': ' . $headerValue . "\r\n");
    }

    /**
     * Ends the headers.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function endHeaders(): void
    {
        $this->_add("\r\n");
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
    final protected function addContent(mixed $content, int $length = -1): void
    {
        $this->_add($content, $length);
    }

    /**
     * Adds a nested multipart.
     *
     * @param Multipart $multipart The nested multipart.
     *
     * @return void
     * @throws LogicException If the multipart is already finished.
     */
    final protected function addNestedMultipart(Multipart $multipart): void
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
    final protected function endPart(): void
    {
        $this->_add("\r\n");
    }

    /**
     * Finishes the multipart. Nothing can be added to it afterwards.
     *
     * @return Multipart this object.
     * @throws LogicException If the multipart is already finished.
     */
    final public function finish(): Multipart
    {
        $this->_add('--' . $this->_boundary . "--\r\n");
        $this->_finished = true;

        return $this;
    }

    /**
     * Returns whether or not the multipart is finished.
     *
     * @return bool
     */
    final public function isFinished(): bool
    {
        return $this->_finished;
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
    private function _add(mixed $part, int $length = -1): void
    {
        if ($this->_finished) {
            throw new LogicException('can\'t add to a finished multipart object');
        }

        if (is_string($part)) {
            $length = strlen($part);
            $this->_parts[] = $part;
            $this->_partCount++;
            if ($this->_contentLength !== -1) {
                $this->_contentLength += $length;
            }
        } elseif (is_resource($part) || is_callable($part)) {
            $this->_parts[] = $part;
            $this->_partCount++;
            if ($length === -1) {
                $this->_contentLength = -1;
            } elseif ($this->_contentLength !== -1) {
                $this->_contentLength += $length;
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
    final public function read(int $length): string
    {
        if (!$this->_finished) {
            throw new LogicException('can\'t read from a non-finished multipart object');
        }

        if ($length <= 0) {
            return '';
        }

        return $this->_doRead($length);
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
    private function _doRead(int $length): string
    {
        while ($this->_index < $this->_partCount) {
            $data = $this->_doReadFromPart($length);
            if ($data !== '') {
                return $data;
            }
            $this->_index++;
            $this->_partIndex = 0;
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
    private function _doReadFromPart(int $length): string
    {
        $part = $this->_parts[$this->_index];
        if (is_string($part)) {
            $partLength = strlen($part);
            $length = min($length, $partLength - $this->_partIndex);
            $result = $length === 0 ? '' : substr($part, $this->_partIndex, $length);
            $this->_partIndex += $length;
            return $result;
        } elseif (is_resource($part)) {
            $result = @fread($part, $length);
            if ($result === false) {
                // @phpstan-ignore offsetAccess.notFound
                throw new ErrorException(error_get_last()['message']);
            }
            return $result;
        } elseif (is_callable($part)) {
            return call_user_func($part, $length);
        } else {
            throw new UnexpectedValueException('non-supported part type: ' . gettype($part));
        }
    }

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
    final public function curlRead($ch, $fd, int $length): string
    {
        return $this->read($length);
    }

    /**
     * Buffers the content of this multipart object.
     * Note that this method should be called before calling read,
     * otherwise the contents that have already read may not be part of the buffered content.
     * If the content is already buffered, this method will simply return the buffered content.
     *
     * @param int $bufferSize The size to use for reading parts of the content.
     *
     * @return string The content of this multipart object.
     * @throws ValueError               If the buffer size is not at least 1.
     * @throws LogicException           If the multipart is not yet finished.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    final public function buffer(int $bufferSize = 8192): string
    {
        if (!$this->_finished) {
            throw new LogicException('can\'t buffer a non-finished multipart object');
        }

        if ($bufferSize <= 0) {
            throw new ValueError('$bufferSize <= 0');
        }

        return $this->_doBuffer($bufferSize);
    }

    /**
     * Buffers the content of this multipart object.
     *
     * @param int<1, max> $bufferSize The size to use for reading parts of the content.
     *
     * @return string The content of this multipart object.
     * @throws UnexpectedValueException If any resource part is no longer readable.
     */
    private function _doBuffer(int $bufferSize = 8192): string
    {
        if (!$this->isBuffered()) {

            $this->_index = 0;
            $this->_partIndex = 0;

            $content = '';
            while (($data = $this->_doRead($bufferSize)) !== '') {
                $content .= $data;
            }
            $this->_parts = [$content];
            $this->_partCount = 1;
            $this->_contentLength = strlen($content);
        }
        $this->_index = 0;
        $this->_partIndex = 0;

        // when buffered, $this->_parts is an array with a single string element
        // @phpstan-ignore return.type
        return $this->_parts[0];
    }

    /**
     * Returns whether or not the content is currently buffered.
     *
     * @return bool
     */
    final public function isBuffered(): bool
    {
        return $this->_partCount === 1 && is_string($this->_parts[0]) && $this->_contentLength === strlen($this->_parts[0]);
    }

    /**
     * Returns this multipart object as a string. It will buffer the object to achieve this.
     * Note that this method should be called before calling read,
     * otherwise the contents that have already read may not be part of the result.
     *
     * @return string this multipart object as a string.
     */
    final public function __toString(): string
    {
        return $this->_doBuffer();
    }
}
