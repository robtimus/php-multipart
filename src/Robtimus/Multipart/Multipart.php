<?php
namespace Robtimus\Multipart;

/**
 * Base class of multipart types.
 */
abstract class Multipart {

    /**
     * @var string the multipart boundary.
     */
    private $boundary;

    /**
     * @var string the content type.
     */
    private $contentType;

    /**
     * @var array the parts that form this multipart object. Each element is a non-empty string, resource or callable.
     */
    private $parts = [];

    /**
     * @var integer the number of parts.
     */
    private $partCount = 0;

    /**
     * @var bool whether or not the multipart is finished.
     */
    private $finished = false;

    /**
     * @var int the index of the current part.
     */
    private $index = 0;

    /**
     * @var int for string parts only, the index within the current part.
     */
    private $partIndex = 0;

    /**
     * @var int the content length, or -1 if not known.
     */
    private $contentLength = 0;

    /**
     * Creates a new multipart object.
     * @param string $boundary the multipart boundary. If empty a new boundary will be generated.
     * @param string $contentType the content type without the boundary.
     */
    protected function __construct($boundary, $contentType) {
        Util::validateString($boundary, '$boundary');
        Util::validateNonEmptyString($contentType, $contentType);

        $this->boundary = $boundary !== '' ? $boundary : $this->generateBoundary();
        $this->contentType = $contentType . '; boundary=' . $this->boundary;
    }

