<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;
use LogicException;
use ValueError;

/**
 * A multipart/mixed object.
 *
 * @package Robtimus\Multipart
 * @author  Rob Spoor <robtimus@users.noreply.github.com>
 * @license https://www.apache.org/licenses/LICENSE-2.0.txt The Apache Software License, Version 2.0
 */
final class MultipartMixed extends Multipart
{
    /**
     * Creates a new multipart/mixed object.
     *
     * @param string $boundary The multipart boundary. If empty a new boundary will be generated.
     *
     * @throws ValueError If the given content type is empty.
     */
    public function __construct(string $boundary = '')
    {
        parent::__construct($boundary, 'multipart/mixed');
    }

    /**
     * Adds a nested multipart.
     *
     * @param Multipart $multipart The nested multipart.
     *
     * @return MultipartMixed this object.
     * @throws LogicException If the multipart is already finished.
     */
    public function addMultipart(Multipart $multipart): MultipartMixed
    {
        $this->addNestedMultipart($multipart);
        return $this;
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
     * @param string                               $contentTransferEncoding The part's optional content transfer encoding.
     *
     * @return MultipartMixed this object.
     * @throws InvalidArgumentException If the content is not a string, resource or callable.
     * @throws ValueError               If the content type is empty.
     * @throws LogicException           If the multipart is already finished.
     */
    public function addPart(
        mixed  $content,
        string $contentType,
        int    $contentLength = -1,
        string $contentTransferEncoding = ''
    ): MultipartMixed {
        Util::validateStreamable($content, '$content');
        Util::validateNonEmptyString($contentType, '$contentType');

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
     * Adds a file attachment.
     *
     * @param string                               $filename                The name of the file.
     * @param string|resource|callable(int):string $content                 The file's content.
     *                                                                      If it's a callable it should take a length argument
     *                                                                      and return a string that is not larger than the input.
     * @param string                               $contentType             The file's content type.
     * @param int                                  $contentLength           The file's content length, or -1 if not known.
     *                                                                      Ignored if the file's content is a string.
     * @param string                               $contentTransferEncoding The part's optional content transfer encoding.
     *
     * @return MultipartMixed this object.
     * @throws ValueError               If the file name or content type is empty.
     * @throws InvalidArgumentException If the content is not a string, resource or callable.
     * @throws LogicException           If the multipart is already finished.
     */
    public function addAttachment(
        string $filename,
        mixed  $content,
        string $contentType,
        int    $contentLength = -1,
        string $contentTransferEncoding = ''
    ): MultipartMixed {
        Util::validateNonEmptyString($filename, '$filename');
        Util::validateStreamable($content, '$content');
        Util::validateNonEmptyString($contentType, '$contentType');

        $this->startPart();
        $this->addContentType($contentType);
        if ($contentTransferEncoding !== '') {
            $this->addContentTransferEncoding($contentTransferEncoding);
        }
        $this->addContentDisposition('attachment', '', $filename);
        $this->endHeaders();
        $this->addContent($content, $contentLength);
        $this->endPart();

        return $this;
    }
}
