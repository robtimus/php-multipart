<?php
namespace Robtimus\Multipart;

/**
 * A multipart/related object.
 */
final class MultipartRelated extends Multipart {

    /**
     * Creates a new multipart/related object.
     * @param string $boundary the multipart boundary. If empty a new boundary will be generated.
     */
    public function __construct($boundary = '') {
        parent::__construct($boundary, 'multipart/related');
    }

    /**
     * Adds a part.
     * @param string|resource|callable $content the part's content.
     *        If it's a callable it should take a length argument and return a string that is not larger than the input.
     * @param string $contentType the part's content type.
     * @param int the part's content length, or -1 if not known. Ignored if the part's content is a string.
     * @param string $contentTransferEncoding the optional content transfer encoding.
     * @return MultipartAlternative this object.
     */
    public function addPart($content, $contentType, $contentLength = -1, $contentTransferEncoding = '') {
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

    /**
     * Adds an inline file.
     * @param string $contentID the content id of the file.
     * @param string $filename the name of the file.
     * @param string|resource|callable $content the file's content.
     *        If it's a callable it should take a length argument and return a string that is not larger than the input.
     * @param string $contentType the file's content type.
     * @param int the file's content length, or -1 if not known. Ignored if the file's content is a string.
     * @param string $contentTransferEncoding the optional content transfer encoding.
     * @param string $contentDisposition the content disposition (e.g. attachment or inline).
     * @return MultipartMixed this object.
     */
    public function addInlineFile($contentID, $filename, $content, $contentType, $contentLength = -1, $contentTransferEncoding = '') {
        Util::validateNonEmptyString($contentID, '$contentID');
        Util::validateNonEmptyString($filename, '$filename');
        Util::validateStreamable($content, '$content');
        Util::validateNonEmptyString($contentType, '$contentType');
        Util::validateInt($contentLength, '$contentLength');
        Util::validateString($contentTransferEncoding, '$contentTransferEncoding');

        $this->startPart();
        $this->addContentType($contentType);
        $this->addContentID($contentID);
        if ($contentTransferEncoding !== '') {
            $this->addContentTransferEncoding($contentTransferEncoding);
        }
        $this->addContentDisposition('inline', '', $filename);
        $this->endHeaders();
        $this->addContent($content, $contentLength);
        $this->endPart();

        return $this;
    }
}
