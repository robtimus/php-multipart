<?php
namespace Robtimus\Multipart;

/**
 * A multipart/related object.
 *
 * @package Robtimus\Multipart
 * @author  Rob Spoor
 * @license https://www.apache.org/licenses/LICENSE-2.0.txt The Apache Software License, Version 2.0
 */
final class MultipartRelated extends Multipart
{
    /**
     * Creates a new multipart/related object.
     *
     * @param string $boundary The multipart boundary. If empty a new boundary will be generated.
     */
    public function __construct($boundary = '')
    {
        parent::__construct($boundary, 'multipart/related');
    }

    /**
     * Adds a part.
     *
     * @param string|resource|callable(int):string $content                 The part's content.
     *                                                                      If it's a callable it should take a length argument
     *                                                                      and return a string that is not larger than the input.
     * @param string                               $contentType             The part's content type.
     * @param int                                  $contentLength           The part's content length, or -1 if not known.
     *                                                                      Ignored if the part's content is a string.
     * @param string                               $contentTransferEncoding The optional content transfer encoding.
     *
     * @return MultipartRelated this object.
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

    /**
     * Adds an inline file.
     *
     * @param string                               $contentID               The content id of the file.
     * @param string                               $filename                The name of the file.
     * @param string|resource|callable(int):string $content                 The file's content.
     *                                                                      If it's a callable it should take a length argument
     *                                                                      and return a string that is not larger than the input.
     * @param string                               $contentType             The file's content type.
     * @param int                                  $contentLength           The file's content length, or -1 if not known.
     *                                                                      Ignored if the file's content is a string.
     * @param string                               $contentTransferEncoding The optional content transfer encoding.
     *
     * @return MultipartRelated this object.
     */
    public function addInlineFile($contentID, $filename, $content, $contentType, $contentLength = -1, $contentTransferEncoding = '')
    {
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
