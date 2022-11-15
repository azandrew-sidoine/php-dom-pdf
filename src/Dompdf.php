<?php

namespace Drewlabs\Dompdf;

use DOMException;
use Dompdf\Dompdf as PHPDomPdf;
use Dompdf\Exception as DompdfException;
use Dompdf\Options;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use SplFileInfo;

use function Drewlabs\Dompdf\Proxy\PathPrefixer;

class Dompdf implements DomPdfable, IOReader, IOWriter
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
     * @param PHPDomPdf $instance 
     * @return self 
     */
    public function __construct(PHPDomPdf $instance)
    {
        $this->dompdf = $instance;
    }

    /**
     * {@inheritDoc}
     * 
     * @return DomPdfable 
     * @throws RuntimeException 
     * @throws InvalidArgumentException 
     */
    public function read($pathorstream)
    {
        if ($pathorstream instanceof StreamInterface) {
            return $this->html($pathorstream->__toString());
        } else if ($pathorstream instanceof SplFileInfo) {
            return $this->html($this->getPathContents($pathorstream));
        } else if (null === $pathorstream) {
            $pathorstream = '';
        }
        if (!is_string($pathorstream)) {
            throw new InvalidArgumentException('Invalid parameter type passed to ' . __METHOD__ . '. Expected string|\SplFileInfo::class|' . StreamInterface::class);
        }
        if (false !== filter_var($pathorstream, FILTER_VALIDATE_URL)) {
            return $this->resource($pathorstream);
        }
        $isdiskfile = @is_file($pathorstream);
        if ($pathorstream === '' || !$isdiskfile) {
            return $this->html($pathorstream);
        }
        if ($isdiskfile && ($path = new SplFileInfo($pathorstream))) {
            return $this->html($this->getPathContents($path));
        }
        throw new RuntimeException('Unknown Error : Failed to read DOM string');
    }

    /**
     * Creates a new instance of the DOMPdf class
     * 
     * @param array $options 
     * @return static 
     */
    public static function new($options = [])
    {
        $result = is_callable($options) || ($options instanceof \Closure) ? ($options() ?? []) : ($options ?? []);
        $object = new static(new PHPDomPdf($result));
        $object->setOutputPath($options['output_path'] ?? realpath(__DIR__ . '/app/documents/'));
        return $object;
    }

    public function setOutputPath(string $path)
    {
        $this->outputPath = $path;
        return $this;
    }

    public function contraints($size, ?string $orientation = Orientation::PORTRAIT)
    {
        $this->dompdf->setPaper($size, $orientation);
        return $this;
    }

    public function html(string $string, ?string $encoding = null)
    {
        $string = $this->transformSpecialCharacters($string);
        $this->dompdf->loadHtml($string, $encoding);
        $this->rendered = false;
        return $this;
    }

    public function resource(string $path, ?string $encoding = null)
    {
        $this->dompdf->loadHtmlFile($path, $encoding);
        $this->rendered = false;
        return $this;
    }

    public function addInfo(array $infos = [])
    {
        foreach ($infos ?? [] as $key => $value) {
            method_exists($this->dompdf, 'add_info') ?
                \Closure::fromCallable([$this->dompdf, 'add_info'])->__invoke($key, $value) :
                \Closure::fromCallable([$this->dompdf, 'addInfo'])->__invoke($key, $value);
        }
        return $this;
    }

    public function setOptions(array $options)
    {
        $options = new Options($options);
        $this->dompdf->setOptions($options);
        return $this;
    }

    public function print()
    {
        if (!$this->rendered) {
            $this->render();
        }
        return $this->dompdf->output();
    }

    public function write(string $name, ?int $flags = null)
    {
        [$directory, $name] = $name[0] === DIRECTORY_SEPARATOR ? [dirname($name), basename($name)] : [$this->outputPath, $name];
        $name = sprintf("%s.%s", uniqid(str_replace(".pdf", "", $name ?? '')), "pdf");
        return @file_put_contents(PathPrefixer($directory)->prefix($name), $this->print(), $flags ? LOCK_EX : 0);
    }

    public function download(string $name = 'document.pdf', string $disposition = 'attachment')
    {
        return Response::new($this);
    }

    public function stream(string $name = 'document.pdf', $callback = null, string $disposition = 'attachment')
    {
        return $this->dompdf->stream($name);
    }

    /**
     * Add encryption to pdf instance
     * 
     * @param mixed $cypherText 
     * @return mixed 
     * @throws Exception 
     * @throws DompdfException 
     * @throws DOMException 
     */
    public function encrypt($cypherText)
    {
        if (method_exists($canvas = $this->dompdf->getCanvas(), 'get_cpdf')) {
            if (!$this->rendered) {
                $this->render();
            }
            \Closure::fromCallable([$canvas, 'get_cpdf'])->__invoke()->setEncryption("pass", $cypherText);
        }
    }

    /**
     * Get PHP DomPdf instance
     *
     * @return PHPDomPdf
     */
    public function getInstance()
    {
        return $this->dompdf;
    }

    /**
     * Render the PDF document
     */
    private function render()
    {
        $this->dompdf->render();
        $this->rendered = true;
    }

    /**
     * Replace HTML special characters in the subject string
     * 
     * @param mixed $subject 
     * @return mixed 
     */
    private function transformSpecialCharacters(string $subject)
    {
        return htmlspecialchars($subject);
    }

    /**
     * Read content from a {@see \SplFileInfo} instance
     * 
     * @param SplFileInfo $object 
     * @return string 
     * @throws RuntimeException 
     */
    private function getPathContents(SplFileInfo $object)
    {
        $error = null;
        set_error_handler(function ($type, $msg) use (&$error) {
            $error = $msg;
        });
        $content = file_get_contents($object->getRealPath());
        restore_error_handler();
        if (false === $content) {
            throw new \RuntimeException($error ?? '');
        }
        return $content;
    }
}
