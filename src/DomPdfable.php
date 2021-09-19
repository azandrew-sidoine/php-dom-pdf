<?php

namespace Drewlabs\Core\Dompdf;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @package [[Drewlabs\Core\Dompdf]]
 */
interface DomPdfable
{

    /**
     * Set the paper size (default A4)
     *
     * @param string $paper
     * @param string $orientation
     * @return self
     */
    public function setPaperOrientation($paper, $orientation = 'portrait');


    /**
     * Load a HTML string
     *
     * @param string $string
     * @param string|null $encoding Not used yet
     * @return self
     */
    public function loadHTML(string $string, ?string $encoding = null);

    /**
     * Load a HTML file
     *
     * @param string $file
     * @param string|null $encoding
     * @return self
     */
    public function loadFile(string $path, ?string $encoding = null);

    /**
     * Add metadata to the document
     *
     * @param array $infos
     * @return self
     */
    public function addInfo(array $infos = []);

    /**
     * Update the PHP DOM PDF Options
     *
     * @param array $options
     * @return static
     */
    public function setPHPDomPdfOptions(array $options);

    /**
     * Output the PDF as a string.
     *
     * @return string The rendered PDF as string
     */
    public function printDocument();

    /**
     * Save the PDF to a file. $flags parameter modified how the file write operation is performed.
     *
     * @param string $path
     * @param $flags
     * @return int|false
     */
    public function writeDocument(string $path, ?int $flags = null);

    /**
     * Make the PDF downloadable by the user
     *
     * @param string $filename
     * @param string $disposition
     * @return BinaryFileResponse
     */
    public function download(string $name = 'document.pdf', string $disposition = 'attachment');
    /**
     * Return a response with the PDF to show in the browser
     *
     * @param string $name
     * @param \Closure|null $callback
     * @return StreamedResponse
     */
    public function stream(string $name = 'document.pdf', $callback = null, string $disposition = 'attachment');
}
