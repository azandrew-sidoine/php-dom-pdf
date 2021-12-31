<?php

namespace Drewlabs\Core\Dompdf;

use Dompdf\Dompdf as PHPDomPdf;
use Dompdf\Options;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Drewlabs\Core\Dompdf\Proxy\PathPrefixer;

/**
 * @package [[Drewlabs\Core\Dompdf]]
 */
class Dompdf implements DomPdfable
{
    /**
     * PHP DomPdf instance
     *
     * @var PHPDomPdf
     */
    private $dompdf;

    /**
     *
     * @var boolean
     */
    private $rendered = false;

    /**
     * Path where generated pdf will be written
     * 
     * @var string
     */
    private $outputPath;

    /**
     * Creates an instance of the {@see Dompdf} class
     * 
     * @param array $options 
     * @return self 
     */
    public function __construct(array $options = [])
    {
        $this->dompdf = new PHPDomPdf($options ?? []);

        // #region Set the document output base path
        $this->setOutputPath($options['output_path'] ?? realpath(__DIR__ . '/app/documents/'));
        // #endregion
    }

    public function setOutputPath(string $path)
    {
        $this->outputPath = $path;
        return $this;
    }


    /**
     * Get PHP DomPdf instance
     *
     * @return PHPDomPdf
     */
    public function getDOMPdfProvider()
    {
        return $this->dompdf;
    }

    public function setPaperOrientation($paper, ?string $orientation = 'portrait')
    {
        $this->dompdf->setPaper($paper, $orientation);
        return $this;
    }

    public function loadHTML(string $string, ?string $encoding = null)
    {
        $string = $this->transformSpecialCharacters($string);
        $this->dompdf->loadHtml($string, $encoding);
        $this->rendered = false;
        return $this;
    }

    public function loadFile(string $path, ?string $encoding = null)
    {
        $this->dompdf->loadHtmlFile($path, $encoding);
        $this->rendered = false;
        return $this;
    }

    public function addInfo(array $infos = [])
    {
        foreach ($infos ?? [] as $key => $value) {
            $this->dompdf->add_info($key, $value);
        }
        return $this;
    }

    public function setPHPDomPdfOptions(array $options)
    {
        $options = new Options($options);
        $this->dompdf->setOptions($options);
        return $this;
    }

    public function printDocument()
    {
        if (!$this->rendered) {
            $this->render();
        }
        return $this->dompdf->output();
    }


    public function writeDocument(string $name, ?int $flags = null)
    {
        $name = sprintf("%s.%s", uniqid(str_replace(".pdf", "", $name ?? '')), "pdf");
        return @file_put_contents(
            PathPrefixer($this->outputPath)->prefix($name),
            $this->printDocument(),
            $flags ? LOCK_EX : 0
        );
    }


    public function download(string $name = 'document.pdf', string $disposition = 'attachment')
    {
        $path = PathPrefixer($this->outputPath)->prefix($name);
        // #region Write document to path
        file_put_contents($path, $this->printDocument(), LOCK_EX);
        // #endregion Write document to path
        return (new BinaryFileResponse(
            $path,
            200,
            ['Content-Type' => 'application/pdf'],
            true
        ))
            ->setContentDisposition(
                $disposition ?? 'attachement',
                $name,
                'document.pdf'
            )
            ->deleteFileAfterSend(true);
    }

    /**
     * Return a response with the PDF to show in the browser
     *
     * @param string $name
     * @param \Closure $callback
     * @return StreamedResponse
     */
    public function stream(string $name = 'document.pdf', $callback = null, string $disposition = 'attachment')
    {
        return $this->dompdf->stream($name);
    }

    public function setEncryption($password)
    {
        if (!$this->dompdf) {
            throw new Exception("PDF Provider not initialized");
        }
        $this->render();
        return $this->dompdf->getCanvas()->{'get_cpdf'}()->setEncryption("pass", $password);
    }

    /**
     * Render the PDF document
     */
    private function render()
    {
        if (!$this->dompdf) {
            throw new Exception('PDF Provider not initialized');
        }
        $this->dompdf->render();
        $this->rendered = true;
    }


    private function transformSpecialCharacters($subject)
    {
        foreach (array('€' => '&#0128;', '£' => '&pound;') as $search => $replace) {
            $subject = str_replace($search, $replace, $subject);
        }
        return $subject;
    }
}
