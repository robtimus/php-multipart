<?php
namespace Robtimus\Multipart;

/**
 * A multipart/alternative object.
 *
 * @package Robtimus\Multipart
 * @author  Rob Spoor
 * @license The Apache Software License, Version 2.0
 */
final class MultipartAlternative extends Multipart
{
    /**
     * Creates a new multipart/alternative object.
     *
     * @param string $boundary The multipart boundary. If empty a new boundary will be generated.
     */
    public function __construct($boundary = '')
    {
        parent::__construct($boundary, 'multipart/alternative');
    }

    /**
     * Adds a multipart alternative.
     *
     * @param Multipart $multipart The nested multipart.
     *
     * @return MultipartAlternative this object.
     */
    public function addMultipart(Multipart $multipart)
    {
        $this->startPart();
        $this->addContentType($multipart->getContentType());
        $this->endHeaders();
        $this->addContent(array($multipart, 'read'), $multipart->getContentLength());
        $this->endPart();

        return $this;
    }

    /**
     * Adds an alternative.
     *
     * @param string|resource|callable $content                 The part's content.
     *                                                          If it's a callable it should take a length argument
     *                                                          and return a string that is not larger than the input.
     * @param string                   $contentType             The part's content type.
     * @param int                      $contentLength           The part's content length, or -1 if not known.
     *                                                          Ignored if the part's content is a string.
     * @param string                   $contentTransferEncoding The optional content transfer encoding.
     *
     * @return MultipartAlternative this object.
     */
    public function addPart($content, $contentType, $contentLength = -1, $contentTransferEncoding = '')
    {
        Util::validateStreamable($content, '$content');
        Util::validateNonEmptyString($contentType, '$contentType');
        Util::validateInt($contentLength, '$contentLength');
        Util::validateString($contentTransferEncoding, '$contentTransferEncoding');

        $this->startPart();
        $this->addContentType($contentType);
        if ($contentTransferEncoding !== '') {
            $this->addContentTransferEncoding($contentTransferEncoding);
        }
        $this->endHeaders();
        $this->addContent($content, $contentLength);
        $this->endPart();

        return $this;
    }
}
