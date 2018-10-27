<?php
namespace Robtimus\Multipart;

/**
 * A multipart/alternative object.
 */
final class MultipartAlternative extends Multipart {

    /**
     * Creates a new multipart/alternative object.
     * @param string $boundary the multipart boundary. If empty a new boundary will be generated.
     */
    public function __construct($boundary = '') {
        parent::__construct($boundary, 'multipart/alternative');
    }

    /**
     * Adds an alternative.
     * @param string|resource|callable $content the file's content.
     *        If it's a callable it should take a length argument and return a string that is not larger than the input.
     * @param string $contentType the file's content type.
     * @param int the file's content length, or -1 if not known. Ignored if the file's content is a string.
     * @param string $contentTransferEncoding the optional content transfer encoding.
     * @return MultipartAlternative this object.
     */
    public function addAlternative($content, $contentType, $contentLength = -1, $contentTransferEncoding = '') {
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
