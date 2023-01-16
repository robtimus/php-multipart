<?php
namespace Robtimus\Multipart;

/**
 * A multipart/form-data object.
 *
 * @package Robtimus\Multipart
 * @author  Rob Spoor
 * @license https://www.apache.org/licenses/LICENSE-2.0.txt The Apache Software License, Version 2.0
 */
final class MultipartFormData extends Multipart
{
    /**
     * Creates a new multipart/form-data object.
     *
     * @param string $boundary the multipart boundary. If empty a new boundary will be generated.
     */
    public function __construct($boundary = '')
    {
        parent::__construct($boundary, 'multipart/form-data');
    }

    /**
     * Adds a string parameter.
     *
     * @param string $name  The parameter name.
     * @param string $value The parameter value.
     *
     * @return MultipartFormData this object.
     */
    public function addValue($name, $value)
    {
        Util::validateNonEmptyString($name, '$name');
        Util::validateString($value, '$value');

        $this->startPart();
        $this->addContentDisposition('form-data', $name);
        $this->endHeaders();
        $this->addContent($value);
        $this->endPart();

        return $this;
    }

    /**
     * Adds a file parameter.
     *
     * @param string                               $name          The parameter name.
     * @param string                               $filename      The name of the file.
     * @param string|resource|callable(int):string $content       The file's content.
     *                                                            If it's a callable it should take a length argument
     *                                                            and return a string that is not larger than the input.
     * @param string                               $contentType   The file's content type.
     * @param int                                  $contentLength The file's content length, or -1 if not known.
     *                                                            Ignored if the file's content is a string.
     *
     * @return MultipartFormData this object.
     */
    public function addFile($name, $filename, $content, $contentType, $contentLength = -1)
    {
        Util::validateNonEmptyString($name, '$name');
        Util::validateNonEmptyString($filename, '$filename');
        Util::validateStreamable($content, '$content');
        Util::validateNonEmptyString($contentType, '$contentType');
        Util::validateInt($contentLength, '$contentLength');

        $this->startPart();
        $this->addContentDisposition('form-data', $name, $filename);
        $this->addContentType($contentType);
        $this->endHeaders();
        $this->addContent($content, $contentLength);
        $this->endPart();

        return $this;
    }
}