    /**
     * @return string a newly generated random boundary.
     */
    private function generateBoundary() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff));
    }

    /**
     * @return string the multipart boundary.
     */
    public final function getBoundary() {
        return $this->boundary;
    }

    /**
     * @return string the multipart's content type.
     */
    public final function getContentType() {
        return $this->contentType;
    }

    /**
     * @return int the multipart's content length, or -1 if not known.
     */
    public final function getContentLength() {
        return $this->contentLength;
    }

    /**
     * Starts a new part.
     */
    protected final function startPart() {
        $this->add('--' . $this->boundary . "\r\n");
    }

    /**
     * Adds a Content-Disposition header.
     * @param string $type the Content-Disposition type (e.g. form-data, attachment).
     * @param string $name the value for any name parameter.
     * @param string $filename the value for any filename parameter.
     */
    protected final function addContentDisposition($type, $name = '', $filename = '') {
        $header = 'Content-Disposition: ' . $type;
        if ($name !== '') {
            $header .= '; name="' . $name . '"';
        }
        if ($filename !== '') {
            $header .= '; filename="' . $filename . '"';
        }
        $this->add($header . "\r\n");
    }

    /**
     * Adds a Content-ID header.
     * @param string $contentID the content ID.
     */
    protected final function addContentID($contentID) {
        $this->add('Content-ID: ' . $contentID . "\r\n");
    }

    /**
     * Adds a Content-Type header.
     * @param string $contentType the content type.
     */
    protected final function addContentType($contentType) {
        $this->add('Content-Type: ' . $contentType . "\r\n");
    }

    /**
     * Adds a Content-Transfer-Encoding header.
     * @param string $contentTransferEncoding the content transfer encoding.
     */
    protected final function addContentTransferEncoding($contentTransferEncoding) {
        $this->add('Content-Transfer-Encoding: ' . $contentTransferEncoding . "\r\n");
    }

    /**
     * Ends the headers.
     */
    protected final function endHeaders() {
        $this->add("\r\n");
    }

    /**
     * Adds the content of a part.
     * @param string|resource|callable $content The content.
     *        If it's a callable it should take a length argument and return a string that is not larger than the input.
     * @param int $length the length of the part, or -1 if not known. Ignored if the part is a string.
     */
    protected final function addContent($content, $length = -1) {
        $this->add($content, $length);
    }

    /**
     * Ends the last part.
     */
    protected final function endPart() {
        $this->add("\r\n");
    }

    /**
     * Finishes the multipart. Nothing can be added to it afterwards.
     * @return Multipart this object.
     */
    public final function finish() {
        $this->add('--' . $this->boundary . "--\r\n");
        $this->finished = true;

        return $this;
    }

    /**
     * @return boolean whether or not the multipart is finished.
     */
    public final function isFinished() {
        return $this->finished;
    }

    /**
     * Adds a piece of a part.
     * @param string|resource|callable $part The part to add.
     *        If it's a callable it should take a length argument and return a string that is not larger than the input.
     * @param int $length the length of the part, or -1 if not known. Ignored if the part is a string.
     */
    private function add($part, $length = -1) {
        if ($this->finished) {
            throw new \LogicException('can\'t add to a finished multipart object');
        }

        if (is_string($part)) {
            $length = strlen($part);
            $this->parts[] = $part;
            $this->partCount++;
            if ($this->contentLength !== -1) {
                $this->contentLength += $length;
            }
        } else if (is_resource($part) || is_callable($part)) {
            $this->parts[] = $part;
            $this->partCount++;
            if ($length === -1) {
                $this->contentLength = -1;
            } else if ($this->contentLength !== -1) {
                $this->contentLength += $length;
            }
        } else {
            throw new \UnexpectedValueException('non-supported part type: ' . gettype($part));
        }
    }

    /**
     * Reads a portion of this multipart object.
     * @param int $length the maximum length of the portion to read.
     * @return string a portion of this multipart object not larger than the given length, or an empty string if nothing remains to be read.
     */
    public final function read($length) {
        if (!$this->finished) {
            throw new \LogicException('can\'t read from a non-finished multipart object');
        }

        Util::validateInt($length, '$length');
        if ($length <= 0) {
            return '';
        }

        return $this->doRead($length);
    }

    private function doRead($length) {
        while ($this->index < $this->partCount) {
            $data = $this->readFromPart($length);
            if ($data !== '') {
                return $data;
            }
            $this->index++;
            $this->partIndex = 0;
        }
        return '';
    }

    private function readFromPart($length) {
        $part = $this->parts[$this->index];
        if (is_string($part)) {
            $partLength = strlen($part);
            $length = min($length, $partLength - $this->partIndex);
            $result = $length === 0 ? '' : substr($part, $this->partIndex, $length);
            $this->partIndex += $length;
            return $result;
        } else if (is_resource($this->parts[$this->index])) {
            $result = @fread($part, $length);
            if ($result === false) {
                throw new \ErrorException(error_get_last()['message']);
            }
            return $result;
        } else if (is_callable($this->parts[$this->index])) {
            return call_user_func($part, $length);
        } else {
            throw new \UnexpectedValueException('non-supported part type: ' . gettype($this->parts[$this->index]));
        }
    }

    /**
     * cURL compatible version of the read method.
     * @param resource $ch the cURL handle; ignored.
     * @param resource $fd the file descriptor passed to cURL by the CURLOPT_INFILE option; ignored.
     * @param int $length the maximum length of the portion to read.
     * @return string a portion of this multipart object not larger than the given length, or an empty string if nothing remains to be read.
     */
    public final function curl_read($ch, $fd, $length) {
        return $this->read($length);
    }

    /**
     * Buffers the content of this multipart object.
     * Note that this method should be called before calling read, otherwise the contents that have already read may not be part of the buffered content.
     * If the content is already buffered, this method will simply return the buffered content.
     * @return string the content of this multipart object.
     */
    public final function buffer($bufferSize = 8192) {
        if (!$this->finished) {
            throw new \LogicException('can\'t buffer a non-finished multipart object');
        }

        Util::validatePositiveInt($bufferSize, '$bufferSize');
        return $this->doBuffer($bufferSize);
    }

    private function doBuffer($bufferSize = 8192) {
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
     * @return boolean whether or not the content is currently buffered.
     */
    public final function isBuffered() {
        return $this->partCount === 1 && is_string($this->parts[0]) && $this->contentType === strlen($this->parts[0]);
    }

    /**
     * Returns this multipart object as a string. It will buffer the object to achieve this.
     * Note that this method should be called before calling read, otherwise the contents that have already read may not be part of the result.
     */
    public final function __toString() {
        return $this->doBuffer();
    }
}
