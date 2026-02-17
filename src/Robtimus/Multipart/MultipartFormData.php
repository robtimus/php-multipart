<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;
use LogicException;
use ValueError;

/**
 * A multipart/form-data object.
 *
 * @package Robtimus\Multipart
 * @author  Rob Spoor <robtimus@users.noreply.github.com>
 * @license https://www.apache.org/licenses/LICENSE-2.0.txt The Apache Software License, Version 2.0
 */
final class MultipartFormData extends Multipart
{
    /**
     * Creates a new multipart/form-data object.
     *
     * @param string $boundary the multipart boundary. If empty a new boundary will be generated.
     *
     * @throws ValueError If the given content type is empty.
     */
    public function __construct(string $boundary = '')
    {
        parent::__construct($boundary, 'multipart/form-data');
    }

    /**
     * Adds a string parameter.
     *
     * @param string $name                    The parameter name.
     * @param string $value                   The parameter value.
     * @param string $contentType             The part's optional content type.
     * @param string $contentTransferEncoding The part's optional content transfer encoding.
     *
     * @return MultipartFormData this object.
     * @throws ValueError     If the parameter name is empty.
     * @throws LogicException If the multipart is already finished.
     */
    public function addValue(
        string $name,
        string $value,
        string $contentType = '',
        string $contentTransferEncoding = ''
    ): MultipartFormData {
        Util::validateNonEmptyString($name, '$name');

        $this->startPart();
        $this->addContentDisposition('form-data', $name);
        if ($contentType !== '') {
            $this->addContentType($contentType);
        }
        if ($contentTransferEncoding !== '') {
            $this->addContentTransferEncoding($contentTransferEncoding);
        }
        $this->endHeaders();
        $this->addContent($value);
        $this->endPart();

        return $this;
    }

    /**
     * Adds a file parameter.
     *
     * @param string                               $name                    The parameter name.
     * @param string                               $filename                The name of the file.
     * @param string|resource|callable(int):string $content                 The file's content.
     *                                                                      If it's a callable it should take a length argument
     *                                                                      and return a string that is not larger than the input.
     * @param string                               $contentType             The file's content type.
     * @param int                                  $contentLength           The file's content length, or -1 if not known.
     *                                                                      Ignored if the file's content is a string.
     * @param string                               $contentTransferEncoding The part's optional content transfer encoding.
     *
     * @return MultipartFormData this object.
     * @throws ValueError               If the parameter name, file name or content type is empty.
     * @throws InvalidArgumentException If the content is not a string, resource or callable.
     * @throws LogicException           If the multipart is already finished.
     */
    public function addFile(
        string $name,
        string $filename,
        mixed  $content,
        string $contentType,
        int    $contentLength = -1,
        string $contentTransferEncoding = ''
    ): MultipartFormData {
        Util::validateNonEmptyString($name, '$name');
        Util::validateNonEmptyString($filename, '$filename');
        Util::validateStreamable($content, '$content');
        Util::validateNonEmptyString($contentType, '$contentType');

        $this->startPart();
        $this->addContentDisposition('form-data', $name, $filename);
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
